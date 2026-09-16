<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

/**
 * İçerik yayın akışı: draft → review → published → archived.
 *
 * Yayındaki içerik SİLİNMEZ, arşivlenir; devam eden oturumlar
 * session_items.content_snapshot sayesinde etkilenmez.
 */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function isVisibleToLearners(): bool
    {
        return $this === self::Published;
    }
}
