<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Xp;

/**
 * Oturum XP'sini hesaplar.
 *
 * Kurallar sırayla uygulanır ve her biri dökümde ayrı satır bırakır:
 * sonuç ekranı "neden bu kadar XP aldım" sorusunu cevaplayabilmeli.
 *
 * Yeni bir bonus eklemek = buraya bir plus() satırı + config anahtarı.
 */
final readonly class XpCalculator
{
    public function __construct(
        private int $perfectBonus,
        private int $firstCompletionBonus,
        private float $replayFactor,
        private float $belowThresholdFactor,
    ) {}

    public function calculate(SessionOutcome $outcome): XpAward
    {
        $award = XpAward::zero()->plus('base', $outcome->baseXp);

        // Eşiğin altında kalan oturum da XP alır ama yarısını:
        // emek ödüllendirilir, başarısızlık cezalandırılmaz.
        if (! $outcome->meetsCompletionThreshold) {
            $award = $award->scaled('below_threshold', $this->belowThresholdFactor);
        }

        // Tekrar oynamada taban düşer — aynı node'u grind ederek
        // lig sıralaması yükseltilemesin.
        if ($outcome->isReplay) {
            $award = $award->scaled('replay', $this->replayFactor);
        }

        if ($outcome->isPerfect) {
            $award = $award->plus('perfect', $this->perfectBonus);
        }

        // İlk tamamlama bonusu yalnızca bir kez, tekrarlarda asla.
        if ($outcome->isFirstCompletion && ! $outcome->isReplay) {
            $award = $award->plus('first_completion', $this->firstCompletionBonus);
        }

        return $award;
    }
}
