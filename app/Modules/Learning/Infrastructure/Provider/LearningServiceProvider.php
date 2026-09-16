<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Provider;

use App\Modules\Catalog\Domain\Contract\ProgressReader;
use App\Modules\Catalog\Domain\Contract\ReviewQueueReader;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\Grader\DiagramLabelGrader;
use App\Modules\Learning\Domain\Grading\Grader\FillBlankGrader;
use App\Modules\Learning\Domain\Grading\Grader\FlashcardGrader;
use App\Modules\Learning\Domain\Grading\Grader\ImageHotspotGrader;
use App\Modules\Learning\Domain\Grading\Grader\MatchingGrader;
use App\Modules\Learning\Domain\Grading\Grader\MultipleChoiceGrader;
use App\Modules\Learning\Domain\Grading\Grader\NumericInputGrader;
use App\Modules\Learning\Domain\Grading\Grader\OrderingGrader;
use App\Modules\Learning\Domain\Grading\Grader\TrueFalseGrader;
use App\Modules\Learning\Domain\Grading\Grader\WordOrderGrader;
use App\Modules\Learning\Domain\Grading\GraderRegistry;
use App\Modules\Learning\Infrastructure\Eloquent\Repository\EloquentLearningStatsReader;
use App\Modules\Learning\Infrastructure\Eloquent\Repository\EloquentProgressReader;
use App\Modules\Learning\Infrastructure\Eloquent\Repository\EloquentReviewQueueReader;
use App\Shared\Domain\Learner\LearningStatsReader;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class LearningServiceProvider extends ServiceProvider
{
    /**
     * Yeni bir egzersiz tipi eklemek: enum'a bir case, bir grader sınıfı ve
     * bu listeye bir satır. Mevcut hiçbir dosya açılmaz (OCP).
     *
     * @var list<class-string<ExerciseGrader>>
     */
    private const GRADERS = [
        MultipleChoiceGrader::class,
        TrueFalseGrader::class,
        FillBlankGrader::class,
        MatchingGrader::class,
        OrderingGrader::class,
        WordOrderGrader::class,
        NumericInputGrader::class,
        FlashcardGrader::class,
        ImageHotspotGrader::class,
        DiagramLabelGrader::class,
    ];

    public function register(): void
    {
        $this->app->singleton(GraderRegistry::class, static function (Application $app): GraderRegistry {
            $registry = new GraderRegistry;

            foreach (self::GRADERS as $grader) {
                $registry->register($app->make($grader));
            }

            return $registry;
        });

        /*
         | Catalog'un geçici bağlamalarının yerini alır. Catalog bu sınıfları
         | tanımaz — yalnızca kendi Domain\Contract arayüzlerini bilir; hangi
         | modülün onları uyguladığı container'ın bileceği iştir.
         */
        $this->app->bind(ProgressReader::class, EloquentProgressReader::class);
        $this->app->bind(ReviewQueueReader::class, EloquentReviewQueueReader::class);

        // Gamification rozet değerlendirmesi için bu özeti okur;
        // Learning'in tablolarına doğrudan dokunmaz.
        $this->app->bind(LearningStatsReader::class, EloquentLearningStatsReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
