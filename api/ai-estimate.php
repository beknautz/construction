<?php
/**
 * api/ai-estimate.php
 * Accepts a plain-English project description, calls Claude,
 * returns JSON with scope suggestions, line items, risks, missing items.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../src/ClaudeService.php';

header('Content-Type: application/json');
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']); exit;
}

$db          = get_db();
$estimate_id = (int)($_POST['estimate_id'] ?? 0);
$prompt_text = trim($_POST['prompt']       ?? '');

if (!$estimate_id || !$prompt_text) {
    echo json_encode(['error' => 'estimate_id and prompt are required']); exit;
}

$claude = new ClaudeService($db);

if (!$claude->isEnabled()) {
    echo json_encode(['error' => 'Claude API key not configured. Go to Settings → AI Settings.']); exit;
}

// Tenant quota check
if (tid() !== null) {
    $tidVal = tid();
    $quotaRow = $db->prepare('SELECT t.ai_calls_used, p.ai_calls_limit
                               FROM tenants t JOIN subscription_plans p ON p.id = t.plan_id
                               WHERE t.id = ? LIMIT 1');
    $quotaRow->execute([$tidVal]);
    $quota = $quotaRow->fetch();
    if ($quota && (int)$quota['ai_calls_used'] >= (int)$quota['ai_calls_limit']) {
        echo json_encode(['error' => 'AI call limit reached for this billing period. Upgrade your plan to continue.']);
        exit;
    }
}

// ---------------------------------------------------------------
// Build the estimating prompt
// ---------------------------------------------------------------
// Build labor rates context from tenant settings
$rates_json  = trim($_POST['rates'] ?? '{}');
$tenantRates = json_decode($rates_json, true) ?: [];

$rateLines = [];
$rateMap = [
    'labor_rate_general'     => 'General laborer',
    'labor_rate_carpenter'   => 'Carpenter/framer',
    'labor_rate_electrician' => 'Electrician',
    'labor_rate_plumber'     => 'Plumber',
    'labor_rate_hvac'        => 'HVAC technician',
    'labor_rate_painter'     => 'Painter',
    'labor_rate_equipment'   => 'Equipment operator',
];
foreach ($rateMap as $key => $label) {
    $rate = (float)($tenantRates[$key] ?? 0);
    if ($rate > 0) {
        $rateLines[] = "- {$label}: \${$rate}/hr";
    }
}
$ratesContext = '';
if ($rateLines) {
    $ratesContext = "\n\nCONTRACTOR LABOR RATES — use these instead of any benchmarks above:\n"
        . implode("\n", $rateLines)
        . "\n\nHow to use rates: labor_cost per unit = (hours for that unit) × (hourly rate)."
        . "\nExample: installing 1 door at 2 hrs × \$75/hr carpenter = labor_cost: 150 with qty:1 unit:EA."
        . "\nExample: painting walls at 0.05 hrs/SF × \$55/hr painter = labor_cost: 2.75 per SF with qty:[sqft] unit:SF."
        . "\nDo NOT use national averages for any trade that has a rate listed above.";
}

$system_prompt = <<<'PROMPT'
You are an expert construction estimator for US residential and commercial contractors.

CRITICAL COST RULES — read carefully before generating any numbers:

The line_total for each item is calculated as:
  line_total = (labor_cost + material_cost + equipment_cost + sub_cost) × qty

This means cost fields are ALWAYS per-unit costs, not totals.

EXAMPLES OF CORRECT vs WRONG:

CORRECT — Remove kitchen cabinets (lump sum):
  qty=1, unit="lot", labor_cost=480, material_cost=0  → line_total = $480
  (1 person × 6 hours × $80/hr = $480)

WRONG — same item:
  qty=30, unit="LF", labor_cost=480  → line_total = $14,400  ← NEVER DO THIS

CORRECT — Install LVP flooring (measured):
  qty=200, unit="SF", labor_cost=2.50, material_cost=4.50  → line_total = $1,400
  (labor $2.50/SF + material $4.50/SF × 200 SF)

WRONG — same item:
  qty=200, unit="SF", labor_cost=500, material_cost=900  → line_total = $280,000  ← NEVER DO THIS

CORRECT — Electrician rough-in (hourly):
  qty=16, unit="HR", labor_cost=110, material_cost=0  → line_total = $1,760
  (16 hours × $110/hr electrician rate)

CORRECT — Permits (flat fee):
  qty=1, unit="lot", labor_cost=0, material_cost=850  → line_total = $850

RULES:
- If using qty > 1 with a unit like SF/LF/HR/EA, cost fields must be the PER-UNIT rate.
- If the job is a lump sum, use qty=1 and unit="lot", and put the TOTAL cost in the field.
- Never multiply cost by qty yourself — the system does that automatically.
- Labor costs must reflect realistic crew hours × hourly wage, not arbitrary numbers.
- Material costs must reflect real supplier pricing (lumber, fixtures, tile, etc.).
- Do not include travel time or fuel in Demo or other sections — omit these entirely.

Realistic US labor benchmarks (adjust if contractor rates are provided):
- General laborer: $45–60/hr
- Carpenter/framer: $65–90/hr
- Electrician: $85–120/hr
- Plumber: $80–110/hr
- Painter: $45–65/hr
- HVAC tech: $80–100/hr
- Equipment operator: $65–85/hr

Return ONLY valid JSON in this exact structure (no markdown, no explanation):
{
  "summary": "One sentence overview of the project",
  "sections": [
    {
      "category": "Demo",
      "items": [
        {
          "description": "Remove existing kitchen cabinets and countertops",
          "qty": 1,
          "unit": "lot",
          "labor_cost": 480,
          "material_cost": 0,
          "equipment_cost": 0,
          "sub_cost": 0
        },
        {
          "description": "Remove existing tile flooring",
          "qty": 200,
          "unit": "SF",
          "labor_cost": 1.75,
          "material_cost": 0,
          "equipment_cost": 0,
          "sub_cost": 0
        }
      ]
    }
  ],
  "risks": ["Specific risk the contractor must verify before pricing"],
  "missing": ["Item likely needed that was not mentioned"],
  "allowances": ["Allowance item: suggested budget range"]
}

Valid category values: Demo, Framing, Concrete, Plumbing, Electrical, HVAC, Drywall, Tile, Flooring, Cabinets, Finish Carpentry, Paint, Excavation, Permits, Other

All cost values must be plain numbers (no $ signs, no commas).
PROMPT;

$full_prompt = $system_prompt . $ratesContext . "\n\nProject Description:\n" . $prompt_text;

try {
    [$parsed, $inputTokens, $outputTokens, $cost, $model] = $claude->askJson($full_prompt, 4096);

    if ($parsed === null) {
        echo json_encode(['error' => 'Claude returned an unexpected response. Please try again.']);
        exit;
    }

    // Log the AI call to estimate history
    $db->prepare(
        'INSERT INTO estimate_ai_suggestions
         (estimate_id, user_id, prompt, response, model, input_tokens, output_tokens, cost_usd)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $estimate_id,
        $_SESSION['user_id'],
        $prompt_text,
        json_encode($parsed),
        $model,
        $inputTokens,
        $outputTokens,
        $cost,
    ]);

    // Log against tenant quota
    log_tenant_ai_usage($inputTokens, $outputTokens, $cost, 'estimate', $estimate_id, $model);

    $parsed['cost_usd']       = $cost;
    $parsed['input_tokens']   = $inputTokens;
    $parsed['output_tokens']  = $outputTokens;
    $parsed['model']          = $model;

    echo json_encode($parsed);

} catch (RuntimeException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
