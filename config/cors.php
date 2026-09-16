<?php

declare(strict_types=1);

/*
 | Panel (Next.js) ve mobil istemci API'den farklı kaynaklardan çağırıyor.
 |
 | Varsayılan '*' — ve bu kurulumda GÜVENLİ, çünkü kimlik doğrulama
 | Bearer token ile yapılıyor, cookie ile değil (supports_credentials
 | false). Tarayıcı token'ı kendiliğinden eklemediği için başka bir
 | sitenin JS'i kullanıcı adına istek atamaz; korumayı CORS değil
 | token'ın kendisi sağlıyor.
 |
 | TEK İSTİSNA: ileride Sanctum'un cookie tabanlı SPA moduna geçilirse
 | (supports_credentials true), '*' derhal gerçek alan adlarıyla
 | değiştirilmeli — o zaman tarayıcı oturum cookie'sini otomatik ekler
 | ve CSRF yüzeyi açılır.
 */

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ($origins = array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))) === [] ? ['*'] : $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Bearer token kullanıyoruz, cookie değil: kimlik bilgisi taşımaya gerek yok.
    'supports_credentials' => false,
];
