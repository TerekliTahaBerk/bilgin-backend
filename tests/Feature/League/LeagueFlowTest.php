<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\League\Domain\Enum\LeagueTier;
use App\Modules\League\Infrastructure\Eloquent\Model\League;
use App\Modules\League\Infrastructure\Eloquent\Model\LeagueMembership;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Lig — tasarımdaki dördüncü sekme.
 |
 | Kritik davranış: lige katılım XP kazanınca olur, hafta başında toplu
 | atamayla değil. Toplu atama, kohortların çoğunu sıfır XP'de bırakır ve
 | yarışı anlamsız kılar.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'lig-testi', 'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ]);
});

/** Bir turu hatasız bitirir ve kazanılan XP'yi döner. */
function playOneRound(?string $token = null): int
{
    $token ??= test()->token;

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $node = UnitNode::query()->whereIn('unit_id', $course->units()->pluck('id'))
        ->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();

    $sessionId = test()->withToken($token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->json('data.session_id');

    $items = StudySession::query()->where('uuid', $sessionId)->firstOrFail()->items()->get();

    foreach ($items as $item) {
        $key = $item->answer_key_snapshot;

        $answer = match ($item->type->value) {
            'multiple_choice' => ['option_id' => $key['correct_option_id']],
            'true_false' => ['value' => $key['value']],
            'fill_blank' => ['blanks' => $key['blanks']],
            'matching' => ['pairs' => $key['pairs']],
            'ordering', 'word_order' => ['order' => $key['order']],
            'numeric_input' => ['value' => $key['value']],
            default => ['known' => true],
        };

        test()->withToken($token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $item->exercise_id, 'answer' => $answer, 'elapsed_ms' => 5000,
        ]);
    }

    return (int) test()->withToken($token)
        ->postJson("/api/v1/sessions/{$sessionId}/complete")
        ->json('data.xp.total');
}

it('XP kazanmadan lige katılmaz', function (): void {
    // Hafta başında toplu atama yapılsaydı kohortların çoğu sıfır XP'de
    // durur ve yarış diye bir şey kalmazdı.
    $response = $this->withToken($this->token)->getJson('/api/v1/league/current')->assertOk();

    expect($response->json('data.joined'))->toBeFalse()
        ->and(LeagueMembership::query()->count())->toBe(0);
});

it('ilk tur tamamlanınca bronz lige katılır', function (): void {
    $xp = playOneRound();

    app('auth')->forgetGuards();
    $response = $this->withToken($this->token)->getJson('/api/v1/league/current')->assertOk();

    expect($response->json('data.joined'))->toBeTrue()
        ->and($response->json('data.tier'))->toBe('bronze')
        ->and($response->json('data.name'))->toBe('Bronz Lig')
        ->and($response->json('data.my_rank'))->toBe(1)
        ->and($response->json('data.members.0.weekly_xp'))->toBe($xp)
        ->and($response->json('data.members.0.is_me'))->toBeTrue();
});

it('aynı hafta ikinci tur XP\'yi ekler, yeni üyelik açmaz', function (): void {
    $first = playOneRound();
    $second = playOneRound();

    expect(LeagueMembership::query()->count())->toBe(1)
        ->and((int) LeagueMembership::query()->value('weekly_xp'))->toBe($first + $second);
});

it('haftanın bitişini ve terfi eşiğini bildirir', function (): void {
    playOneRound();

    app('auth')->forgetGuards();
    $data = $this->withToken($this->token)->getJson('/api/v1/league/current')->json('data');

    expect($data['ends_at'])->not->toBeNull()
        ->and($data['promotion_zone'])->toBe(5)
        // Tek kişilik kohortta zaten 1. sıradayız; terfiye kalan XP yok.
        ->and($data)->not->toHaveKey('xp_to_promotion');
});

it('kohort dolunca yeni lig açılır', function (): void {
    config(['tekrarla.league.cohort_size' => 2]);

    $week = now(config('tekrarla.league.timezone'))->startOfWeek()->format('Y-m-d');

    // Üç kullanıcı, kapasite 2 → iki kohort.
    foreach (['a', 'b', 'c'] as $device) {
        app('auth')->forgetGuards();

        $token = $this->postJson('/api/v1/auth/guest', [
            'device_identifier' => $device, 'platform' => 'ios',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

        playOneRound($token);
    }

    $leagues = League::query()->where('week_start', $week)->get();

    expect($leagues)->toHaveCount(2)
        ->and($leagues->sum('member_count'))->toBe(3);
});

it('hafta kapanışı sıralamayı kesinleştirir ve terfi yazar', function (): void {
    config(['tekrarla.league.promotion_count' => 1, 'tekrarla.league.demotion_count' => 1]);

    $week = now(config('tekrarla.league.timezone'))->startOfWeek()->format('Y-m-d');

    $league = League::query()->create([
        'tier' => LeagueTier::Emerald, 'week_start' => $week, 'capacity' => 30, 'member_count' => 3,
    ]);

    foreach ([['u1', 500], ['u2', 300], ['u3', 100]] as $index => [$device, $xp]) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/guest', ['device_identifier' => $device, 'platform' => 'ios']);

        LeagueMembership::query()->create([
            'league_id' => $league->id,
            'user_id' => DB::table('users')->latest('id')->value('id'),
            'week_start' => $week,
            'weekly_xp' => $xp,
        ]);
    }

    $this->artisan('league:close', ['--week' => $week])->assertSuccessful();

    $memberships = LeagueMembership::query()->orderBy('final_rank')->get();

    expect($league->fresh()->status)->toBe('closed')
        ->and($memberships[0]->result)->toBe('promoted')
        ->and($memberships[1]->result)->toBe('stayed')
        ->and($memberships[2]->result)->toBe('demoted')
        ->and($memberships->pluck('final_rank')->all())->toBe([1, 2, 3]);
});

it('kapanış idempotenttir', function (): void {
    $week = now(config('tekrarla.league.timezone'))->startOfWeek()->format('Y-m-d');

    $league = League::query()->create([
        'tier' => LeagueTier::Bronze, 'week_start' => $week, 'capacity' => 30, 'member_count' => 1,
    ]);

    playOneRound();
    LeagueMembership::query()->update(['league_id' => $league->id]);

    $this->artisan('league:close', ['--week' => $week])->assertSuccessful();
    $firstRank = LeagueMembership::query()->value('final_rank');

    // Yarım kalan job yeniden çalıştığında sonucu değiştirmemeli.
    $this->artisan('league:close', ['--week' => $week])->assertSuccessful();

    expect(LeagueMembership::query()->value('final_rank'))->toBe($firstRank)
        ->and(League::query()->where('status', 'closed')->count())->toBe(1);
});

it('terfi eden kullanıcı sonraki hafta üst ligde başlar', function (): void {
    $thisWeek = now(config('tekrarla.league.timezone'))->startOfWeek();
    $lastWeek = $thisWeek->copy()->subWeek()->format('Y-m-d');

    playOneRound();
    $userId = (int) DB::table('users')->value('id');

    // Geçen hafta zümrütte terfi etmiş gibi kurgula.
    $previous = League::query()->create([
        'tier' => LeagueTier::Emerald, 'week_start' => $lastWeek,
        'capacity' => 30, 'member_count' => 1, 'status' => 'closed',
    ]);

    LeagueMembership::query()->create([
        'league_id' => $previous->id, 'user_id' => $userId,
        'week_start' => $lastWeek, 'weekly_xp' => 5000,
        'final_rank' => 1, 'result' => 'promoted',
    ]);

    // Bu haftaki üyeliği silip yeniden kazandır.
    LeagueMembership::query()->where('week_start', $thisWeek->format('Y-m-d'))->delete();
    League::query()->where('week_start', $thisWeek->format('Y-m-d'))->delete();

    playOneRound();

    $current = LeagueMembership::query()
        ->with('league')
        ->where('week_start', $thisWeek->format('Y-m-d'))
        ->firstOrFail();

    expect($current->league->tier)->toBe(LeagueTier::Sapphire);
});

it('lig geçmişi kapanmış haftaları döner', function (): void {
    $week = now(config('tekrarla.league.timezone'))->startOfWeek()->format('Y-m-d');

    playOneRound();
    $this->artisan('league:close', ['--week' => $week]);

    app('auth')->forgetGuards();
    $history = $this->withToken($this->token)->getJson('/api/v1/league/history')->assertOk()->json('data');

    expect($history)->toHaveCount(1)
        ->and($history[0]['tier'])->toBe('bronze')
        ->and($history[0]['rank'])->toBe(1);
});

it('rozet ve görev XP\'si lige sayılmaz', function (): void {
    // Lig XP'si yalnızca oturumdan gelir; manipülasyon yüzeyi dar tutulur.
    playOneRound();

    $sessionXp = (int) DB::table('xp_ledger')->where('source_type', 'session')->sum('amount');
    $leagueXp = (int) LeagueMembership::query()->value('weekly_xp');

    expect($leagueXp)->toBe($sessionXp);

    // Lige sayılmayan bir XP kaydı eklense bile lig puanı değişmez.
    DB::table('xp_ledger')->insert([
        'user_id' => DB::table('users')->value('id'),
        'amount' => 999, 'source_type' => 'badge', 'source_id' => 1,
        'counts_for_league' => false, 'awarded_at' => now(),
    ]);

    expect((int) LeagueMembership::query()->value('weekly_xp'))->toBe($sessionXp);
});
