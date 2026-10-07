<?php

namespace App\Services;

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
            ->acceptJson()
            ->get(rtrim((string) $this->baseUrl, '/').'/transaction/verify/'.$txRef);

        return $response->json() ?? ['status' => 'failed'];
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
