<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune old records daily at 03:00 UTC based on config/retention.php
Schedule::command('app:prune-old-records')->dailyAt('03:00')->withoutOverlapping();

// Send billing reminders daily at 09:00 UTC
Schedule::command('app:send-billing-reminders')->dailyAt('09:00')->withoutOverlapping();

// Send weekly workspace activity digests every Monday at 08:00 UTC
Schedule::command('app:send-weekly-digests')->weeklyOn(1, '08:00')->withoutOverlapping();

// Send plan usage alerts daily at 10:00 UTC
Schedule::command('app:send-plan-usage-alerts')->dailyAt('10:00')->withoutOverlapping();

// Send API key expiry alerts daily at 08:00 UTC
Schedule::command('app:send-api-key-expiry-alerts')->dailyAt('08:00')->withoutOverlapping();

// Expire freight posts past pickup window daily at 02:00 UTC
Schedule::command('freight:expire')->dailyAt('02:00')->withoutOverlapping();

// Reconcile pending Chapa payments every 5 minutes
Schedule::command('chapa:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Expire lapsed marketplace subscriptions and hide their excess listings.
//
// Hourly because entitlement is already enforced immediately on ends_at; this
// only maintains the status column and listing visibility.
//
// DISABLED BY DEFAULT. This command writes to production data, so it is only
// registered when MARKETPLACE_EXPIRY_ENABLED is truthy. Run it by hand with
// --dry-run first to see what it would do.
//
//   MARKETPLACE_EXPIRY_ENABLED=true php artisan marketplace:expire-subscriptions --dry-run
//   MARKETPLACE_EXPIRY_ENABLED=true php artisan marketplace:expire-subscriptions
if (config('marketplace.expiry_enabled')) {
    Schedule::command('marketplace:expire-subscriptions')->hourly()->withoutOverlapping();

    // Daily rather than hourly: a request is reviewed by a human, so a shorter
    // cadence would only re-examine the same queue more often.
    Schedule::command('marketplace:expire-manual-payments')->dailyAt('03:30')->withoutOverlapping();
}

// Marketplace renewal and expiry reminders.
//
// DISABLED BY DEFAULT, and gated on its own flag rather than on
// MARKETPLACE_EXPIRY_ENABLED. The two are independent decisions: a site can
// expire subscriptions without ever emailing anybody about it, and should be able
// to try reminders on a pilot before trusting the expiry sweep on live data.
//
// Hourly. The windows are day-granular but the moment a subscription enters one
// is not, and the reminder log is what makes re-asking cheap.
//
//   php artisan marketplace:send-reminders --dry-run
//   MARKETPLACE_REMINDERS_ENABLED=true php artisan marketplace:send-reminders
//
// --dry-run works with the flag off. The flag gates the scheduler registration
// only.
if (config('marketplace.reminders_enabled')) {
    Schedule::command('marketplace:send-reminders')->hourly()->withoutOverlapping();
}
