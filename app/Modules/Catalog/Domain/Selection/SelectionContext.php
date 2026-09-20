<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

/**
 * Seçimin yapıldığı bağlam.
 *
 * $userId null olabilir: yayın kapısı (SelectionRuleValidator) kuralı
 * kullanıcısız kuru çalıştırır. Bu yüzden hiçbir selector kullanıcının
 * var olduğunu varsayamaz.
 *
 * @param  list<int>  $unitTopicIds
 * @param  list<int>  $excludeExerciseIds
 */
final readonly class SelectionContext
{
    /**
     * @param  list<int>  $unitTopicIds
     * @param  list<int>  $excludeExerciseIds
     */
    public function __construct(
        public int $unitId,
        public array $unitTopicIds,
        public string $courseScope,
        public ?int $userId = null,
        public array $excludeExerciseIds = [],
        public bool $publicationValidation = false,
    ) {}
}
