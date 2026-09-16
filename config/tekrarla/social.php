<?php

declare(strict_types=1);

/*
 | Sosyal giriş.
 |
 | audience listeleri BOŞ ise o sağlayıcı kapalıdır. "Yapılandırılmamışsa
 | herkesi kabul et" davranışı, başka bir uygulamanın token'ıyla hesaplarımıza
 | girilmesi demek olurdu.
 */

return [
    'apple' => [
        'jwks_url' => 'https://appleid.apple.com/auth/keys',
        'issuer' => 'https://appleid.apple.com',
        // iOS bundle id + (varsa) Android/web için Services ID.
        'audiences' => array_values(array_filter(explode(',', (string) env('APPLE_AUDIENCES', '')))),
    ],

    'google' => [
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        'issuer' => 'https://accounts.google.com',
        // iOS, Android ve web istemcilerinin ayrı client id'leri olur; hepsi listelenir.
        'audiences' => array_values(array_filter(explode(',', (string) env('GOOGLE_AUDIENCES', '')))),
    ],
];
