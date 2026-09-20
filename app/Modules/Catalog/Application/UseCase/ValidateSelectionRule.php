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
 */
final readonly class ValidateSelectionRule
{
    public function __construct(
        private SelectorRegistry $selectors,
        private UnitTopicReader $unitTopics,
    ) {}

    public function __invoke(UnitNode $node): SelectionRuleReport
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
            publicationValidation: true,
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
