<?php

namespace App\Console\Commands;

use App\Models\MarketplacePaymentReview;
use App\Models\MarketplacePaymentTransaction;
use App\Services\ChapaConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Configuration-only release gate. Never contacts a payment provider, reveals
 * credentials, triggers a notification, issues a refund or changes a record.
 */
class CheckMarketplaceReleaseReadiness extends Command
{
    protected $signature = 'marketplace:release-check
                            {--strict : Exit nonzero when required infrastructure checks are missing}';

    protected $description = 'Read-only payment, scheduler and privacy release preflight';

    public function handle(ChapaConfiguration $chapa): int
    {
        $issues = 0;

        $this->line('Numan Freight marketplace readiness — read-only configuration and backlog report');

        $baseUrl = rtrim((string) config('chapa.baseUrl'), '/');

        $this->line('Chapa mode: '.$chapa->mode());
        $this->line('Chapa API: '.($baseUrl === 'https://api.chapa.co/v1' ? 'v1 configured' : 'review v1 base URL'));

        if ($baseUrl !== 'https://api.chapa.co/v1') {
            $issues++;
        }

        foreach (['secretKey' => 'Chapa private key', 'webhookSecret' => 'Chapa webhook secret'] as $key => $label) {
            $configured = $chapa->configured($key);
            $this->line($label.': '.($configured ? 'configured (value hidden)' : 'MISSING'));

            if (! $configured) {
                $issues++;
            }
        }

        $appUrl = (string) config('app.url');
        $https = str_starts_with($appUrl, 'https://');
        $this->line('Public callback origin: '.($https ? 'HTTPS configured' : 'HTTPS NOT configured'));

        if (! $https) {
            $issues++;
        }

        $mailer = (string) config('mail.default');
        $queue = (string) config('queue.default');
        $this->line('Mail transport: '.$mailer.($mailer === 'log' || $mailer === 'array' ? ' (not for customer delivery)' : ''));
        $this->line('Queue transport: '.$queue.($queue === 'sync' ? ' (no durable background worker)' : ''));

        if (in_array($mailer, ['log', 'array'], true) || $queue === 'sync') {
            $issues++;
        }

        $this->line('Expiry sweep scheduled: '.(config('marketplace.expiry_enabled') ? 'enabled' : 'disabled'));
        $this->line('Reminder delivery scheduled: '.(config('marketplace.reminders_enabled') ? 'enabled' : 'disabled'));
        $this->line('These flags only control schedules; request-time freight access and Chapa reconciliation apply separately.');

        try {
            $tables = ['marketplace_payment_transactions', 'marketplace_payment_receipts', 'marketplace_subscriptions', 'chapa_webhook_events', 'chapa_webhook_deliveries', 'marketplace_payment_reviews'];
            $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

            if ($missing !== []) {
                $this->warn('Missing marketplace tables: '.implode(', ', $missing));
                $issues++;
            } else {
                $this->info('Marketplace tables: present');

                $oldPending = MarketplacePaymentTransaction::query()
                    ->where('provider', MarketplacePaymentTransaction::PROVIDER_CHAPA)
                    ->whereIn('status', ['pending', 'initialized'])
                    ->where('created_at', '<=', now()->subDays(2))
                    ->count();

                $openReviews = MarketplacePaymentReview::query()
                    ->whereIn('status', ['open', 'investigating', 'submitted_to_chapa'])
                    ->count();

                $this->line('Outstanding Chapa attempts older than 48 hours: '.$oldPending);
                $this->line('Open refund/dispute review cases: '.$openReviews);

                if ($oldPending > 0 || $openReviews > 0) {
                    $this->warn('Review outstanding payments/disputes before a financial release; no records were changed.');
                    $issues++;
                }
            }
        } catch (Throwable $e) {
            // Connection errors can carry DB credentials or table names; never
            // leak exception messages to a routine production preflight report.
            $this->error('Database readiness unavailable; verify connection and migrations.');
            $issues++;
        }

        $this->warn('NOT VERIFIED HERE: actual Chapa Test Mode checkout, signed webhook delivery, cron operation, queue worker supervision, mail receipt or backup restoration.');
        $this->line('For genuine Test Mode acceptance, complete two hosted checkouts and run chapa:accept-renewal using their references.');

        $this->info($issues === 0 ? 'Configuration preflight has no detected blockers.' : 'Configuration preflight found '.$issues.' issue group(s).');

        return $issues > 0 && $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
