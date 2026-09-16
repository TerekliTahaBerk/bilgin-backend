<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

use App\Modules\Catalog\Domain\Unlock\ProgressSnapshot;

/**
 * Kullanıcının bir dersteki ilerlemesini tek seferde okur.
 *
 * Gerçek implementasyonu Learning modülünde olacak. Tek çağrıda tüm dersi
 * kapsayan bir snapshot dönmesi zorunlu: kilit kuralları ünite/node başına
 * sorgu çalıştırırsa yol ekranı N+1'e düşer.
 */
interface ProgressReader
{
    public function snapshotForCourse(int $userId, int $courseId): ProgressSnapshot;
}
