<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\ProgressSnapshot;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRuleFactory;

/*
 | Kilit motoru — veritabanı yok. Kurallar JSON'dan kuruluyor, ilerleme
 | bellekten geliyor; yol ekranının tüm kilit mantığı milisaniyelerde test edilir.
 */

function unlockContext(array $overrides = []): UnlockContext
{
    return new UnlockContext(
        nodeId: $overrides['nodeId'] ?? 2,
        unitId: $overrides['unitId'] ?? 1,
        previousNodeId: $overrides['previousNodeId'] ?? 1,
        previousUnitId: $overrides['previousUnitId'] ?? null,
        unitStudyNodeIds: $overrides['unitStudyNodeIds'] ?? [1, 2, 3],
        progress: $overrides['progress'] ?? ProgressSnapshot::empty(),
        hasPremium: $overrides['hasPremium'] ?? false,
    );
}

function progress(array $completedNodes = [], array $accuracy = [], array $completedUnits = []): ProgressSnapshot
{
    return new ProgressSnapshot(
        array_fill_keys($completedNodes, true),
        $accuracy,
        array_fill_keys($completedUnits, true),
    );
}

it('kuralsız node her zaman açıktır', function (): void {
    $verdict = (new UnlockRuleFactory)->make(null)->evaluate(unlockContext());

    expect($verdict->satisfied)->toBeTrue()
        ->and($verdict->reason)->toBeNull();
});

it('önceki node bitmeden açılmaz', function (): void {
    $rule = (new UnlockRuleFactory)->make(['type' => 'previous_node_completed']);

    expect($rule->evaluate(unlockContext())->reason)
        ->toBe(LockReason::PreviousNodeIncomplete);

    expect($rule->evaluate(unlockContext(['progress' => progress([1])]))->satisfied)
        ->toBeTrue();
});

it('ünite challenge tüm çalışma node\'larını bekler', function (): void {
    $rule = (new UnlockRuleFactory)->make([
        'type' => 'all_of',
        'rules' => [
            ['type' => 'unit_study_nodes_completed'],
            ['type' => 'min_accuracy', 'value' => 80],
        ],
    ]);

    // İki node bitti, üçü eksik → kilitli, sebep UNIT_INCOMPLETE.
    expect($rule->evaluate(unlockContext(['progress' => progress([1, 2])]))->reason)
        ->toBe(LockReason::UnitIncomplete);

    // Hepsi bitti ve doğruluk yeterli → açık.
    $done = progress([1, 2, 3], [1 => 100]);
    expect($rule->evaluate(unlockContext(['previousNodeId' => 1, 'progress' => $done]))->satisfied)
        ->toBeTrue();
});

it('doğruluk eşiğinin altında kilitler', function (): void {
    $rule = (new UnlockRuleFactory)->make(['type' => 'min_accuracy', 'value' => 80]);

    $weak = progress([1], [1 => 60]);
    expect($rule->evaluate(unlockContext(['progress' => $weak]))->reason)
        ->toBe(LockReason::AccuracyTooLow);

    $strong = progress([1], [1 => 80]);
    expect($rule->evaluate(unlockContext(['progress' => $strong]))->satisfied)->toBeTrue();
});

it('doğruluk kuralı hiç oynanmamış node için ihlal saymaz', function (): void {
    // Sıra kuralı PreviousNodeCompleted'ın işidir; MinAccuracy sadece
    // doğrulukla ilgilenir (SRP). İkisi AllOf ile birlikte kullanılır.
    $rule = (new UnlockRuleFactory)->make(['type' => 'min_accuracy', 'value' => 80]);

    expect($rule->evaluate(unlockContext())->satisfied)->toBeTrue();
});

it('premium kuralı sadece premium olmayanı kilitler', function (): void {
    $rule = (new UnlockRuleFactory)->make(['type' => 'requires_premium']);

    expect($rule->evaluate(unlockContext())->reason)->toBe(LockReason::PremiumRequired)
        ->and($rule->evaluate(unlockContext(['hasPremium' => true]))->satisfied)->toBeTrue();
});

it('all_of ilk ihlal edilen kuralın sebebini döner', function (): void {
    // Kullanıcıya gösterilecek mesaj, "neden kilitli" sorusunun en yakın cevabı olmalı.
    $rule = (new UnlockRuleFactory)->make([
        'type' => 'all_of',
        'rules' => [
            ['type' => 'previous_node_completed'],
            ['type' => 'requires_premium'],
        ],
    ]);

    expect($rule->evaluate(unlockContext())->reason)
        ->toBe(LockReason::PreviousNodeIncomplete);
});

it('any_of tek kural yetince açar', function (): void {
    $rule = (new UnlockRuleFactory)->make([
        'type' => 'any_of',
        'rules' => [
            ['type' => 'previous_node_completed'],
            ['type' => 'requires_premium'],
        ],
    ]);

    expect($rule->evaluate(unlockContext(['hasPremium' => true]))->satisfied)->toBeTrue()
        ->and($rule->evaluate(unlockContext())->satisfied)->toBeFalse();
});

it('önceki ünite bitmeden sonraki ünite açılmaz', function (): void {
    $rule = (new UnlockRuleFactory)->make(['type' => 'previous_unit_completed']);

    expect($rule->evaluate(unlockContext(['previousUnitId' => 5]))->reason)
        ->toBe(LockReason::PreviousUnitIncomplete);

    expect($rule->evaluate(unlockContext(['previousUnitId' => 5, 'progress' => progress(completedUnits: [5])]))->satisfied)
        ->toBeTrue();
});

it('bilinmeyen kural tipi sessizce açmaz, hata verir', function (): void {
    // Yazım hatası yüzünden premium içeriğin herkese açılmasındansa
    // yayın anında patlaması yeğdir.
    (new UnlockRuleFactory)->make(['type' => 'her_zaman_ac_lutfen']);
})->throws(InvalidArgumentException::class, 'Bilinmeyen unlock_rule tipi');

it('composite kural boş alt kural listesini reddeder', function (): void {
    (new UnlockRuleFactory)->make(['type' => 'all_of', 'rules' => []]);
})->throws(InvalidArgumentException::class);
