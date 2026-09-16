<?php

declare(strict_types=1);

use App\Modules\League\Domain\Enum\LeagueTier;
use App\Modules\League\Domain\LeagueWeek;
use App\Modules\League\Domain\Promotion\PromotionRule;
use App\Modules\League\Domain\Promotion\StandingEntry;

/*
 | Terfi/düşme kuralları — veritabanı yok.
 |
 | Bir hatanın bedeli doğrudan: yanlış düşürülen kullanıcı haftanın emeğini
 | kaybetmiş sayar ve bunu geri almanın yolu yoktur.
 */

function promotionRule(int $promote = 5, int $demote = 5): PromotionRule
{
    return new PromotionRule($promote, $demote);
}

/** @return list<StandingEntry> XP'ye göre sıralı kohort */
function leagueCohort(int $size, int $startXp = 1000): array
{
    $entries = [];

    for ($i = 0; $i < $size; $i++) {
        $entries[] = new StandingEntry(userId: $i + 1, weeklyXp: max(1, $startXp - $i * 50));
    }

    return $entries;
}

it('ilk beşi üst lige çıkarır', function (): void {
    $outcomes = promotionRule()->decide(leagueCohort(30), LeagueTier::Emerald);

    expect($outcomes[0]->result)->toBe('promoted')
        ->and($outcomes[0]->nextTier)->toBe(LeagueTier::Sapphire)
        ->and($outcomes[4]->result)->toBe('promoted')
        ->and($outcomes[5]->result)->toBe('stayed');
});

it('son beşi alt lige düşürür', function (): void {
    $outcomes = promotionRule()->decide(leagueCohort(30), LeagueTier::Emerald);

    expect($outcomes[29]->result)->toBe('demoted')
        ->and($outcomes[29]->nextTier)->toBe(LeagueTier::Gold)
        ->and($outcomes[25]->result)->toBe('demoted')
        ->and($outcomes[24]->result)->toBe('stayed');
});

it('sıfır XP\'li üyeyi DÜŞÜRMEZ', function (): void {
    // Haftayı hiç oynamadan geçiren zaten cezalandırılmış sayılır;
    // üstüne düşürmek geri dönmeyi zorlaştırır.
    $entries = [...leagueCohort(25), new StandingEntry(userId: 99, weeklyXp: 0)];

    $outcomes = promotionRule()->decide($entries, LeagueTier::Emerald);
    $last = $outcomes[count($outcomes) - 1];

    expect($last->userId)->toBe(99)
        ->and($last->result)->toBe('stayed');
});

it('şampiyon ligde terfi yoktur', function (): void {
    $outcomes = promotionRule()->decide(leagueCohort(30), LeagueTier::Champion);

    expect($outcomes[0]->result)->toBe('stayed')
        ->and($outcomes[0]->nextTier)->toBe(LeagueTier::Champion);
});

it('bronz ligde düşme yoktur', function (): void {
    // Bronzdan aşağı atmak kimseyi motive etmez.
    $outcomes = promotionRule()->decide(leagueCohort(30), LeagueTier::Bronze);

    expect($outcomes[29]->result)->toBe('stayed')
        ->and($outcomes[29]->nextTier)->toBe(LeagueTier::Bronze);
});

it('küçük kohortta düşme bölgesi daralır', function (): void {
    // 8 kişilik ligde son 5'i düşürmek, katılanların çoğunu cezalandırmak olurdu.
    $outcomes = promotionRule()->decide(leagueCohort(8), LeagueTier::Emerald);

    $demoted = array_filter($outcomes, fn ($o): bool => $o->result === 'demoted');

    expect(count($demoted))->toBe(2)   // floor(8/3)
        ->and($outcomes[7]->result)->toBe('demoted')
        ->and($outcomes[5]->result)->toBe('stayed');
});

it('herkesin terfi ettiği küçük kohortta kimse düşmez', function (): void {
    $outcomes = promotionRule()->decide(leagueCohort(4), LeagueTier::Emerald);

    expect(array_filter($outcomes, fn ($o): bool => $o->result === 'demoted'))->toBeEmpty()
        ->and(array_filter($outcomes, fn ($o): bool => $o->result === 'promoted'))->toHaveCount(4);
});

describe('lig haftası', function (): void {
    it('Pazartesi\'yi haftanın başı sayar', function (): void {
        $wednesday = new DateTimeImmutable('2026-09-16 14:00', new DateTimeZone('Europe/Istanbul'));

        expect(LeagueWeek::containing($wednesday)->startsOn)->toBe('2026-09-14');
    });

    it('Pazar gecesini hâlâ o haftaya yazar', function (): void {
        // UTC'ye göre hesaplansaydı Türkiye'de Pazar 23:00'te kazanılan XP
        // bir sonraki haftaya giderdi.
        $sundayNight = new DateTimeImmutable('2026-09-20 23:30', new DateTimeZone('Europe/Istanbul'));

        expect(LeagueWeek::containing($sundayNight)->startsOn)->toBe('2026-09-14');
    });

    it('Pazartesi 00:30 yeni haftadır', function (): void {
        $mondayEarly = new DateTimeImmutable('2026-09-21 00:30', new DateTimeZone('Europe/Istanbul'));

        expect(LeagueWeek::containing($mondayEarly)->startsOn)->toBe('2026-09-21');
    });

    it('bir önceki haftayı bulur', function (): void {
        $week = LeagueWeek::containing(new DateTimeImmutable('2026-09-16 12:00'));

        expect($week->previous()->startsOn)->toBe('2026-09-07');
    });
});
