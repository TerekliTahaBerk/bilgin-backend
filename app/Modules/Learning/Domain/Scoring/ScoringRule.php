<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Scoring;

/**
 * Deneme puanlaması.
 *
 * Sınavdan sınava değişen tek şey ceza oranıdır (YKS 1/4, LGS 1/3, bazı
 * sınavlarda 0). Bu yüzden ayrı sınıflar yerine tek kural + parametre:
 * yeni sınav eklemek bir veri satırı, yeni bir sınıf değil.
 */
interface ScoringRule
{
    public function score(int $correct, int $wrong, int $total): ExamScore;
}
