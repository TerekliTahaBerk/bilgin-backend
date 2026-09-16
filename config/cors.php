<?php

declare(strict_types=1);

/*
 | Panel (Next.js) API'den FARKLI bir alan adında çalışacak; tarayıcı
 | isteklerini CORS olmadan engeller.
 |
 | allowed_origins ASLA '*' olmamalı: kimlik doğrulamalı bir API'de her
 | kaynağa izin vermek, herhangi bir sitenin kullanıcının token'ıyla
 | istek atabilmesi demek. Alan adları ortam değişkeninden gelir.
 */

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Bearer token kullanıyoruz, cookie değil: kimlik bilgisi taşımaya gerek yok.
    'supports_credentials' => false,
];
