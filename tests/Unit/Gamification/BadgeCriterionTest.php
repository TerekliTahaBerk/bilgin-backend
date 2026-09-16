<?php

declare(strict_types=1);

use App\Modules\Gamification\Domain\Badge\BadgeCriterionFactory;
use App\Modules\Gamification\Domain\Badge\BadgeSnapshot;

/*
 | Rozet kriterleri — veritabanı yok.
 |
 | Kilitli rozetlerde ilerleme de test ediliyor: tasarımda kilitli rozet
 | görünür durumda ve "73/100" göstermek gri bir ikondan çok daha motive edici.
 */

function criterion(array $definition)
{
    return (new BadgeCriterionFactory)->make($definition);
}

function snapshot(array $overrides = []): BadgeSnapshot
{
    return new BadgeSnapshot(
        totalXp: $overrides['totalXp'] ?? 0,
        level: $overrides['level'] ?? 1,
        currentStreak: $overrides['currentStreak'] ?? 0,
        longestStreak: $overrides['longestStreak'] ?? 0,
        totalSessions: $overrides['totalSessions'] ?? 0,
        perfectSessions: $overrides['perfectSessions'] ?? 0,
        totalCorrect: $overrides['totalCorrect'] ?? 0,
        completedUnits: $overrides['completedUnits'] ?? 0,
        completedNodes: $overrides['completedNodes'] ?? 0,
        maxCourseLevel: $overrides['maxCourseLevel'] ?? 0,
        strongTopics: $overrides['strongTopics'] ?? 0,
    );
}

it('eşiğe ulaşınca rozeti verir', function (): void {
    $rule = criterion(['type' => 'threshold', 'metric' => 'total_correct', 'target' => 100]);

    expect($rule->evaluate(snapshot(['totalCorrect' => 100]))->earned)->toBeTrue()
        ->and($rule->evaluate(snapshot(['totalCorrect' => 99]))->earned)->toBeFalse();
});

it('kilitli rozette ilerleme gösterir', function (): void {
    $rule = criterion(['type' => 'threshold', 'metric' => 'total_correct', 'target' => 100]);
    $result = $rule->evaluate(snapshot(['totalCorrect' => 73]));

    expect($result->current)->toBe(73)
        ->and($result->target)->toBe(100)
        ->and($result->percent())->toBe(73);
});

it('ilerleme hedefi aşamaz', function (): void {
    // 250 doğru yapan kullanıcıya "250/100" göstermek anlamsız.
    $rule = criterion(['type' => 'threshold', 'metric' => 'total_correct', 'target' => 100]);
    $result = $rule->evaluate(snapshot(['totalCorrect' => 250]));

    expect($result->current)->toBe(100)
        ->and($result->percent())->toBe(100);
});

it('en uzun seriye bakar, güncel seriye değil', function (): void {
    // Serisi kırılan kullanıcı kazandığı rozeti kaybetmemeli.
    $rule = criterion(['type' => 'threshold', 'metric' => 'longest_streak', 'target' => 7]);

    expect($rule->evaluate(snapshot(['longestStreak' => 9, 'currentStreak' => 1]))->earned)->toBeTrue();
});

describe('çok şartlı rozet', function (): void {
    $perfect = [
        'type' => 'all_of',
        'criteria' => [
            ['type' => 'threshold', 'metric' => 'perfect_sessions', 'target' => 10],
            ['type' => 'threshold', 'metric' => 'total_sessions', 'target' => 20],
        ],
    ];

    it('tüm şartlar sağlanınca verir', function () use ($perfect): void {
        expect(criterion($perfect)->evaluate(snapshot([
            'perfectSessions' => 10, 'totalSessions' => 20,
        ]))->earned)->toBeTrue();
    });

    it('tek şart eksikse vermez', function () use ($perfect): void {
        // İlk turunu kusursuz bitiren rozeti anında almamalı.
        expect(criterion($perfect)->evaluate(snapshot([
            'perfectSessions' => 10, 'totalSessions' => 12,
        ]))->earned)->toBeFalse();
    });

    it('ilerleme en geride kalan şartı gösterir', function () use ($perfect): void {
        // Rozetin ne kadar yakın olduğunu belirleyen darboğaz şart.
        $result = criterion($perfect)->evaluate(snapshot([
            'perfectSessions' => 9,    // %90
            'totalSessions' => 12,     // %60 ← darboğaz
        ]));

        expect($result->percent())->toBe(60);
    });
});

it('bilinmeyen kriter tipini reddeder', function (): void {
    criterion(['type' => 'her_zaman_ver']);
})->throws(InvalidArgumentException::class, 'Bilinmeyen rozet kriteri');

it('metriksiz eşik tanımını reddeder', function (): void {
    criterion(['type' => 'threshold', 'target' => 10]);
})->throws(InvalidArgumentException::class);
