<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Console;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Gamification\Domain\Xp\LevelCurve;
use App\Modules\Identity\Application\DTO\OnboardingCommand;
use App\Modules\Identity\Application\UseCase\CompleteOnboarding;
use App\Modules\Identity\Application\UseCase\RegisterGuestUser;
use App\Modules\Identity\Domain\Enum\Grade;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Domain\Event\XpAwarded;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Uygulamayı dolu bir hâlde görmek için sahte öğrenciler üretir.
 *
 * Boş bir kurulumda lig tablosu tek kişilik, profil sıfır, seri yok —
 * yani ekranların yarısı gerçekte nasıl görüneceğini göstermiyor. Test
 * eden kişi "çalışıyor mu" sorusunu ancak dolu ekranda cevaplayabilir.
 *
 * XP, GERÇEK YOLDAN veriliyor: `XpAwarded` olayı yayımlanıyor ve lig
 * üyeliğini her zamanki dinleyici kuruyor. Doğrudan satır yazmak, üretimde
 * asla oluşmayacak bir veri şekli üretir ve test edilen şey uygulama
 * olmaktan çıkar.
 *
 * Demo kullanıcıları `demo-` önekli cihaz kimliğiyle işaretleniyor;
 * `demo:clear` hepsini geri alıyor.
 */
final class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo:seed
                            {--students=14 : Kaç sahte öğrenci}
                            {--force : Üretimde de çalıştır}';

    protected $description = 'Lig ve profil ekranlarını dolu görmek için sahte öğrenci üretir';

    /** Cihaz kimliği öneki — temizleme bunu kullanıyor. */
    public const DEVICE_PREFIX = 'demo-ogrenci-';

    /** @var list<string> */
    private const NAMES = [
        'Ege', 'Deniz', 'Zeynep', 'Kerem', 'Elif', 'Mert', 'Aslı',
        'Burak', 'Nehir', 'Can', 'Defne', 'Emir', 'Sıla', 'Yusuf',
        'Ada', 'Poyraz', 'Duru', 'Arda',
    ];

    public function __construct(private readonly LevelCurve $levels)
    {
        parent::__construct();
    }

    public function handle(
        RegisterGuestUser $register,
        CompleteOnboarding $onboard,
    ): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->components->error('Üretimde çalışmaz. Bilerek istiyorsan --force ekle.');

            return self::FAILURE;
        }

        $count = min((int) $this->option('students'), count(self::NAMES));

        $this->components->info("{$count} sahte öğrenci üretiliyor...");

        for ($i = 0; $i < $count; $i++) {
            $name = self::NAMES[$i];

            $this->components->task($name, function () use ($register, $onboard, $i, $name): bool {
                $user = $register(self::DEVICE_PREFIX.$i, 'ios');

                $onboard(new OnboardingCommand(
                    userId: $user->id,
                    examCode: 'yks',
                    field: FieldCode::Sayisal,
                    grade: Grade::OnBir,
                    name: $name,
                ));

                $this->awardWeeklyXp($user, $this->xpFor($i));

                return true;
            });
        }

        $this->newLine();
        $this->components->info('Hazır. Lig tablosu ve sıralama artık dolu.');
        $this->components->twoColumnDetail('Temizlemek için', 'php artisan demo:clear');

        return self::SUCCESS;
    }

    /**
     * Sıralamanın anlamlı görünmesi için dağılmış XP.
     *
     * Hepsine aynı XP vermek lig ekranını test etmiyor: yükselme ve düşme
     * bölgeleri ancak farklı değerlerle ayırt edilebiliyor.
     */
    private function xpFor(int $index): int
    {
        return match (true) {
            $index < 2 => 480 - $index * 60,    // tepe
            $index < 6 => 320 - $index * 25,    // orta üst
            $index < 10 => 180 - $index * 8,    // orta
            default => max(20, 90 - $index * 4), // düşme bölgesi
        };
    }

    /**
     * XP'yi gerçek olay üzerinden verir.
     *
     * `sourceId` kullanıcı kimliğiyle tekil: XP defteri
     * `unique(source_type, source_id)` tutuyor ve komut iki kez
     * çalıştırılırsa aynı XP ikinci kez yazılmamalı.
     */
    private function awardWeeklyXp(User $user, int $amount): void
    {
        $now = new DateTimeImmutable;

        DB::table('xp_ledger')->insertOrIgnore([
            'user_id' => $user->id,
            'amount' => $amount,
            'source_type' => 'demo_seed',
            'source_id' => $user->id,
            'course_id' => null,
            'counts_for_league' => true,
            'breakdown' => json_encode(['demo' => $amount]),
            'awarded_at' => $now,
        ]);

        // Profil ekranı istatistik tablosundan besleniyor; defteri yazıp
        // burayı atlamak, XP'si olan ama seviyesi 1 görünen bir kullanıcı
        // bırakırdı.
        DB::table('user_stats')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'total_xp' => $amount,
                'level' => $this->levels->levelFor($amount),
                'current_streak' => 1 + ($user->id % 9),
                'longest_streak' => 3 + ($user->id % 14),
                'last_study_date' => $now->format('Y-m-d'),
                'total_sessions' => 2 + ($user->id % 11),
                'perfect_sessions' => $user->id % 4,
                'total_correct' => $amount,
            ],
        );

        Event::dispatch(new XpAwarded(
            userId: $user->id,
            amount: $amount,
            sourceType: 'demo_seed',
            sourceId: $user->id,
            countsForLeague: true,
            timezone: 'Europe/Istanbul',
            awardedAt: $now,
        ));
    }
}
