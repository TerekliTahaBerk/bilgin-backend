<?php

declare(strict_types=1);

use App\Modules\Hearts\Domain\HeartRegeneration;

/*
 | Can rejenerasyonu — cron yok, saf hesap. FrozenClock yerine doğrudan
 | tarih veriyoruz; bu sınıf zamanı kendi okumuyor.
 */

function regen(): HeartRegeneration
{
    return new HeartRegeneration(max: 5, intervalMinutes: 18);
}

it('süre dolmadan can vermez', function (): void {
    $result = regen()->apply(2, at('2026-09-14 10:00'), at('2026-09-14 10:17'));

    expect($result->hearts)->toBe(2)
        ->and($result->earned)->toBe(0);
});

it('bir aralık geçince bir can verir', function (): void {
    $result = regen()->apply(2, at('2026-09-14 10:00'), at('2026-09-14 10:18'));

    expect($result->hearts)->toBe(3)
        ->and($result->earned)->toBe(1);
});

it('kalan süreyi korur — sık uygulama açan cezalandırılmaz', function (): void {
    // 10:00'da 2 can, 10:25'te bakıyoruz: 1 can kazanılmış, 7 dakika birikmiş.
    // last_regen 10:18 olmalı, 10:25 DEĞİL.
    $result = regen()->apply(2, at('2026-09-14 10:00'), at('2026-09-14 10:25'));

    expect($result->hearts)->toBe(3)
        ->and($result->lastRegenAt->format('H:i'))->toBe('10:18');
});

it('birikmiş sürede birden fazla can verir', function (): void {
    $result = regen()->apply(1, at('2026-09-14 10:00'), at('2026-09-14 11:00'));

    // 60 dk / 18 dk = 3 can
    expect($result->hearts)->toBe(4)
        ->and($result->earned)->toBe(3);
});

it('üst sınırı aşmaz', function (): void {
    $result = regen()->apply(1, at('2026-09-14 10:00'), at('2026-09-15 10:00'));

    expect($result->hearts)->toBe(5)
        ->and($result->earned)->toBe(4);
});

it('dolu bakiyede sayacı şimdiden başlatır', function (): void {
    // Aksi hâlde biriken süre ilk yanlıştan sonra anında can olarak geri gelirdi.
    $result = regen()->apply(5, at('2026-09-14 08:00'), at('2026-09-14 12:00'));

    expect($result->hearts)->toBe(5)
        ->and($result->lastRegenAt->format('H:i'))->toBe('12:00');
});

it('saat geri alınırsa bakiyeyi bozmaz', function (): void {
    $result = regen()->apply(3, at('2026-09-14 12:00'), at('2026-09-14 10:00'));

    expect($result->hearts)->toBe(3)
        ->and($result->earned)->toBe(0);
});

it('sonraki canın ve doluluğun zamanını bildirir', function (): void {
    $r = regen();

    expect($r->nextHeartAt(3, at('2026-09-14 10:00'))?->format('H:i'))->toBe('10:18')
        ->and($r->fullAt(3, at('2026-09-14 10:00'))?->format('H:i'))->toBe('10:36')
        ->and($r->nextHeartAt(5, at('2026-09-14 10:00')))->toBeNull();
});
