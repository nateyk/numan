<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChapaService
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $baseUrl = null,
        protected ?string $webhookSecret = null,
    ) {
        $this->secretKey = $secretKey ?? config('chapa.secretKey');
        $this->baseUrl = $baseUrl ?? config('chapa.baseUrl', 'https://api.chapa.co/v1');
        $this->webhookSecret = $webhookSecret ?? config('chapa.webhookSecret');
    }

    public function generateReference(?string $prefix = null): string
    {
        if ($prefix) {
            // Chapa rejects refs over 50 chars and anything outside
            // [A-Za-z0-9_-]. uniqid() alone is 13 hex chars with no dot,
            // unlike uniqid(time, true). The unique tail is never trimmed.
            $tail = uniqid();
            $head = substr($prefix, 0, max(0, 50 - strlen($tail) - 1));

            return $head.'_'.$tail;
        }

        $app = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) config('app.name', 'NumanFreight'));

        $tail = uniqid();
        $head = substr($app.'_chapa', 0, max(0, 50 - strlen($tail) - 1));

        return $head.'_'.$tail;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function initializePayment(array $data): array
    {
        $response = Http::withToken((string) $this->secretKey)
            ->connectTimeout(5)
            ->timeout(15)
            ->acceptJson()
            ->post(rtrim((string) $this->baseUrl, '/').'/transaction/initialize', $data);

        return $response->json() ?? ['status' => 'failed', 'message' => 'No response'];
    }

    /**
     * @return array<string,mixed>
     */
    public function verifyTransaction(string $txRef): array
    {
        $response = Http::withToken((string) $this->secretKey)
            ->connectTimeout(5)
            ->timeout(15)
            ->acceptJson()
            ->get(rtrim((string) $this->baseUrl, '/').'/transaction/verify/'.$txRef);

        return $response->json() ?? ['status' => 'failed'];
    }

    /**
     * All Chapa fulfillment paths must require a complete provider-verification
     * response. A missing amount/currency is not evidence of payment.
     */
    public function verifiedPaymentMatches(array $verified, MarketplacePaymentTransaction $transaction): bool
    {
        $data = $verified['data'] ?? null;

        if (! is_array($data)
            || ($verified['status'] ?? null) !== 'success'
            || ! in_array($data['status'] ?? null, ['success', 'paid'], true)
            || ($data['currency'] ?? null) !== $transaction->currency) {
            return false;
        }

        if (isset($data['tx_ref']) && (string) $data['tx_ref'] !== $transaction->tx_ref) {
            return false;
        }

        $amount = $data['amount'] ?? $data['amount_etb'] ?? null;
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return false;
        }

        $received = $this->canonicalAmount((string) $amount);
        $expected = $this->canonicalAmount((string) $transaction->amount_etb);

        return $received !== null && $expected !== null && $received === $expected;
    }

    /**
     * Compare money as decimal strings, without float equality or rounding.
     */
    private function canonicalAmount(string $amount): ?string
    {
        if (! preg_match('/^\\d+(?:\\.\\d{1,2})?$/D', $amount)) {
            return null;
        }

        [$units, $cents] = array_pad(explode('.', $amount, 2), 2, '');

        return (ltrim($units, '0') ?: '0').'.'.str_pad($cents, 2, '0');
    }

    public function validateWebhook(Request $request): bool
    {
        $signature = $request->header('x-chapa-signature') ?? $request->header('chapa-signature');
        $expected = hash_hmac('sha256', $request->getContent(), (string) $this->webhookSecret);

        if (! $signature) {
            return false;
        }

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<string,mixed>
     */
    public function getBanks(): array
    {
        $response = Http::withToken((string) $this->secretKey)->acceptJson()->get(rtrim((string) $this->baseUrl, '/').'/banks');

        return $response->json() ?? [];
    }

    public function getTransactionIdFromCallback(Request $request): ?string
    {
        if ($request->has('trx_ref')) {
            return (string) $request->input('trx_ref');
        }

        $resp = $request->input('resp');
        if ($resp) {
            $decoded = is_string($resp) ? json_decode($resp, true) : $resp;
            if (isset($decoded['data']['id'])) {
                return (string) $decoded['data']['id'];
            }
        }

        return null;
    }
}
