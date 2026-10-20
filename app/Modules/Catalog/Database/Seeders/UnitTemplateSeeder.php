<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitTemplate;
use Illuminate\Database\Seeder;

/**
 * Ünite iskeletleri. "Ünite oluştur → şablon seç → konuları işaretle" akışının
 * veri tarafı; node'ların selection_rule/unlock_rule/XP'si CreateUnitFromTemplate
 * tarafından bu tanımlardan türetilir.
 */
final class UnitTemplateSeeder extends Seeder
{
    public function run(): void
    {
        UnitTemplate::query()->updateOrCreate(
            ['code' => 'standart_unite'],
            [
                'name' => 'Standart Ünite',
                'description' => '6 node: kolaydan zora çalışma + eşleştirme + tekrar + ünite challenge.',
                'is_default' => true,
                'nodes' => [
                    [
                        'title' => 'Çalışma 1', 'node_type' => 'study', 'difficulty' => 'kolay',
                        'exercise_count' => 6, 'types' => ['multiple_choice', 'true_false'],
                    ],
                    [
                        'title' => 'Çalışma 2', 'node_type' => 'study', 'difficulty' => 'kolay_orta',
                        'exercise_count' => 7, 'types' => ['multiple_choice', 'fill_blank'],
                        'preview_label' => 'boşluk doldur · 7 soru',
                    ],
                    [
                        'title' => 'Kavramları Eşleştir', 'node_type' => 'matching', 'difficulty' => 'orta',
                        'exercise_count' => 4, 'types' => ['matching'],
                    ],
                    [
                        'title' => 'Çalışma 3', 'node_type' => 'study', 'difficulty' => 'orta_zor',
                        'exercise_count' => 7, 'types' => ['multiple_choice', 'ordering'],
                    ],
                    [
                        'title' => 'Hızlı Tekrar', 'node_type' => 'quick_review', 'difficulty' => 'kolay',
                        'exercise_count' => 10, 'mode' => 'review_queue',
                    ],
                    [
                        'title' => 'Ünite Challenge', 'node_type' => 'unit_challenge', 'difficulty' => 'zor',
                        'exercise_count' => 10, 'time_limit_sec' => 600, 'types' => [],
                    ],
                ],
            ],
        );

        /*
         | Her soru tipinden birer node.
         |
         | Standart ünitede ilk node yalnızca çoktan seçmeli ve doğru/yanlış
         | gösteriyor (kolaydan zora ilerleme bilinçli). Ama bu, geliştirirken
         | diğer altı tipin hiç görülmemesine yol açıyordu — bir tipin
         | bozulduğu ancak o node'a kadar ilerleyince anlaşılıyordu.
         |
         | Bu şablon yalnızca test paketinde kullanılıyor; müfredatta yeri
         | yok. Kodu ALT ÇİZGİYLE başlıyor — içerik dosyalarındaki kuralın
         | aynısı — ve panel bu önekli şablonları listelemiyor.
         */
        UnitTemplate::query()->updateOrCreate(
            ['code' => '_tum_tipler'],
            [
                'name' => 'Tüm Soru Tipleri (test)',
                'description' => 'Her node tek bir soru tipi — tiplerin tamamı tek ünitede.',
                'is_default' => false,
                'nodes' => [
                    [
                        'title' => 'Çoktan Seçmeli', 'node_type' => 'study',
                        'difficulty' => 'kolay', 'exercise_count' => 4,
                        'types' => ['multiple_choice'],
                    ],
                    [
                        'title' => 'Doğru / Yanlış', 'node_type' => 'study',
                        'difficulty' => 'kolay', 'exercise_count' => 4,
                        'types' => ['true_false'],
                    ],
                    [
                        'title' => 'Boşluk Doldur', 'node_type' => 'study',
                        'difficulty' => 'kolay_orta', 'exercise_count' => 4,
                        'types' => ['fill_blank'],
                        'preview_label' => 'boşluk doldur · 4 soru',
                    ],
                    [
                        'title' => 'Eşleştirme', 'node_type' => 'matching',
                        'difficulty' => 'orta', 'exercise_count' => 3,
                        'types' => ['matching'],
                    ],
                    [
                        'title' => 'Sıralama', 'node_type' => 'study',
                        'difficulty' => 'orta', 'exercise_count' => 3,
                        'types' => ['ordering'],
                    ],
                    [
                        'title' => 'Kelime Dizme', 'node_type' => 'study',
                        'difficulty' => 'orta', 'exercise_count' => 3,
                        'types' => ['word_order'],
                    ],
                    [
                        'title' => 'Sayısal Giriş', 'node_type' => 'study',
                        'difficulty' => 'orta', 'exercise_count' => 3,
                        'types' => ['numeric_input'],
                    ],
                    [
                        'title' => 'Bilgi Kartı', 'node_type' => 'study',
                        'difficulty' => 'kolay', 'exercise_count' => 4,
                        'types' => ['flashcard'],
                    ],
                    /*
                     | Son iki tip görsel istiyor ve medya altyapısı (R2)
                     | henüz yok. Yine de teste konuyor: istemci bu tipleri
                     | görselsiz de çizebiliyor (bölge ve konum ADLARIYLA)
                     | ve içerik ekibi yazdığı soruyu görebilmeli.
                     */
                    [
                        'title' => 'Harita İşaretleme', 'node_type' => 'study',
                        'difficulty' => 'orta', 'exercise_count' => 2,
                        'types' => ['image_hotspot'],
                        'preview_label' => 'görselli · 2 soru',
                    ],
                    [
                        'title' => 'Şema Etiketleme', 'node_type' => 'study',
                        'difficulty' => 'orta_zor', 'exercise_count' => 2,
                        'types' => ['diagram_label'],
                        'preview_label' => 'görselli · 2 soru',
                    ],
                ],
            ],
        );

        UnitTemplate::query()->updateOrCreate(
            ['code' => 'hafif_unite'],
            [
                'name' => 'Hafif Ünite',
                'description' => 'Kısa üniteler için 4 node.',
                'is_default' => false,
                'nodes' => [
                    [
                        'title' => 'Çalışma 1', 'node_type' => 'study', 'difficulty' => 'kolay',
                        'exercise_count' => 5, 'types' => ['multiple_choice', 'true_false'],
                    ],
                    [
                        'title' => 'Çalışma 2', 'node_type' => 'study', 'difficulty' => 'orta',
                        'exercise_count' => 6, 'types' => ['multiple_choice', 'fill_blank'],
                    ],
                    [
                        'title' => 'Mini Challenge', 'node_type' => 'mini_challenge', 'difficulty' => 'orta',
                        'exercise_count' => 8, 'time_limit_sec' => 300, 'types' => [],
                    ],
                    [
                        'title' => 'Hızlı Tekrar', 'node_type' => 'quick_review', 'difficulty' => 'kolay',
                        'exercise_count' => 8, 'mode' => 'review_queue',
                    ],
                ],
            ],
        );
    }
}
