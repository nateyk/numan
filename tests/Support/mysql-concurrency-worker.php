<?php

use App\Http\Controllers\Freight\FreightInterestAcceptanceController;
use App\Models\MarketplacePaymentTransaction;
use App\Services\MarketplaceFulfillmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    if ($id < 1 || $readyFile === '' || ! in_array($mode, ['accept', 'fulfill'], true)) {
        throw new InvalidArgumentException('Invalid MySQL test worker arguments.');
    }

    if ($mode === 'accept') {
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
