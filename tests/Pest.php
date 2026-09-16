<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 | Feature testleri gerçek Postgres'e karşı koşar. SQLite ile test etmek
 | jsonb sorguları ve kısmi indeksler nedeniyle yanlış güven verir —
 | production neyse test de o olmalı.
 |
 | Unit testleri veritabanına DOKUNMAZ: domain katmanı Eloquent tanımadığı
 | için grader, selector, kilit ve XP kuralları saniyeler içinde koşar.
 */

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
 | Test içinde kullanıcı değiştirirken guard'ı unutturur.
 |
 | Laravel'in RequestGuard'ı çözdüğü kullanıcıyı uygulama örneğinde önbelleğe
 | alır. Gerçek HTTP'de her istek taze bir örnek olduğu için sorun çıkmaz
 | (doğrulandı), ama testte aynı örnek paylaşıldığı için ikinci token ilk
 | kullanıcıyı çözer. Bu yardımcı olmadan "başka kullanıcı erişemez" testleri
 | yanlış yere YEŞİL geçer — yani asıl tehlike sessiz başarıdır.
 */
function actingWithToken(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

/**
 * Testlerde sabit bir UTC anı üretir.
 *
 * Burada duruyor çünkü birden fazla test dosyası kullanıyor; tek bir
 * dosyada tanımlanması, o dosya yüklenmeden koşan testlerin kırılmasına
 * yol açıyordu (tek dosya çalıştırmak sık yapılan bir şey).
 */
function at(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('UTC'));
}
