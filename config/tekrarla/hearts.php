<?php

declare(strict_types=1);

/*
 | Can sistemi. Tüm sayılar burada — kod değişmeden ayarlanabilir, A/B testine açık.
 |
 | Denge notu: reklamla can sınırsız olursa premium'un değeri sıfırlanır.
 | Günlük 4 limiti ve pratik turunun 1 can vermesi bilinçlidir; dönüşüm
 | hunisi bunun üzerine kuruludur.
 */

return [
    'max' => (int) env('HEARTS_MAX', 5),

    // 5 can ≈ 90 dakika. Duolingo'nun eşiğine yakın tutuldu.
    'regen_interval_minutes' => (int) env('HEARTS_REGEN_MINUTES', 18),

    'ad_reward_daily_limit' => (int) env('HEARTS_AD_LIMIT', 4),

    'practice_refill_enabled' => true,
];
