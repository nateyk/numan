<?php

return [
    'currency' => env('MARKETPLACE_CURRENCY', 'ETB'),

    /*
    |--------------------------------------------------------------------------
    | Scheduled expiry
    |--------------------------------------------------------------------------
    |
    | marketplace:expire-subscriptions flips lapsed subscriptions to 'expired'
    | and hides the excess listings of workspaces whose paid plan ended. It
    | writes to real data, so it is NOT scheduled unless this is enabled.
    |
    | Enabling it registers the command with the scheduler (hourly). The command
    | itself always works when invoked by hand, so you can dry-run it first.
    |
    */

    'expiry_enabled' => env('MARKETPLACE_EXPIRY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Free plan limits
    |--------------------------------------------------------------------------
    |
    | Defaults for the implicit Free state (no paid subscription). These are
    | seeded into the `marketplace_settings` table and are editable by an
    | admin from the marketplace plans screen. The values here are only
    | fallbacks for when a setting row is missing.
    |
    | -1 means unlimited.
    |
    */

    'free_limits' => [
        'free_active_posts' => 1,
        'free_interests_per_week' => 3,
        'free_contact_reveals_per_week' => 3,
        'free_interest_window_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact reveal window
    |--------------------------------------------------------------------------
    |
    | The rolling window, in days, over which contact reveals are counted
    | against the quota. Seeded into marketplace_settings as
    | free_contact_reveal_window_days and editable by an admin; this is the
    | fallback for when the setting row is missing.
    |
    | Deliberately its own number, not interest_window_days. That one is how
    | long an interest stays open. Coupling them would mean changing how long an
    | interest lives silently changed how often somebody could look at a
    | contact.
    |
    */

    'free_contact_reveal_window_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Editable periods
    |--------------------------------------------------------------------------
    |
    | Admin-editable settings that are periods rather than quotas. They live
    | here so MarketplaceSettingsService knows to include them even though they
    | are not free_limits keys.
    |
    */

    'free_settings' => [
        'free_interest_window_days',
        'free_contact_reveal_window_days',
        'free_manual_payment_expiry_days',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual payment expiry
    |--------------------------------------------------------------------------
    |
    | How long an admin has to review a manual payment before it lapses. An
    | expired request grants nothing and is never fulfilled; the payer has to
    | start again. Deliberately generous, because a confirmation is a human
    | decision and some of them take a week.
    |
    */

    'free_manual_payment_expiry_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Paid plans
    |--------------------------------------------------------------------------
    |
    | One paid plan with three durations. The features are identical across
    | durations; only the price and the period length differ.
    |
    */

    'plans' => [
        'standard' => [
            'name' => 'Standard',
            'slug' => 'standard',
            'description' => 'Full access — unlimited posts, interests and contact reveals',
            'price_weekly_etb' => 149,
            'price_monthly_etb' => 449,
            'price_yearly_etb' => 3999,
            'popular' => true,
            'sort_order' => 1,
            'limits' => [
                'active_freight_posts' => -1,
                'interests_per_week' => -1,
                'contact_reveals_per_week' => -1,
                // Rolling window for the interest counter. Explicit rather than
                // inferred from which limit keys exist, so adding an
                // interests_per_month key can never change the window.
                'interest_window_days' => 7,
            ],
            'features' => [
                'Unlimited active freight posts',
                'Unlimited interests',
                'Unlimited contact reveals',
            ],
            'faq' => [
                [
                    'q' => 'How long is each plan valid?',
                    'a' => 'Weekly is 7 days, Monthly is 30 days, and Yearly is 365 days from the moment your payment is confirmed.',
                ],
                [
                    'q' => 'What happens when my plan expires?',
                    'a' => 'You fall back to the Free state. Your data stays exactly where it is and extra listings are kept.',
                ],
                [
                    'q' => 'Can I renew early?',
                    'a' => 'Yes. Renewing early extends your current end date, so you never lose days you already paid for.',
                ],
            ],
        ],
    ],
];
