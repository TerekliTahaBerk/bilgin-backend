<?php

declare(strict_types=1);

use App\Modules\Gamification\Domain\Streak\StreakState;
use App\Modules\Gamification\Domain\Streak\StreakTracker;
use App\Modules\Gamification\Domain\Xp\LevelCurve;
use App\Modules\Gamification\Domain\Xp\SessionOutcome;
use App\Modules\Gamification\Domain\Xp\XpCalculator;

function calculator(): XpCalculator
{
    return new XpCalculator(
        perfectBonus: 20,
        firstCompletionBonus: 60,
        replayFactor: 0.5,
        belowThresholdFactor: 0.5,
    );
}

function outcome(array $overrides = []): SessionOutcome
{
    return new SessionOutcome(
        baseXp: $overrides['baseXp'] ?? 20,
        accuracy: $overrides['accuracy'] ?? 90,
        isPerfect: $overrides['isPerfect'] ?? false,
        isFirstCompletion: $overrides['isFirstCompletion'] ?? false,
        isReplay: $overrides['isReplay'] ?? false,
        meetsCompletionThreshold: $overrides['meetsThreshold'] ?? true,
    );
}

describe('XP hesabı', function (): void {
    it('taban XP ile başlar', function (): void {
        expect(calculator()->calculate(outcome())->total)->toBe(20);
    });

    it('kusursuz tamamlamayı ödüllendirir', function (): void {
        $award = calculator()->calculate(outcome(['isPerfect' => true]));

        expect($award->total)->toBe(40)
            ->and($award->breakdown)->toHaveKey('perfect');
    });

    it('ilk tamamlamada bonus verir', function (): void {
        expect(calculator()->calculate(outcome(['isFirstCompletion' => true]))->total)->toBe(80);
    });

    it('tekrar oynamada ilk tamamlama bonusu VERMEZ', function (): void {
        // Aynı node grind edilerek lig sıralaması yükseltilememeli.
        $award = calculator()->calculate(outcome(['isFirstCompletion' => true, 'isReplay' => true]));

        expect($award->breakdown)->not->toHaveKey('first_completion')
            ->and($award->total)->toBe(10);   // 20 × 0.5
    });

    it('eşiğin altında yarım XP verir — emek ödüllendirilir', function (): void {
        expect(calculator()->calculate(outcome(['meetsThreshold' => false]))->total)->toBe(10);
    });

    it('dökümü sonuç ekranı için saklar', function (): void {
        $award = calculator()->calculate(outcome(['isPerfect' => true, 'isFirstCompletion' => true]));

        expect($award->breakdown)->toHaveKeys(['base', 'perfect', 'first_completion'])
            ->and($award->total)->toBe(100);
    });
});

describe('level eğrisi', function (): void {
    $curve = new LevelCurve([0, 100, 250, 450, 700, 1000], 600);

    it('sıfır XP\'de level 1 verir', function () use ($curve): void {
        expect($curve->levelFor(0))->toBe(1);
    });

    it('eşikte level atlatır', function () use ($curve): void {
        expect($curve->levelFor(99))->toBe(1)
            ->and($curve->levelFor(100))->toBe(2)
            ->and($curve->levelFor(1000))->toBe(6);
    });

    it('tablo bittikten sonra doğrusal devam eder', function () use ($curve): void {
        expect($curve->levelFor(1600))->toBe(7)
            ->and($curve->levelFor(2200))->toBe(8);
    });

    it('sonraki level eşiğini bildirir', function () use ($curve): void {
        expect($curve->nextLevelXp(70))->toBe(100)
            ->and($curve->nextLevelXp(1000))->toBe(1600);
    });
});

describe('seri', function (): void {
    $tracker = new StreakTracker;

    it('ilk çalışmada 1 günle başlar', function () use ($tracker): void {
        $state = $tracker->extend(new StreakState(0, 0, null), at('2026-09-14 20:00'));

        expect($state->current)->toBe(1)
            ->and($state->extendedToday)->toBeTrue();
    });

    it('dün çalışıldıysa seriyi uzatır', function () use ($tracker): void {
        $state = $tracker->extend(new StreakState(11, 11, '2026-09-13'), at('2026-09-14 20:00'));

        expect($state->current)->toBe(12)
            ->and($state->milestone)->toBe(12);
    });

    it('aynı gün ikinci tur seriyi ilerletmez', function () use ($tracker): void {
        $state = $tracker->extend(new StreakState(5, 9, '2026-09-14'), at('2026-09-14 22:00'));

        expect($state->current)->toBe(5)
            ->and($state->extendedToday)->toBeFalse();
    });

    it('gün atlanınca seriyi sıfırdan başlatır', function () use ($tracker): void {
        $state = $tracker->extend(new StreakState(20, 20, '2026-09-10'), at('2026-09-14 20:00'));

        expect($state->current)->toBe(1)
            ->and($state->longest)->toBe(20);   // en uzun seri korunur
    });

    it('en uzun seriyi günceller', function () use ($tracker): void {
        $state = $tracker->extend(new StreakState(9, 9, '2026-09-13'), at('2026-09-14 20:00'));

        expect($state->longest)->toBe(10);
    });

    it('yerel gün sınırını kullanır', function () use ($tracker): void {
        // İstanbul'da 14 Eylül 02:00 — UTC'de 13 Eylül 23:00.
        // Yerel güne göre hesaplamasaydık seri haksız yere kırılırdı.
        $istanbulNow = new DateTimeImmutable('2026-09-14 02:00', new DateTimeZone('Europe/Istanbul'));
        $state = $tracker->extend(new StreakState(3, 3, '2026-09-13'), $istanbulNow);

        expect($state->current)->toBe(4);
    });

    it('serinin canlı olup olmadığını bildirir', function () use ($tracker): void {
        $now = at('2026-09-14 20:00');

        expect($tracker->isAlive(new StreakState(5, 5, '2026-09-13'), $now))->toBeTrue()
            ->and($tracker->isAlive(new StreakState(5, 5, '2026-09-12'), $now))->toBeFalse()
            ->and($tracker->isAlive(new StreakState(0, 0, null), $now))->toBeFalse();
    });
});
