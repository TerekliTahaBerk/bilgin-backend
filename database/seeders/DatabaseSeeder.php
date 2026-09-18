<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Admin\Database\Seeders\AdminUserSeeder;
use App\Modules\Catalog\Database\Seeders\PilotContentSeeder;
use App\Modules\Catalog\Database\Seeders\SubjectSeeder;
use App\Modules\Catalog\Database\Seeders\TopicSeeder;
use App\Modules\Catalog\Database\Seeders\UnitTemplateSeeder;
use App\Modules\Catalog\Database\Seeders\YksCourseSeeder;
use App\Modules\Curriculum\Database\Seeders\YksBlueprintSeeder;
use App\Modules\Curriculum\Database\Seeders\YksCurriculumMapSeeder;
use App\Modules\Curriculum\Database\Seeders\YksExamSeeder;
use App\Modules\Gamification\Database\Seeders\BadgeSeeder;
use Illuminate\Database\Seeder;

/**
 * Sıra önemlidir: oturumlar → dersler → eşleme. Eşleme tablosu hem
 * exam_variants'e hem courses'a bağlı olduğu için en sonda gelir.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            YksExamSeeder::class,        // Curriculum: sınav, oturum, alan varyantları
            SubjectSeeder::class,
            TopicSeeder::class,        // Catalog: kavramsal dersler
            YksCourseSeeder::class,      // Catalog: 21 ders
            YksCurriculumMapSeeder::class, // Curriculum: hangi alan hangi dersi görür
            YksBlueprintSeeder::class,   // Curriculum: deneme kompozisyonları
            UnitTemplateSeeder::class,   // Catalog: ünite şablonları
            PilotContentSeeder::class,   // Catalog: pilot üniteler + soru havuzu
            BadgeSeeder::class,          // Gamification: rozet tanımları
        ]);

        // Panel hesapları yalnızca yerel ve test ortamında.
        if (app()->environment('local', 'testing')) {
            $this->call(AdminUserSeeder::class);
        }
    }
}
