<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ProgressReader;
use App\Modules\Catalog\Domain\Unlock\ProgressSnapshot;

/**
 * Learning modülü gelene kadarki bağlama: hiç ilerleme yok.
 *
 * Sonuç doğrudur — yeni kullanıcının ilerlemesi zaten boştur. Yol ekranı
 * bugün de doğru çalışır: ilk node açık, gerisi kilitli.
 */
final class NullProgressReader implements ProgressReader
{
    public function snapshotForCourse(int $userId, int $courseId): ProgressSnapshot
    {
        return ProgressSnapshot::empty();
    }
}
