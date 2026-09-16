<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Provider;

use App\Modules\Catalog\Console\ValidateContentCommand;
use App\Modules\Catalog\Domain\Contract\CourseTopicReader;
use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Contract\ProgressReader;
use App\Modules\Catalog\Domain\Contract\ReviewQueueReader;
use App\Modules\Catalog\Domain\Contract\UnitTopicReader;
use App\Modules\Catalog\Domain\Selection\ExerciseSelector;
use App\Modules\Catalog\Domain\Selection\SelectorRegistry;
use App\Modules\Catalog\Domain\Selection\Strategy\BlueprintSelector;
use App\Modules\Catalog\Domain\Selection\Strategy\FixedListSelector;
use App\Modules\Catalog\Domain\Selection\Strategy\PoolSelector;
use App\Modules\Catalog\Domain\Selection\Strategy\ReviewQueueSelector;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Validator\DiagramLabelValidator;
use App\Modules\Catalog\Domain\Validation\Validator\FillBlankValidator;
use App\Modules\Catalog\Domain\Validation\Validator\FlashcardValidator;
use App\Modules\Catalog\Domain\Validation\Validator\ImageHotspotValidator;
use App\Modules\Catalog\Domain\Validation\Validator\MatchingValidator;
use App\Modules\Catalog\Domain\Validation\Validator\MultipleChoiceValidator;
use App\Modules\Catalog\Domain\Validation\Validator\NumericInputValidator;
use App\Modules\Catalog\Domain\Validation\Validator\OrderingValidator;
use App\Modules\Catalog\Domain\Validation\Validator\TrueFalseValidator;
use App\Modules\Catalog\Domain\Validation\Validator\WordOrderValidator;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Repository\EloquentExercisePool;
use App\Modules\Catalog\Infrastructure\Eloquent\Repository\EloquentUnitTopicReader;
use App\Modules\Catalog\Infrastructure\Eloquent\Repository\NullProgressReader;
use App\Modules\Catalog\Infrastructure\Eloquent\Repository\NullReviewQueueReader;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Yeni bir seçim modu eklemek yalnızca bu listeye bir satır eklemektir.
     * Mevcut hiçbir sınıf açılmaz.
     *
     * @var list<class-string<ExerciseSelector>>
     */
    private const SELECTORS = [
        PoolSelector::class,
        FixedListSelector::class,
        ReviewQueueSelector::class,
        BlueprintSelector::class,
    ];

    /**
     * Her egzersiz tipinin içerik doğrulayıcısı. Grader listesiyle BİREBİR
     * aynı tipleri kapsamalı; eksik kalan bir tip, panelden hatalı soru
     * girilebilmesi demek.
     *
     * @var list<class-string<ExerciseContentValidator>>
     */
    private const VALIDATORS = [
        MultipleChoiceValidator::class,
        TrueFalseValidator::class,
        FillBlankValidator::class,
        MatchingValidator::class,
        OrderingValidator::class,
        WordOrderValidator::class,
        NumericInputValidator::class,
        FlashcardValidator::class,
        ImageHotspotValidator::class,
        DiagramLabelValidator::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ValidatorRegistry::class, static function (Application $app): ValidatorRegistry {
            $registry = new ValidatorRegistry;

            foreach (self::VALIDATORS as $validator) {
                $registry->register($app->make($validator));
            }

            return $registry;
        });

        // DIP: domain arayüzü → infrastructure implementasyonu.
        $this->app->bind(ExercisePool::class, EloquentExercisePool::class);
        $this->app->bind(UnitTopicReader::class, EloquentUnitTopicReader::class);
        $this->app->bind(CourseTopicReader::class, EloquentUnitTopicReader::class);
        // Learning modülü gelince buradaki bağlama gerçek kuyruk okuyucusuyla değişir.
        $this->app->bind(ReviewQueueReader::class, NullReviewQueueReader::class);
        $this->app->bind(ProgressReader::class, NullProgressReader::class);

        $this->app->singleton(SelectorRegistry::class, static function (Application $app): SelectorRegistry {
            $registry = new SelectorRegistry;

            foreach (self::SELECTORS as $selector) {
                $registry->register($app->make($selector));
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([ValidateContentCommand::class]);
        }
    }
}
