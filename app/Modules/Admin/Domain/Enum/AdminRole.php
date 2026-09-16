<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Enum;

/**
 * Panel rolleri.
 *
 * Yetkiler role GÖMÜLÜ, ayrı bir izin tablosu yok: bu ölçekte (5 rol,
 * ~10 yetki) izin tablosu esneklik değil yalnızca dolaylılık üretir.
 * Roller çeşitlenirse spatie/laravel-permission'a geçiş tek noktadan olur.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case ContentEditor = 'content_editor';
    case ContentReviewer = 'content_reviewer';
    case Support = 'support';
    case Analyst = 'analyst';

    public function canEditContent(): bool
    {
        return in_array($this, [self::SuperAdmin, self::ContentEditor], true);
    }

    /**
     * Yayınlama yetkisi editörde DEĞİL.
     *
     * Soruyu yazan kişinin kendi sorusunu yayınlaması, dört göz ilkesini
     * ortadan kaldırır; yanlış bir soru doğrudan öğrenciye gider.
     */
    public function canPublishContent(): bool
    {
        return in_array($this, [self::SuperAdmin, self::ContentReviewer], true);
    }

    public function canEditCurriculum(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canViewUsers(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Support], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Süper Yönetici',
            self::ContentEditor => 'İçerik Editörü',
            self::ContentReviewer => 'İçerik Denetçisi',
            self::Support => 'Destek',
            self::Analyst => 'Analist',
        };
    }
}
