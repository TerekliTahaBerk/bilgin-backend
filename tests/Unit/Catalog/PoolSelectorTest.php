<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionRule;
use App\Modules\Catalog\Domain\Selection\Strategy\PoolSelector;

/*
 | Veritabanı YOK. Domain katmanı Eloquent tanımadığı için havuzu sahte bir
 | implementasyonla besleyip seçim mantığını saniyeler içinde test edebiliyoruz.
 | Bu, "domain saf kalsın" kuralının somut getirisidir.
 */

/** Ölçüte göre filtreleme yapan basit bellek içi havuz. */
function fakePool(array $exercises): ExercisePool
{
    return new class($exercises) implements ExercisePool
    {
        /** @param list<array{id:int,difficulty:int,type:string,topic:int}> $rows */
        public function __construct(private array $rows) {}

        public function count(PoolCriteria $criteria): int
        {
            return count($this->filter($criteria));
        }

        public function pick(PoolCriteria $criteria, int $limit): array
        {
            return array_slice($this->filter($criteria), 0, max(0, $limit));
        }

        public function findSelectableByIds(array $ids, ?int $publicationUnitId = null): array
        {
            return array_values(array_map(
                fn (array $r): ExerciseRef => $this->toRef($r),
                array_filter($this->rows, fn (array $r): bool => in_array($r['id'], $ids, true)),
            ));
        }

        /** @return list<ExerciseRef> */
        private function filter(PoolCriteria $criteria): array
        {
            $matched = array_filter($this->rows, function (array $row) use ($criteria): bool {
                if ($criteria->topicIds !== [] && ! in_array($row['topic'], $criteria->topicIds, true)) {
                    return false;
                }

                if ($row['difficulty'] < $criteria->difficultyMin || $row['difficulty'] > $criteria->difficultyMax) {
                    return false;
                }

                if ($criteria->types !== [] && ! in_array($row['type'], $criteria->types, true)) {
                    return false;
                }

                return ! in_array($row['id'], $criteria->excludeExerciseIds, true);
            });

            return array_values(array_map($this->toRef(...), $matched));
        }

        private function toRef(array $row): ExerciseRef
        {
            return new ExerciseRef($row['id'], "u{$row['id']}", $row['type'], $row['difficulty'], $row['topic'], 1);
        }
    };
}

function rule(array $overrides = []): SelectionRule
{
    return SelectionRule::fromArray(array_replace_recursive([
        'mode' => 'pool',
        'count' => 3,
        'filters' => [
            'topics' => 'inherit_from_unit',
            'difficulty' => ['min' => 1, 'max' => 2],
            'types' => ['multiple_choice'],
        ],
        'fallback' => 'relax_difficulty',
    ], $overrides));
}

function unitContext(): SelectionContext
{
    return new SelectionContext(unitId: 1, unitTopicIds: [10, 11], courseScope: 'tyt');
}

it('ünitenin konularını miras alır', function (): void {
    $pool = fakePool([
        ['id' => 1, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 2, 'difficulty' => 2, 'type' => 'multiple_choice', 'topic' => 11],
        ['id' => 3, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 99], // başka ünite
    ]);

    $result = (new PoolSelector($pool))->select(rule(['count' => 3]), unitContext());

    expect(array_column($result->exercises, 'id'))->toBe([1, 2])
        ->and($result->isComplete())->toBeFalse()
        ->and($result->shortfall())->toBe(1);
});

it('zorluk ve tip filtresine uyar', function (): void {
    $pool = fakePool([
        ['id' => 1, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 2, 'difficulty' => 5, 'type' => 'multiple_choice', 'topic' => 10], // çok zor
        ['id' => 3, 'difficulty' => 2, 'type' => 'matching', 'topic' => 10],        // yanlış tip
    ]);

    $result = (new PoolSelector($pool))->select(rule(['count' => 1]), unitContext());

    expect(array_column($result->exercises, 'id'))->toBe([1]);
});

it('havuz yetersizse zorluğu gevşetir ve bunu raporlar', function (): void {
    $pool = fakePool([
        ['id' => 1, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 2, 'difficulty' => 4, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 3, 'difficulty' => 5, 'type' => 'multiple_choice', 'topic' => 11],
    ]);

    $result = (new PoolSelector($pool))->select(rule(['count' => 3]), unitContext());

    expect($result->exercises)->toHaveCount(3)
        ->and($result->relaxed)->toBeTrue();
});

it('gevşetme kapalıysa eksik soruyla döner', function (): void {
    $pool = fakePool([
        ['id' => 1, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 2, 'difficulty' => 5, 'type' => 'multiple_choice', 'topic' => 10],
    ]);

    $result = (new PoolSelector($pool))->select(
        rule(['count' => 3, 'fallback' => 'none']),
        unitContext(),
    );

    expect($result->exercises)->toHaveCount(1)
        ->and($result->relaxed)->toBeFalse()
        ->and($result->shortfall())->toBe(2);
});

it('aynı soruyu gevşetme turunda tekrar seçmez', function (): void {
    $pool = fakePool([
        ['id' => 1, 'difficulty' => 1, 'type' => 'multiple_choice', 'topic' => 10],
        ['id' => 2, 'difficulty' => 4, 'type' => 'multiple_choice', 'topic' => 10],
    ]);

    $result = (new PoolSelector($pool))->select(rule(['count' => 2]), unitContext());

    expect(array_column($result->exercises, 'id'))->toBe([1, 2]);
});

it('geçersiz mod için anlaşılır hata verir', function (): void {
    SelectionRule::fromArray(['mode' => 'sihir', 'count' => 3]);
})->throws(InvalidArgumentException::class, 'selection_rule.mode geçersiz');
