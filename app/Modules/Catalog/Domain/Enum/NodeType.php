<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enum;

enum NodeType: string
{
    case Study = 'study';
    case Matching = 'matching';
    case MiniChallenge = 'mini_challenge';
    case UnitChallenge = 'unit_challenge';
    case QuickReview = 'quick_review';
    case ExamSim = 'exam_sim';

    /**
     * Sınav provası ve seviye tespiti can harcamaz: gerçek sınav havası
     * bozulmasın ve onboarding'de kullanıcı kaybedilmesin diye.
     */
    public function consumesHeartsByDefault(): bool
    {
        return $this !== self::ExamSim;
    }

    /** Lig XP'si yalnızca oturum XP'sinden gelir; hepsi buraya dahildir. */
    public function xpMultiplier(): float
    {
        return match ($this) {
            self::Study, self::Matching => 1.0,
            self::QuickReview => 0.5,
            self::MiniChallenge => 2.0,
            self::UnitChallenge, self::ExamSim => 3.0,
        };
    }
}
