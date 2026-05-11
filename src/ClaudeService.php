<?php
/**
 * ClaudeService.php
 * Pure cURL Claude API client — no Composer SDK required.
 * Ported from the mediapurchasing ClaudeVideoService pattern.
 */
class ClaudeService
{
    private PDO $db;

    // Cost per 1M tokens (USD) by model
    private static array $RATES = [
        'claude-opus-4-5'    => ['input' => 15.00, 'output' => 75.00],
        'claude-sonnet-4-5'  => ['input' =>  3.00, 'output' => 15.00],
        'claude-haiku-4-5'   => ['input' =>  0.80, 'output' =>  4.00],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ---------------------------------------------------------------
    // Public: call Claude with a prompt, returns [text, in, out, cost]
    // ---------------------------------------------------------------
    public function ask(string $prompt, int $maxTokens = 4096): array
    {
        [$text, $inputTokens, $outputTokens] = $this->callClaude($prompt, $maxTokens);
        $model = $this->getSetting('anthropic_model') ?: 'claude-sonnet-4-5';
        $cost  = $this->estimateCost($inputTokens, $outputTokens, $model);
        return [$text, $inputTokens, $outputTokens, $cost, $model];
    }

    // ---------------------------------------------------------------
    // Public: call Claude and expect a JSON response
    // Returns [array|null, inputTokens, outputTokens, cost, model]
    // ---------------------------------------------------------------
    public function askJson(string $prompt, int $maxTokens = 4096): array
    {
        [$text, $in, $out, $cost, $model] = $this->ask($prompt, $maxTokens);
        $parsed = $this->parseJson($text);
        return [$parsed, $in, $out, $cost, $model];
    }

    // ---------------------------------------------------------------
    // Cost estimation
    // ---------------------------------------------------------------
    public function estimateCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $rates      = self::$RATES[$model] ?? self::$RATES['claude-sonnet-4-5'];
        $inputCost  = ($inputTokens  / 1_000_000) * $rates['input'];
        $outputCost = ($outputTokens / 1_000_000) * $rates['output'];
        return round($inputCost + $outputCost, 6);
    }

    // ---------------------------------------------------------------
    // Check if AI is enabled and key is configured
    // ---------------------------------------------------------------
    public function isEnabled(): bool
    {
        if ($this->getSetting('ai_enabled') !== '1') return false;
        $key = $this->getSetting('anthropic_api_key');
        return !empty($key);
    }

    // ---------------------------------------------------------------
    // Read a setting from ai_settings table
    // ---------------------------------------------------------------
    public function getSetting(string $key): string
    {
        static $cache = [];
        if (!isset($cache[$key])) {
            $stmt = $this->db->prepare('SELECT setting_value FROM ai_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $cache[$key] = (string)($stmt->fetchColumn() ?: '');
        }
        return $cache[$key];
    }

    // ---------------------------------------------------------------
    // Internal: raw cURL call to Anthropic /v1/messages
    // ---------------------------------------------------------------
    private function callClaude(string $userPrompt, int $maxTokens): array
    {
        $apiKey = $this->getSetting('anthropic_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException(
                'Anthropic API key not configured. Go to Settings → AI Settings and add your key.'
            );
        }

        $model   = $this->getSetting('anthropic_model') ?: 'claude-sonnet-4-5';
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '        . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT    => 120,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('cURL error calling Claude API: ' . $err);
        }
        if ($code !== 200) {
            throw new RuntimeException('Claude API returned HTTP ' . $code . ': ' . substr($raw, 0, 400));
        }

        $data         = json_decode($raw, true) ?? [];
        $content      = $data['content'][0]['text'] ?? '';
        $inputTokens  = (int)($data['usage']['input_tokens']  ?? 0);
        $outputTokens = (int)($data['usage']['output_tokens'] ?? 0);

        return [$content, $inputTokens, $outputTokens];
    }

    // ---------------------------------------------------------------
    // Strip markdown fences and decode JSON
    // ---------------------------------------------------------------
    private function parseJson(string $raw): ?array
    {
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try extracting a JSON object from prose
        if (preg_match('/\{[\s\S]+\}/s', $cleaned, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
