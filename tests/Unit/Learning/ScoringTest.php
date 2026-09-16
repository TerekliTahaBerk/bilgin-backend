<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Blueprint\BlueprintItemSpec;
use App\Modules\Learning\Domain\Scoring\NetScoring;

/*
 | Net hesabı — deneme sonucunun tamamı buna bağlı ve öğrencinin ürüne
 | güveni bu sayının doğruluğuyla ölçülüyor.
 */

it('YKS cezasını uygular — 4 yanlış 1 doğru götürür', function (): void {
    $score = (new NetScoring(penaltyRatio: 0.25))->score(correct: 40, wrong: 8, total: 60);

    expect($score->net)->toBe(38.0)      // 40 − 2
        ->and($score->blank)->toBe(12);
});

it('LGS cezasını uygular — 3 yanlış 1 doğru götürür', function (): void {
    $score = (new NetScoring(penaltyRatio: 1 / 3))->score(correct: 30, wrong: 9, total: 40);

    expect($score->net)->toBe(27.0)
        ->and($score->blank)->toBe(1);
});

it('cezasız sınavda net doğru sayısına eşittir', function (): void {
    expect((new NetScoring(penaltyRatio: 0.0))->score(20, 10, 30)->net)->toBe(20.0);
});

it('net negatife düşmez', function (): void {
    // ÖSYM'de düşer ama öğrenciye eksi net göstermek yalnızca cesaret kırar;
    // ham doğru/yanlış sayıları zaten ayrıca gösteriliyor.
    $score = (new NetScoring(penaltyRatio: 0.25))->score(correct: 2, wrong: 20, total: 40);

    expect($score->net)->toBe(0.0)
        ->and($score->correct)->toBe(2)
        ->and($score->wrong)->toBe(20);
});

it('boş sayısını doğru hesaplar', function (): void {
    $score = (new NetScoring(penaltyRatio: 0.25))->score(correct: 10, wrong: 5, total: 40);

    expect($score->blank)->toBe(25);
});

it('tahmini puanı taban üzerine kurar', function (): void {
    $score = (new NetScoring(penaltyRatio: 0.25, baseScore: 100))->score(40, 8, 60);

    expect($score->estimatedScore)->toBe(252.0);   // 100 + 38 × 4
});

describe('zorluk dağılımı', function (): void {
    it('oranları soru adedine çevirir', function (): void {
        $item = new BlueprintItemSpec(
            courseId: 1, topicId: null, questionCount: 40,
            difficultyDistribution: ['1' => 0.2, '2' => 0.3, '3' => 0.3, '4' => 0.15, '5' => 0.05],
        );

        $counts = $item->questionsPerDifficulty();

        expect(array_sum($counts))->toBe(40)
            ->and($counts[1])->toBe(8)
            ->and($counts[5])->toBe(2);
    });

    it('yuvarlama artığını kaybetmez', function (): void {
        // Toplam her zaman questionCount'a eşit olmalı; aksi hâlde deneme
        // eksik soruyla kurulur ve net hesabı bozulur.
        $item = new BlueprintItemSpec(
            courseId: 1, topicId: null, questionCount: 7,
            difficultyDistribution: ['1' => 0.33, '2' => 0.33, '3' => 0.34],
        );

        expect(array_sum($item->questionsPerDifficulty()))->toBe(7);
    });

    it('dağılım yoksa boş döner', function (): void {
        $item = new BlueprintItemSpec(courseId: 1, topicId: null, questionCount: 10);

        expect($item->questionsPerDifficulty())->toBe([]);
    });
});
