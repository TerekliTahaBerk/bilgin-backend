<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

/**
 * Seçimin yapıldığı bağlam.
 *
 * $userId null olabilir: yayın kapısı (ValidateSelectionRule) kuralı
 * kullanıcısız kuru çalıştırır. Bu yüzden hiçbir selector kullanıcının
 * var olduğunu varsayamaz.
 *
 * $publicationUnitId yalnızca yayın doğrulamasında doludur ve seçiciler onu
 * havuza olduğu gibi geçirir. Çalışma anı bağlamı onu HİÇBİR ZAMAN doldurmaz;
 * doldurursa öğrenci taslak soru görürdü.
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
        public ?int $publicationUnitId = null,
    ) {}
}
