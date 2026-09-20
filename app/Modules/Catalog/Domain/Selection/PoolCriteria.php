<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

/**
 * Havuzdan soru çekme ölçütü. Saf veri — Eloquent tanımaz.
 *
 * $publicationUnitId YAYIN DOĞRULAMA kipini açar. Null olduğunda havuz
 * yalnızca yayındaki soruları görür; öğrenci çalışma anı her zaman böyledir.
 * Bir ünite kimliği verildiğinde, o üniteye ait arşivlenmemiş sorular da
 * aday sayılır — yayın kapısı, yayınlamaya çalıştığı ünitenin kendi taslak
 * sorularını sayamazsa ilk yayın imkânsız olurdu.
 *
 * @param  list<int>  $topicIds
 * @param  list<string>  $types  ExerciseType değerleri; boşsa tip filtresi yok
 * @param  list<int>  $excludeExerciseIds
 */
final readonly class PoolCriteria
{
    /**
     * @param  list<int>  $topicIds
     * @param  list<string>  $types
     * @param  list<int>  $excludeExerciseIds
     */
    public function __construct(
        public array $topicIds,
        public string $scope,
        public int $difficultyMin = 1,
        public int $difficultyMax = 5,
        public array $types = [],
        public array $excludeExerciseIds = [],
        public ?int $publicationUnitId = null,
    ) {}

    /** Havuz yetersizse zorluk aralığını genişletir (fallback: relax_difficulty). */
    public function relaxed(): self
    {
        return new self(
            $this->topicIds,
            $this->scope,
            1,
            5,
            $this->types,
            $this->excludeExerciseIds,
            $this->publicationUnitId,
        );
    }

    /** Tip filtresini de kaldırır — son çare. */
    public function withoutTypeFilter(): self
    {
        return new self(
            $this->topicIds,
            $this->scope,
            $this->difficultyMin,
            $this->difficultyMax,
            [],
            $this->excludeExerciseIds,
            $this->publicationUnitId,
        );
    }
}
