<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/*
 | $fillable ile tablo şemasının ayrışmasını yakalar.
 |
 | Bu testin varlık sebebi somut: geliştirme sırasında ÜÇ kez aynı hata
 | yapıldı — migration'a yeni kolon eklendi, $fillable'a eklenmedi. Laravel
 | bunu hata olarak bildirmez; alanı SESSİZCE düşürür. Kayıt oluşur, değer
 | null kalır, testler o alana bakmıyorsa yeşil geçer.
 |
 | Üç örnek: exam_blueprint_id (deneme oturumu bozuldu), email_verified_at
 | (Google girişinde e-posta doğrulanmamış göründü), user_hearts varsayılanları.
 |
 | Tek tek hatırlamak yerine kuralı teste bağlamak, dördüncüsünü imkânsız kılar.
 */

/** @return list<class-string<Model>> */
function eloquentModels(): array
{
    $models = [];

    foreach (Finder::create()->files()->in(app_path('Modules'))->name('*.php')->path('Eloquent/Model') as $file) {
        $class = 'App\\Modules\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            substr($file->getRealPath(), strlen(app_path('Modules')) + 1),
        );

        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            $models[] = $class;
        }
    }

    return $models;
}

it('her modelin fillable listesi tablo kolonlarını kapsar', function (): void {
    $problems = [];

    foreach (eloquentModels() as $class) {
        /** @var Model $model */
        $model = new $class;

        // $guarded = [] kullanan modeller tüm kolonlara zaten açık.
        if ($model->getFillable() === []) {
            continue;
        }

        if (! Schema::hasTable($model->getTable())) {
            continue;
        }

        $ignored = [
            $model->getKeyName(), 'created_at', 'updated_at', 'deleted_at', 'remember_token',
        ];

        $missing = array_diff(
            Schema::getColumnListing($model->getTable()),
            $model->getFillable(),
            $ignored,
        );

        if ($missing !== []) {
            $problems[] = class_basename($class).': '.implode(', ', $missing);
        }
    }

    expect($problems)->toBe([], "fillable'da eksik kolonlar:\n".implode("\n", $problems));
});
