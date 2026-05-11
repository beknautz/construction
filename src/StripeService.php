<?php
/**
 * StripeService.php
 * Pure cURL Stripe API client — no Composer SDK required.
 * Same pattern as ClaudeService from mediapurchasing.
 */
class StripeService
{
    private string $secretKey;
    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    // ---------------------------------------------------------------
    // Customer
    // ---------------------------------------------------------------
    public function createCustomer(string $email, string $name, array $meta = []): array
    {
        return $this->post('/customers', array_merge([
            'email' => $email,
            'name'  => $name,
        ], $meta ? ['metadata' => $meta] : []));
    }

    public function getCustomer(string $customerId): array
    {
        return $this->get('/customers/' . $customerId);
    }

    // ---------------------------------------------------------------
    // Subscriptions
    // ---------------------------------------------------------------
    public function createCheckoutSession(
        string $customerId,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array  $metadata = []
    ): array {
        return $this->post('/checkout/sessions', [
            'customer'             => $customerId,
            'mode'                 => 'subscription',
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
            'metadata'             => $metadata,
            'allow_promotion_codes'=> 'true',
        ]);
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->post('/billing_portal/sessions', [
            'customer'   => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->get('/subscriptions/' . $subscriptionId);
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): array
    {
        if ($immediately) {
            return $this->delete('/subscriptions/' . $subscriptionId);
        }
        return $this->post('/subscriptions/' . $subscriptionId, [
            'cancel_at_period_end' => 'true',
        ]);
    }

    // ---------------------------------------------------------------
    // Webhook signature verification
    // ---------------------------------------------------------------
    public function verifyWebhook(string $payload, string $sigHeader, string $webhookSecret): ?array
    {
        // Parse Stripe-Signature header
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[$k] = $v;
        }

        $timestamp = (int)($parts['t'] ?? 0);
        $signature = $parts['v1'] ?? '';

        // Replay attack: reject if older than 5 minutes
        if (abs(time() - $timestamp) > 300) return null;

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
        if (!hash_equals($expected, $signature)) return null;

        return json_decode($payload, true);
    }

    // ---------------------------------------------------------------
    // Prices (for plan listing)
    // ---------------------------------------------------------------
    public function getPrice(string $priceId): array
    {
        return $this->get('/prices/' . $priceId);
    }

    // ---------------------------------------------------------------
    // Internal HTTP helpers
    // ---------------------------------------------------------------
    private function post(string $path, array $data): array
    {
        return $this->request('POST', $path, $data);
    }

    private function get(string $path): array
    {
        return $this->request('GET', $path, []);
    }

    private function delete(string $path): array
    {
        return $this->request('DELETE', $path, []);
    }

    private function request(string $method, string $path, array $data): array
    {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildPostBody($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Stripe cURL error: ' . $err);
        }

        $decoded = json_decode($raw, true) ?? [];

        if ($code >= 400) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $code);
            throw new RuntimeException('Stripe API error: ' . $msg);
        }

        return $decoded;
    }

    // Stripe uses form-encoded bodies, including nested arrays
    private function buildPostBody(array $data, string $prefix = ''): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $parts[] = $this->buildPostBody($value, $fullKey);
            } else {
                $parts[] = urlencode($fullKey) . '=' . urlencode((string)$value);
            }
        }
        return implode('&', $parts);
    }
}
