<?php

declare(strict_types=1);

return [
    'revenuecat' => [
        // Tanımlı değilse webhook KAPALIDIR — doğrulayamadığımız isteği
        // kabul etmek, uca herkesin premium yazabilmesi demek.
        'webhook_secret' => env('REVENUECAT_WEBHOOK_SECRET', ''),
    ],

    'products' => [
        'monthly' => env('BILLING_PRODUCT_MONTHLY', 'tekrarla_premium_monthly'),
        'yearly' => env('BILLING_PRODUCT_YEARLY', 'tekrarla_premium_yearly'),
    ],

    // Yıllık planda deneme süresi — dönüşümü ciddi artıran klasik taktik.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 7),
];
