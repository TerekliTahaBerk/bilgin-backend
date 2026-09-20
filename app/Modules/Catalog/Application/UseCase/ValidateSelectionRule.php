<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Domain\Contract\UnitTopicReader;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionRule;
use App\Modules\Catalog\Domain\Selection\SelectorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use InvalidArgumentException;

/**
 * YAYIN KAPISI.
 *
 * Havuz modelinin tek gerçek riski "kural yeterli soru getirmiyor"dur.
 * Bu risk çalışma anında öğrenciye yansımadan önce burada yakalanır:
 * kural kullanıcısız kuru çalıştırılır, gelen soru sayısı sayılır.
 *
 * Sessiz kırpma yoktur — eksik havuz bir hatadır, "idare eder" değildir.
 *
 * YAYIN ADAYI ANLAMI: bu kuru çalıştırma, yayınlanmaya çalışılan ünitenin
 * kendi arşivlenmemiş sorularını da aday sayar. Aksi hâlde soruları henüz
 * taslak olan bir ünite kendi sorularını sayamaz ve İLK yayın hiçbir zaman
 * mümkün olmazdı. Arşivlenmiş sorular aday değildir.
 *
 * Bu gevşeme yalnızca buradan geçer: çalışma anı bağlamını kuran kod
 * publicationUnitId'yi doldurmaz, dolayısıyla öğrenci havuzu yayındaki
 * sorularla sınırlı kalır.
 *
 * ADAY SAYMAK KAPATILABİLİR. Yayın kararı için aday kipi doğrudur; ama
 * "öğrenci ŞU AN ne alıyor?" sorusunun cevabı farklıdır ve yayınlanmış bir
 * ünitede ikisi ayrışabilir: soru arşivlenip yerine taslak yazıldığında aday
 * sayısı yeterli görünürken öğrenciye giden azalır. Önizleme ikisini birden
 * sorar; tek sayı hangisi olursa olsun editörü yanıltırdı.
 */
final readonly class ValidateSelectionRule
{
    public function __construct(
        private SelectorRegistry $selectors,
        private UnitTopicReader $unitTopics,
    ) {}

    /**
     * @param  bool  $includeUnitDrafts  Ünitenin kendi arşivlenmemiş soruları
     *                                   aday sayılsın mı. Yayın kapısı için
     *                                   true (yayınlayınca zaten yayına
     *                                   geçecekler); "öğrenci şu an ne
     *                                   alıyor" sorusu için false.
     */
    public function __invoke(UnitNode $node, bool $includeUnitDrafts = true): SelectionRuleReport
    {
        try {
            $rule = SelectionRule::fromArray($node->selection_rule ?? []);
        } catch (InvalidArgumentException $e) {
            return SelectionRuleReport::invalid($node, $e->getMessage());
        }

        if (! $this->selectors->supports($rule->mode)) {
            return SelectionRuleReport::invalid(
                $node,
                "'{$rule->mode->value}' modu için selector kayıtlı değil (henüz uygulanmadı)."
            );
        }

        $unit = $node->unit()->with('course')->first();

        if ($unit === null) {
            return SelectionRuleReport::invalid($node, 'Node bir üniteye bağlı değil.');
        }

        $context = new SelectionContext(
            unitId: $unit->id,
            unitTopicIds: $this->unitTopics->topicIdsForUnit($unit->id),
            courseScope: $unit->course->scope->value,
            publicationUnitId: $includeUnitDrafts ? $unit->id : null,
        );

        $result = $this->selectors->for($rule->mode)->select($rule, $context);

        return new SelectionRuleReport(
            nodeId: $node->id,
            nodeTitle: $node->title,
            unitTitle: $unit->title,
            courseName: $unit->course->name,
            required: $node->exercise_count,
            available: count($result->exercises),
            relaxed: $result->relaxed,
            error: null,
        );
    }
}
