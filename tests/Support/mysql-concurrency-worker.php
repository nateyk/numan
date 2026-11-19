<?php

use App\Http\Controllers\Freight\FreightInterestAcceptanceController;
use App\Http\Controllers\Freight\FreightPostLifecycleController;
use App\Http\Controllers\Marketplace\CheckoutController;
use App\Models\FreightPost;
use App\Models\MarketplacePaymentTransaction;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * CLI worker for the dedicated MySQL concurrency tests.
 *
 * Each invocation boots Laravel in its own process with its own database
 * connection. No web endpoint, production command, or test-only application
 * route is added.
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $mode = $argv[1] ?? '';
    $id = (int) ($argv[2] ?? 0);
    $actorId = (int) ($argv[3] ?? 0);
    $readyFile = $argv[4] ?? '';

    if ($id < 1 || $readyFile === '' || ! in_array($mode, ['accept', 'close', 'fulfill', 'checkout'], true)) {
        throw new InvalidArgumentException('Invalid MySQL test worker arguments.');
    }

    if (in_array($mode, ['accept', 'close', 'checkout'], true)) {
        Auth::loginUsingId($actorId);
    }

    if (file_put_contents($readyFile, (string) getmypid()) === false) {
        throw new RuntimeException('Could not signal worker readiness.');
    }

    if ($mode === 'accept') {
        $request = Request::create('/interests/'.$id.'/accept', 'POST');
        $request->setUserResolver(fn () => Auth::user());

        app(FreightInterestAcceptanceController::class)
            ->accept($request, $id);
    } elseif ($mode === 'close') {
        $post = FreightPost::findOrFail($id);
        $request = Request::create('/my-freight/'.$post->ulid.'/close', 'POST', [
            'closure_reason' => 'cancelled',
        ]);
        $request->setUserResolver(fn () => Auth::user());

        app(FreightPostLifecycleController::class)
            ->close($request, $post->ulid);
    } elseif ($mode === 'checkout') {
        // The race is against the real CheckoutController and database lock,
        // never against a live payment provider.
        Http::fake([
            'api.chapa.co/v1/transaction/initialize' => Http::response([
                'status' => 'success',
                'data' => ['checkout_url' => 'https://checkout.chapa.co/synthetic-concurrent'],
            ], 200),
        ]);
        $request = Request::create('/marketplace/checkout', 'POST', [
            'plan' => 'standard',
            'billing_interval' => 'monthly',
        ], server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(CheckoutController::class)->store($request);
        if (! in_array($response->getStatusCode(), [200, 409], true)) {
            throw new RuntimeException('Unexpected checkout response: '.$response->getStatusCode());
        }
    } else {
        $transaction = MarketplacePaymentTransaction::findOrFail($id);

        app(MarketplaceFulfillmentService::class)->fulfill(
            $transaction,
            [
                'status' => 'success',
                'data' => [
                    'status' => 'paid',
                    'currency' => $transaction->currency,
                    'amount' => (string) $transaction->amount_etb,
                    'tx_ref' => $transaction->tx_ref,
                ],
            ],
        );
    }

    echo 'ok';
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);

    exit(1);
}
