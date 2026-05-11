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
    $ratesContext = "\n\nThis contractor's labor rates (USE THESE EXACT RATES when calculating labor costs):\n"
        . implode("\n", $rateLines)
        . "\nCalculate labor_cost as: hours_needed × hourly_rate. Do not use national averages when rates are provided above.";
}

$system_prompt = <<<PROMPT
You are an expert construction estimator for residential and commercial contractors.
A contractor has described a project in plain English. Your job is to:
1. Identify all scope sections needed (use standard construction categories)
2. List line items for each section with estimated labor and material costs
3. Flag risk items the contractor should verify
4. Note any items that seem to be missing from the description
5. Add allowances for items with uncertain costs

Return ONLY valid JSON in this exact structure:
{
  "summary": "One sentence overview of the project",
  "sections": [
    {
      "category": "Demo",
      "items": [
        {
          "description": "Remove existing kitchen cabinets",
          "qty": 1,
          "unit": "lot",
          "labor_cost": 400,
          "material_cost": 0,
          "equipment_cost": 0,
          "sub_cost": 0
        }
      ]
    }
  ],
  "risks": ["List of risk items the contractor should verify"],
  "missing": ["Items likely needed but not mentioned"],
  "allowances": ["Suggested allowance items with typical budget ranges"]
}

Valid category values: Demo, Framing, Concrete, Plumbing, Electrical, HVAC, Drywall, Tile, Flooring, Cabinets, Finish Carpentry, Paint, Excavation, Permits, Other

All cost values must be numbers (no dollar signs). Provide realistic market-rate estimates for a US contractor.
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
