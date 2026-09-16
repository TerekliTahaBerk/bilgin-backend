<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Domain\ReadModel;

/**
 * Ders listesi ekranının tek satırı.
 *
 * Bilinçli olarak Eloquent modeli değil, düz bir okuma satırı: bu uç
 * uygulamanın her açılışında çağrılır ve 13–16 dersi tek join ile okumak,
 * pivot üzerinden model hidrasyonundan hem hızlı hem tip güvenlidir.
 */
final readonly class VariantCourseRow
{
    public function __construct(
        public int $courseId,
        public string $code,
        public string $name,
        public ?string $shortName,
        public ?string $color,
        public ?string $icon,
        public string $scope,
        public bool $published,
        public int $examSectionId,
        public int $sortOrder,
        public string $access,
        public ?int $examWeight,
        public ?string $placeholderLabel,
    ) {}

    public function requiresPremium(): bool
    {
        return $this->access === 'premium';
    }
}
