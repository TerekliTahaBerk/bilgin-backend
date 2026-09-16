<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Scoring;

/**
 * Net = doğru − (yanlış × ceza oranı).
 *
 * Ceza oranı 0.25 → YKS (4 yanlış 1 doğru götürür).
 * Ceza oranı 0.333 → LGS (3 yanlış 1 doğru götürür).
 *
 * Net negatife düşebilir mi? ÖSYM'de düşer ama ürün açısından öğrenciye
 * eksi net göstermek yalnızca cesaret kırar; 0'da sınırlandırıyoruz.
 * Ham doğru/yanlış sayıları zaten ayrıca gösteriliyor.
 */
final readonly class NetScoring implements ScoringRule
{
    public function __construct(
        private float $penaltyRatio,
        private int $baseScore = 100,
        private float $pointsPerNet = 4.0,
    ) {}

    public function score(int $correct, int $wrong, int $total): ExamScore
    {
        $net = max(0.0, $correct - ($wrong * $this->penaltyRatio));

        return new ExamScore(
            correct: $correct,
            wrong: $wrong,
            blank: max(0, $total - $correct - $wrong),
            net: round($net, 2),
            // Taban puan + net başına katsayı. Gerçek ÖSYM formülü
            // yıllık katsayılara ve standart sapmaya bağlıdır; bu bir
            // TAHMİNDİR ve istemcide de öyle etiketlenir.
            estimatedScore: round($this->baseScore + ($net * $this->pointsPerNet), 2),
        );
    }
}
