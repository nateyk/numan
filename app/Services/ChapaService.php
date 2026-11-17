<?php

namespace App\Services;

use App\Models\MarketplacePaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChapaService
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $baseUrl = null,
        protected ?string $webhookSecret = null,
    ) {
        $settings = app(ChapaConfiguration::class);
        $this->secretKey = $secretKey ?? $settings->value('secretKey');
        $this->baseUrl = $baseUrl ?? config('chapa.baseUrl', 'https://api.chapa.co/v1');
        $this->webhookSecret = $webhookSecret ?? $settings->value('webhookSecret');
    }

    public function generateReference(?string $prefix = null): string
    {
        if ($prefix) {
            // Chapa rejects refs over 50 chars and anything outside
            // [A-Za-z0-9_-]. An unpredictable random tail also prevents
            // enumeration of public server-callback references.
            $tail = Str::random(20);
            $head = substr($prefix, 0, max(0, 50 - strlen($tail) - 1));

            return $head.'_'.$tail;
        }

        $app = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) config('app.name', 'NumanFreight'));

        $tail = Str::random(20);
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

        // An HTTP 5xx does not prove Chapa rejected the transaction. Treat it
        // as indeterminate so checkout retains the ref for reconciliation.
        if ($response->serverError()) {
            $response->throw();
        }

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

        // A temporary provider outage is retryable; neither callbacks nor
        // webhooks may turn it into a terminal failed payment.
        if ($response->serverError()) {
            $response->throw();
        }

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

        // Do not activate a live subscription with a test-mode provider
        // payment (or vice versa). Older mock/legacy responses may omit
        // mode; when present it must exactly match the stored checkout.
        if (array_key_exists('mode', $data) && $data['mode'] !== $transaction->mode) {
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
        // Chapa v1 uses separate signature algorithms depending on the
        // header. The x-chapa-signature digest covers the raw request bytes;
        // chapa-signature authenticates the shared webhook secret itself.
        // A valid digest from either header is sufficient per Chapa docs.
        $secret = $this->webhookSecret;
        if (! is_string($secret) || trim($secret) === '') {
            return false;
        }

        $bodySignature = $request->header('x-chapa-signature');
        if (is_string($bodySignature) && hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $bodySignature,
        )) {
            return true;
        }

        $keySignature = $request->header('chapa-signature');

        return is_string($keySignature) && hash_equals(
            hash_hmac('sha256', $secret, $secret),
            $keySignature,
        );
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
