<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enum;

/** Müşteri dokümanındaki zorluk path'i: Kolay → SınavProvası. */
enum DifficultyLevel: string
{
    case Kolay = 'kolay';
    case KolayOrta = 'kolay_orta';
    case Orta = 'orta';
    case OrtaZor = 'orta_zor';
    case Zor = 'zor';
    case SinavProvasi = 'sinav_provasi';

    public function baseXp(): int
    {
        return match ($this) {
            self::Kolay => 10,
            self::KolayOrta => 15,
            self::Orta => 20,
            self::OrtaZor => 30,
            self::Zor => 40,
            self::SinavProvasi => 60,
        };
    }

    /** Havuzdan soru seçerken kullanılacak zorluk aralığı (exercises.difficulty 1–5). */
    /** @return array{int, int} */
    public function exerciseDifficultyRange(): array
    {
        return match ($this) {
            self::Kolay => [1, 2],
            self::KolayOrta => [1, 3],
            self::Orta => [2, 3],
            self::OrtaZor => [3, 4],
            self::Zor => [4, 5],
            self::SinavProvasi => [1, 5],
        };
    }
}
