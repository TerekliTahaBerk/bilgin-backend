<?php

declare(strict_types=1);

return [
    'cohort_size' => (int) env('LEAGUE_COHORT_SIZE', 30),
    'promotion_count' => (int) env('LEAGUE_PROMOTION', 5),
    'demotion_count' => (int) env('LEAGUE_DEMOTION', 5),

    // Hafta Pazartesi 00:00 Europe/Istanbul'da döner.
    'timezone' => 'Europe/Istanbul',
];
