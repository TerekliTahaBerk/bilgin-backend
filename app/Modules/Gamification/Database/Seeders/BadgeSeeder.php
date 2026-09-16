<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Database\Seeders;

use App\Modules\Gamification\Infrastructure\Eloquent\Model\Badge;
use Illuminate\Database\Seeder;

/**
 * Tasarımdaki rozet ızgarası.
 *
 * Kriterler veri olduğu için yeni rozet eklemek panelden bir kayıt;
 * "30 Gün Seri"yi "21 Gün"e çevirmek bile deploy gerektirmez.
 */
final class BadgeSeeder extends Seeder
{
    private const BADGES = [
        ['ilk_calisma', 'İlk Çalışma', 'İlk turunu tamamladın.', 'sparkles', 'bronze',
            ['type' => 'threshold', 'metric' => 'total_sessions', 'target' => 1]],

        ['seri_7', '7 Gün Seri', 'Yedi gün üst üste çalıştın.', 'flame', 'bronze',
            ['type' => 'threshold', 'metric' => 'longest_streak', 'target' => 7]],

        ['dogru_100', '100 Doğru', 'Yüz soruyu doğru cevapladın.', 'target', 'silver',
            ['type' => 'threshold', 'metric' => 'total_correct', 'target' => 100]],

        ['ilk_unite', 'İlk Ünite', 'Bir üniteyi tamamen bitirdin.', 'flag', 'bronze',
            ['type' => 'threshold', 'metric' => 'completed_units', 'target' => 1]],

        ['ders_lv5', 'Ders Ustası', 'Bir derste 5. seviyeye ulaştın.', 'medal', 'silver',
            ['type' => 'threshold', 'metric' => 'max_course_level', 'target' => 5]],

        ['seri_30', '30 Gün Seri', 'Otuz gün üst üste çalıştın.', 'flame', 'gold',
            ['type' => 'threshold', 'metric' => 'longest_streak', 'target' => 30]],

        ['xp_5000', '5.000 XP', 'Toplam beş bin XP kazandın.', 'bolt', 'gold',
            ['type' => 'threshold', 'metric' => 'total_xp', 'target' => 5000]],

        // İki şartlı: kusursuz turlar ama yeterince oynadıktan sonra.
        // Tek şart olsaydı ilk turunu kusursuz bitiren rozeti anında alırdı.
        ['perfect', 'Kusursuz', 'On turu hatasız tamamladın.', 'star', 'gold',
            ['type' => 'all_of', 'criteria' => [
                ['type' => 'threshold', 'metric' => 'perfect_sessions', 'target' => 10],
                ['type' => 'threshold', 'metric' => 'total_sessions', 'target' => 20],
            ]]],
    ];

    public function run(): void
    {
        foreach (self::BADGES as $index => [$code, $name, $description, $icon, $tier, $criteria]) {
            Badge::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'tier' => $tier,
                    'criteria' => $criteria,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
