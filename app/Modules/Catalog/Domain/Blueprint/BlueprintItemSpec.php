<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Blueprint;

/** Denemenin bir dersten kaç soru içereceği ve zorluk dağılımı. */
final readonly class BlueprintItemSpec
{
    /** @param  array<string, float>  $difficultyDistribution  zorluk (1–5) → oran */
    public function __construct(
        public ?int $courseId,
        public ?int $topicId,
        public int $questionCount,
        public array $difficultyDistribution = [],
    ) {}

    /**
     * Zorluk dağılımını soru adedine çevirir.
     *
     * Yuvarlama artıkları en yüksek paylı zorluğa eklenir; toplam her zaman
     * questionCount'a eşit olmalı, aksi hâlde deneme eksik soruyla kurulur.
     *
     * @return array<int, int> zorluk → adet
     */
    public function questionsPerDifficulty(): array
    {
        if ($this->difficultyDistribution === []) {
            return [];
        }

        $counts = [];
        $assigned = 0;

        foreach ($this->difficultyDistribution as $difficulty => $ratio) {
            $count = (int) floor($this->questionCount * $ratio);
            $counts[(int) $difficulty] = $count;
            $assigned += $count;
        }

        $remainder = $this->questionCount - $assigned;

        if ($remainder > 0) {
            $top = array_search(max($this->difficultyDistribution), $this->difficultyDistribution, true);
            $counts[(int) $top] += $remainder;
        }

        return $counts;
    }
}
