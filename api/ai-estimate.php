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

// ---------------------------------------------------------------
// Build the estimating prompt
// ---------------------------------------------------------------
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

$full_prompt = $system_prompt . "\n\nProject Description:\n" . $prompt_text;

try {
    [$parsed, $inputTokens, $outputTokens, $cost, $model] = $claude->askJson($full_prompt, 4096);

    if ($parsed === null) {
        echo json_encode(['error' => 'Claude returned an unexpected response. Please try again.']);
        exit;
    }

    // Log the AI call
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

    $parsed['cost_usd']       = $cost;
    $parsed['input_tokens']   = $inputTokens;
    $parsed['output_tokens']  = $outputTokens;
    $parsed['model']          = $model;

    echo json_encode($parsed);

} catch (RuntimeException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
