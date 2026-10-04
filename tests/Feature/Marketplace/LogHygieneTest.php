<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nothing this application writes to a log file may hold contact data.
 *
 * The rule is ids and reasons only. A phone number or an email address in a log
 * file is personal data that outlives the request that produced it, gets copied
 * into error reports by whatever monitors the log, and is generally retained far
 * longer than anyone agreed to. A transaction reference is fine: it is an id.
 *
 * These tests read what was actually logged rather than trusting the code,
 * because the leak that matters is what lands on disk.
 */
uses(RefreshDatabase::class);

/**
 * Start recording every log line for the rest of the test.
 *
 * @return Closure(): array<int, string>
 */
function recordingLog(): Closure
{
    $lines = [];

    Log::listen(function (MessageLogged $event) use (&$lines) {
        $lines[] = $event->message.' '.json_encode($event->context);
    });

    // By reference, not an arrow function: an arrow fn captures by value and
    // would hand back the empty array it closed over, quietly passing every
    // assertion below for the wrong reason.
    return function () use (&$lines) {
        return $lines;
    };
}

/**
 * Contact data that must never appear in a log line.
 *
 * @return array<string, string>
 */
function forbiddenLogValues(): array
{
    return [
        'email' => 'payer.contact@example.com',
        'E.164 phone' => '+251911223344',
        'local phone' => '0911223344',
        'first name' => 'Abebe',
        'last name' => 'Kebede',
    ];
}

/**
 * Post a Chapa webhook with a valid signature.
 *
 * @param  array<string, mixed>  $payload
 */
function postChapaWebhook(array $payload, ?string $signature = null)
{
    $body = json_encode($payload);

    $signature ??= hash_hmac('sha256', $body, (string) config('chapa.webhookSecret'));

    return test()->postJson('/webhooks/chapa', json_decode($body, true), [
        'x-chapa-signature' => $signature,
    ]);
}

/**
 * The contact fields Chapa puts on every transaction and webhook.
 *
 * @return array<string, mixed>
 */
function chapaContactPayload(): array
{
    return [
        'status' => 'success',
        'event' => 'payment.success',
        'tx_ref' => 'nf-some-reference',
        'first_name' => 'Abebe',
        'last_name' => 'Kebede',
        'email' => 'payer.contact@example.com',
        'phone_number' => '+251911223344',
    ];
}

/**
 * Assert that no contact data reached the log.
 *
 * Written with an explicit str_contains rather than toContain because Pest reads
 * a second string argument to toContain as another needle and asks whether the
 * haystack contains all of them -- which, negated, passes on any log at all.
 *
 * @param  array<int, string>  $lines
 */
function assertNothingContactWasLogged(array $lines): void
{
    $blob = implode("\n", $lines);

    foreach (forbiddenLogValues() as $label => $value) {
        expect(str_contains($blob, $value))->toBeFalse("logged the {$label}");
    }
}

it('does not log the webhook payload when the signature is invalid', function () {
    $logged = recordingLog();

    postChapaWebhook(chapaContactPayload(), 'wrong-signature')
        ->assertStatus(403);

    assertNothingContactWasLogged($logged());
});

it('does not log the webhook payload when tx_ref is missing', function () {
    $logged = recordingLog();

    $payload = chapaContactPayload();
    unset($payload['tx_ref']);

    postChapaWebhook($payload)->assertStatus(422);

    assertNothingContactWasLogged($logged());
});

it('does not log the verify response when verification does not succeed', function () {
    $logged = recordingLog();

    // The verify response is the payer's name, email and phone number as Chapa
    // holds them. This branch runs whenever a webhook arrives for a transaction
    // that is not actually paid.
    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'message' => 'Payment details',
            'data' => [
                'status' => 'failed',
                'first_name' => 'Abebe',
                'last_name' => 'Kebede',
                'email' => 'payer.contact@example.com',
                'phone_number' => '+251911223344',
            ],
        ], 200),
    ]);

    postChapaWebhook(chapaContactPayload());

    assertNothingContactWasLogged($logged());
});

it('still logs the tx_ref so a failed payment can be traced', function () {
    $logged = recordingLog();

    Http::fake([
        'api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'failed'],
        ], 200),
    ]);

    postChapaWebhook(chapaContactPayload());

    // Stripping the payload must not strip the reason. An id is not personal
    // data and it is the whole reason the line is there.
    expect(implode("\n", $logged()))->toContain('nf-some-reference');
});

it('the locale middleware writes nothing to the log at all', function () {
    $logged = recordingLog();

    $request = HttpRequest::create('/');
    $request->setLaravelSession(app('session')->driver());

    (new SetLocale)->handle($request, fn () => new Response);

    // It used to write Session::all() on every guest request, which put the
    // CSRF token and the whole _old_input bag -- the name and email just typed
    // into the registration form -- into the log file on every page load.
    expect($logged())->toBe([]);
});

it('a guest request writes no session contents to the log', function () {
    $logged = recordingLog();

    // _old_input is where a failed form puts what the person typed.
    session(['_old_input' => ['name' => 'Abebe Kebede', 'email' => 'payer.contact@example.com']]);

    $this->get('/');

    expect(implode("\n", $logged()))
        ->not->toContain('payer.contact@example.com')
        ->not->toContain('Abebe Kebede')
        ->not->toContain('_old_input');
});

it('no log line anywhere in app/ logs a payload, a request or contact data', function () {
    // A standing check rather than a one-off. Every leak found so far was a
    // "just add the payload while debugging" edit, and a one-off test would not
    // catch the next one.
    $offenders = [];

    foreach (phpFilesUnder(base_path('app')) as $file) {
        $source = (string) file_get_contents($file);

        // Comments are stripped first. A "Send welcome email" TODO next to a log
        // call is not a leak, and flagging one would teach everyone to ignore
        // this test.
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        $source = preg_replace('~//[^\n]*~', '', (string) $source);

        foreach (logCallsIn($source) as $call) {
            foreach (logCallLeaks($call) as $reason) {
                $offenders[] = str_replace(base_path().'/', '', $file)
                    .': '.$reason.' in ->'.trim(substr($call, 0, 70));
            }
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * Every logger call in a source file, with its full argument list.
 *
 * Log::create and friends are the Log model rather than the logger and are not
 * matched, which is why only the level verbs are in the pattern.
 *
 * @return array<int, string>
 */
function logCallsIn(string $source): array
{
    preg_match_all(
        '/(?:Log::(?:debug|info|notice|warning|error|critical|alert|emergency)|logger)\s*\(/',
        $source,
        $matches,
        PREG_OFFSET_CAPTURE
    );

    $calls = [];

    foreach ($matches[0] as [$needle, $offset]) {
        $arguments = balancedArgumentList($source, $offset + strlen($needle) - 1);

        if ($arguments !== null) {
            $calls[] = $arguments;
        }
    }

    return $calls;
}

/**
 * The text between the parenthesis at $open and its match.
 *
 * Returns null when the call is truncated or the source does not parse, rather
 * than guessing -- a wrong guess here would silently pass a leak.
 */
function balancedArgumentList(string $source, int $open): ?string
{
    $depth = 0;
    $length = strlen($source);

    for ($i = $open; $i < $length; $i++) {
        $char = $source[$i];

        if ($char === '(' || $char === '[') {
            $depth++;
        } elseif ($char === ')' || $char === ']') {
            $depth--;

            if ($depth === 0) {
                return substr($source, $open + 1, $i - $open - 1);
            }
        }
    }

    return null;
}

/**
 * Why a single log call is a leak, or an empty array if it is clean.
 *
 * Deliberately narrow. A context array of ids, counts, statuses and reasons is
 * exactly what should be logged, and this test must not become so noisy that it
 * gets disabled.
 *
 * @return array<int, string>
 */
function logCallLeaks(string $call): array
{
    $reasons = [];

    // Dumping the raw request, the whole session, or a provider's response.
    $dumps = [
        'getContent()' => 'logs the raw request body',
        'Session::all()' => 'logs the whole session',
        '->all()' => 'logs a whole array',
        '_old_input' => 'logs previously submitted form input',
        '->input(' => 'logs request input',
        'json()' => 'logs a provider response body',
    ];

    foreach ($dumps as $needle => $reason) {
        if (str_contains($call, $needle)) {
            $reasons[] = $reason;
        }
    }

    // A whole provider payload or verify response passed as a value. Reading one
    // narrow key out of it -- $verified['status'] -- is fine and is what the
    // Chapa controller now does.
    if (preg_match('/=>\s*\$(payload|verified|body|response|json)\s*[,)]/', $call)) {
        $reasons[] = 'logs a whole provider payload';
    }

    // A context key that names contact data. The value is logged whatever it
    // happens to hold today.
    if (preg_match('/[\'"][a-z_]*(phone|email|contact|msisdn|customer_name)[a-z_]*[\'"]\s*=>/i', $call)) {
        $reasons[] = 'logs a contact data field';
    }

    return array_values(array_unique($reasons));
}

/**
 * Every PHP file under a directory, recursively.
 *
 * @return array<int, string>
 */
function phpFilesUnder(string $directory): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
