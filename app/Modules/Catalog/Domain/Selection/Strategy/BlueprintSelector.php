<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection\Strategy;

use App\Modules\Catalog\Domain\Blueprint\BlueprintItemSpec;
use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Catalog\Domain\Contract\CourseTopicReader;
use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\ExerciseSelector;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionResult;
use App\Modules\Catalog\Domain\Selection\SelectionRule;

/**
 * Sınav provası: soruları deneme kompozisyonuna göre seçer.
 *
 * Diğer modlardan farkı, seçimin ÜNİTEYE değil DERSE göre yapılması:
 * "TYT Genel Deneme" 120 soruyu 9 dersten, her dersin sınavdaki ağırlığı
 * kadar toplar. Zorluk dağılımı da gerçek sınava yaklaştırılır — hepsi
 * kolay ya da hepsi zor bir deneme, net tahminini işe yaramaz kılar.
 *
 * Sorular ders sırasına göre gelir; gerçek sınavda da testler gruplu gelir.
 */
final readonly class BlueprintSelector implements ExerciseSelector
{
    public function __construct(
        private BlueprintReader $blueprints,
        private CourseTopicReader $courseTopics,
        private ExercisePool $pool,
    ) {}

    public function mode(): SelectionMode
    {
        return SelectionMode::Blueprint;
    }

    public function select(SelectionRule $rule, SelectionContext $context): SelectionResult
    {
        $blueprint = $rule->blueprintId !== null ? $this->blueprints->find($rule->blueprintId) : null;

        if ($blueprint === null) {
            return new SelectionResult([], $rule->count > 0 ? $rule->count : 0);
        }

        $selected = [];

        foreach ($blueprint->items as $item) {
            $selected = [...$selected, ...$this->selectForItem($item, $selected)];
        }

        return new SelectionResult($selected, $blueprint->totalQuestions());
    }

    /**
     * @param  list<ExerciseRef>  $alreadySelected
     * @return list<ExerciseRef>
     */
    private function selectForItem(BlueprintItemSpec $item, array $alreadySelected): array
    {
        $topicIds = $item->topicId !== null
            ? [$item->topicId]
            : ($item->courseId !== null ? $this->courseTopics->topicIdsForCourse($item->courseId) : []);

        if ($topicIds === []) {
            return [];
        }

        $exclude = array_column($alreadySelected, 'id');
        $picked = [];

        // Önce zorluk dağılımına uyulur.
        foreach ($item->questionsPerDifficulty() as $difficulty => $count) {
            if ($count === 0) {
                continue;
            }

            $refs = $this->pool->pick(new PoolCriteria(
                topicIds: $topicIds,
                scope: 'tyt',
                difficultyMin: $difficulty,
                difficultyMax: $difficulty,
                excludeExerciseIds: [...$exclude, ...array_column($picked, 'id')],
            ), $count);

            $picked = [...$picked, ...$refs];
        }

        // Dağılım doldurulamadıysa (o zorlukta yeterli soru yok) kalan
        // kontenjan tüm zorluklardan tamamlanır: eksik soruyla deneme
        // kurmaktansa dağılımdan sapmak yeğdir.
        $missing = $item->questionCount - count($picked);

        if ($missing > 0) {
            $picked = [...$picked, ...$this->pool->pick(new PoolCriteria(
                topicIds: $topicIds,
                scope: 'tyt',
                excludeExerciseIds: [...$exclude, ...array_column($picked, 'id')],
            ), $missing)];
        }

        return $picked;
    }
}
