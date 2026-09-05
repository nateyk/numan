<?php

return [
    'currency' => env('MARKETPLACE_CURRENCY', 'ETB'),

    'trial_days' => env('MARKETPLACE_TRIAL_DAYS', 14),

    'plans' => [
        'weekly' => [
            'name' => 'Weekly',
            'slug' => 'weekly',
            'description' => '7-day pass — perfect for trial',
            'price_weekly_etb' => 349,
            'price_monthly_etb' => 0,
            'price_yearly_etb' => 0,
            'limits' => [
                'active_freight_posts' => 10,
                'monthly_posts' => 20,
                'interests_per_month' => 30,
                'team_members' => 5,
                'workspaces' => 2,
                'api_keys' => 5,
                'webhooks' => 2,
            ],
            'features' => ['10 Active Posts', '20 Posts / 7 days', '30 Interests / week', '5 Team Members', '7-Day Validity', 'Chapa ETB'],
            'faq' => [
                ['q' => 'How long is Weekly valid?', 'a' => '7 days from payment verification. Expires automatically, no renewal.'],
                ['q' => 'Can I upgrade from Weekly?', 'a' => 'Yes — pay Pro/Business anytime, weekly will be canceled and new period starts.'],
            ],
        ],
        'free' => [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'Perfect for getting started',
            'price_weekly_etb' => 0,
            'price_monthly_etb' => 0,
            'price_yearly_etb' => 0,
            'limits' => [
                'active_freight_posts' => 3,
                'monthly_posts' => 5,
                'interests_per_month' => 5,
                'team_members' => 2,
                'workspaces' => 1,
                'api_keys' => 2,
                'webhooks' => 1,
            ],
            'features' => ['3 Active Posts', '5 Posts / month', '5 Interests / month', 'Community Support'],
            'faq' => [
                ['q' => 'What counts as active post?', 'a' => 'Posts with status Active that haven’t expired. Drafts don’t count.'],
                ['q' => 'How is ETB charged?', 'a' => 'Via Chapa (Telebirr, cards). You’ll see ETB checkout and receipt.'],
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For growing teams',
            'price_weekly_etb' => 0,
            'price_monthly_etb' => 999,
            'price_yearly_etb' => 9990, // ~17% off
            'popular' => true,
            'limits' => [
                'active_freight_posts' => 25,
                'monthly_posts' => 50,
                'interests_per_month' => 100,
                'team_members' => 10,
                'workspaces' => 5,
                'api_keys' => 10,
                'webhooks' => 5,
            ],
            'features' => ['25 Active Posts', '50 Posts / month', '100 Interests / month', '10 Team Members', 'Priority Support'],
            'faq' => [
                ['q' => 'Can I cancel anytime?', 'a' => 'Yes, cancels at period end. No pro-rata refund in pilot.'],
                ['q' => 'Yearly discount?', 'a' => 'Yearly is ~17% off monthly — paid once via Chapa.'],
            ],
        ],
        'business' => [
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'For larger organizations',
            'price_weekly_etb' => 0,
            'price_monthly_etb' => 2499,
            'price_yearly_etb' => 24990,
            'limits' => [
                'active_freight_posts' => -1,
                'monthly_posts' => -1,
                'interests_per_month' => -1,
                'team_members' => -1,
                'workspaces' => -1,
                'api_keys' => -1,
                'webhooks' => -1,
            ],
            'features' => ['Unlimited Posts', 'Unlimited Interests', 'Unlimited Members', 'SSO (Coming Soon)', 'Dedicated Support'],
            'faq' => [
                ['q' => 'Unlimited means?', 'a' => '-1 in limits means no cap. Fair use applies.'],
            ],
        ],
    ],
];
