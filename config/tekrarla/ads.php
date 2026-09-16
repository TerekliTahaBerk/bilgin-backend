<?php

declare(strict_types=1);

/*
 | Reklam politikası.
 |
 | Denge notu: çok agresif reklam → düşük retention ve uninstall.
 | Çok az → düşük premium dönüşümü. Bu sayılar A/B testine açık tutulmalı,
 | o yüzden istemciye gömülmüyor; sunucu her açılışta söylüyor.
 */

return [
    'admob' => [
        'keys_url' => env('ADMOB_KEYS_URL', 'https://gstatic.com/admob/reward/verifier-keys.json'),
        // İmzası doğru olsa bile bu yaştan eski callback reddedilir.
        'max_callback_age_seconds' => 3600,
    ],

    'interstitial' => [
        'every_n_sessions' => (int) env('ADS_INTERSTITIAL_EVERY', 2),
        'min_interval_seconds' => (int) env('ADS_INTERSTITIAL_MIN_INTERVAL', 180),
        // Yeni kullanıcıyı reklamla karşılamak retention'ı en hızlı
        // düşüren şeylerden biri.
        'grace_days' => (int) env('ADS_INTERSTITIAL_GRACE_DAYS', 3),
    ],
];
