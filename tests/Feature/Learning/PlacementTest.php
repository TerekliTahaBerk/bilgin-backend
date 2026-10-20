<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Learning\Infrastructure\Eloquent\Model\PlacementAttempt;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserNodeProgress;
use Database\Seeders\DatabaseSeeder;

/*
 | SEVİYE BELİRLEME TESTİ.
 |
 | Buradaki asıl risk "çalışıyor mu" değil, YANLIŞ YERE yerleştirmek.
 | Temelini atlayıp dört adım sonra duvara çarpan öğrenci, bunu kendi
 | başarısızlığı sanır ve uygulamayı bırakır. Testlerin çoğu bu yüzden
 | cömertliğin sınırını ölçüyor.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function placementToken(string $device = 'seviye-testi'): string
{
    app('auth')->forgetGuards();

    $token = test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => $device, 'platform' => 'ios',
    ])->json('data.token');

    actingWithToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'name' => 'Ege', 'daily_goal_rounds' => 3,
    ])->assertCreated();

    return $token;
}

/** Testi başlatır ve (deneme, sorular) döndürür. */
function startPlacement(string $token): array
{
    $data = actingWithToken($token)
        ->postJson('/api/v1/placement')
        ->assertCreated()
        ->json('data.placement');

    return [$data['id'], $data['questions']];
}

/**
 * Sorulara verilecek cevapları üretir.
 *
 * `$correctRatio` kadarı doğru, gerisi kasten yanlış. Cevap anahtarı
 * istemciye inmediği için veritabanından okunuyor — gerçek bir istemcinin
 * yapamayacağı şey, ki testin söylediği de tam olarak bu.
 */
function placementAnswers(int $attemptId, float $correctRatio): array
{
    $attempt = PlacementAttempt::query()->findOrFail($attemptId);
    $questions = $attempt->questions;
    $correctUpTo = (int) round(count($questions) * $correctRatio);

    $answers = [];

    foreach (array_values($questions) as $i => $q) {
        $answers[(string) $q['exercise_id']] = $i < $correctUpTo
            ? submissionFor((string) $q['type'], $q['answer_key'])
            : ['option_id' => '__yanlis__', 'blanks' => ['__yanlis__'], 'value' => -99999];
    }

    return $answers;
}

/**
 * Cevap anahtarını, istemcinin GÖNDERECEĞİ gövdeye çevirir.
 *
 * İkisi her tipte aynı değil: çoktan seçmelide anahtar
 * `correct_option_id`, gönderim `option_id` taşır. Bu fark gerçek bir
 * hataya yol açmıştı (doğru cevaplar yanlış gösteriliyordu) ve testin
 * anahtarı olduğu gibi göndermesi, aynı hatayı bir daha görünmez kılardı.
 *
 * @param  array<string, mixed>  $key
 * @return array<string, mixed>
 */
function submissionFor(string $type, array $key): array
{
    return match ($type) {
        'multiple_choice' => ['option_id' => $key['correct_option_id'] ?? null],
        default => $key,
    };
}

// ------------------------------------------------------------ başlatma

it('kayıt sonrası seviye testine yönlendirir', function (): void {
    app('auth')->forgetGuards();

    $token = test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'yonlendirme', 'platform' => 'ios',
    ])->json('data.token');

    // Bu alan baştan beri dönüyordu ama gidecek bir uç YOKTU.
    actingWithToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'daily_goal_rounds' => 3,
    ])->assertCreated()->assertJsonPath('data.next_step', 'placement');
});

it('cevap anahtarını istemciye SIZDIRMAZ', function (): void {
    [, $questions] = startPlacement(placementToken());

    // Testin tamamı tek istekte iniyor; anahtarın sızması testi tümden
    // anlamsız kılardı ve bu, gözden kaçması en kolay hata.
    foreach ($questions as $question) {
        expect($question)->not->toHaveKey('answer_key');
        expect(json_encode($question))->not->toContain('answer_key');
    }
});

it('yalnızca hızlı tipleri sorar', function (): void {
    [, $questions] = startPlacement(placementToken());

    // Sürükle-bırak tipleri ilk 90 saniyede arayüz öğretmeye kalkar;
    // ölçülen şey bilgi değil, dokunma becerisi olurdu.
    foreach ($questions as $question) {
        expect($question['type'])->toBeIn([
            'multiple_choice', 'true_false', 'fill_blank', 'numeric_input',
        ]);
    }
});

it('12 soruyu aşmaz ve birden fazla dersten sorar', function (): void {
    [, $questions] = startPlacement(placementToken());

    expect(count($questions))->toBeLessThanOrEqual(12);
    expect(count($questions))->toBeGreaterThan(0);

    // Tek derse yığılan bir test, öğrencinin güçlü olduğu dersi hiç
    // sormadan onu başa gönderirdi.
    $courses = array_unique(array_column($questions, 'course_name'));
    expect(count($courses))->toBeGreaterThan(1);
});

it('yarıda kalan test AYNI sorularla devam eder', function (): void {
    $token = placementToken();
    [$firstId, $firstQuestions] = startPlacement($token);

    $again = actingWithToken($token)->postJson('/api/v1/placement')->assertOk();

    // Yeniden başlatmak soruyu değiştirseydi, zor soruya düşen öğrenci
    // uygulamayı kapatıp açarak onu eleyebilirdi.
    expect($again->json('data.placement.id'))->toBe($firstId);
    expect($again->json('data.placement.questions'))->toBe($firstQuestions);
});

it('sınav kaydı olmayan kullanıcı testi başlatamaz', function (): void {
    app('auth')->forgetGuards();

    $token = test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'kayitsiz', 'platform' => 'ios',
    ])->json('data.token');

    actingWithToken($token)->postJson('/api/v1/placement')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'NO_ENROLLMENT');
});

// ---------------------------------------------------------- yerleştirme

it('başarısız test hiçbir adım AÇMAZ', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    $result = actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 0.0)])
        ->assertOk()
        ->json('data.result');

    foreach ($result['courses'] as $course) {
        expect($course['skipped_nodes'])->toBe(0);
        expect($course['level'])->toBe('bastan');
    }

    expect(UserNodeProgress::query()->whereNotNull('placed_out_at')->count())->toBe(0);
});

it('başarılı test adımları açar ve MUAFİYET olarak damgalar', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    $result = actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 1.0)])
        ->assertOk()
        ->json('data.result');

    expect($result['correct'])->toBe($result['total']);

    $skipped = array_sum(array_column($result['courses'], 'skipped_nodes'));
    expect($skipped)->toBeGreaterThan(0);

    $placedOut = UserNodeProgress::query()->whereNotNull('placed_out_at')->get();

    expect($placedOut)->not->toBeEmpty();

    foreach ($placedOut as $progress) {
        // Kilidi açılmış olması çalışılmış olmasıyla aynı şey değil;
        // damga olmadan ünite yüzdesi yapılmamış işi yapılmış sayardı.
        expect($progress->completed)->toBeTrue();
        expect($progress->attempts)->toBe(0);
        expect($progress->wasPlacedOut())->toBeTrue();
    }
});

it('ünite sınavını ve hızlı tekrarı ASLA atlatmaz', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 1.0)])
        ->assertOk();

    $types = UserNodeProgress::query()
        ->whereNotNull('placed_out_at')
        ->join('unit_nodes', 'unit_nodes.id', '=', 'user_node_progress.unit_node_id')
        ->pluck('unit_nodes.node_type')
        ->unique()
        ->all();

    /*
     | Sınav, öğrencinin kendi seviyesini kendi görmesi için duruyor;
     | atlatılırsa seviye testinin sonucu sorgulanamaz olurdu. Hızlı
     | tekrar ise aralıklı tekrar kuyruğunu besliyor — atlanırsa kuyruk
     | boş başlar ve tekrar sistemi hiç çalışmaz.
     */
    expect($types)->not->toContain('unit_challenge');
    expect($types)->not->toContain('quick_review');
});

it('yalnızca İLK üniteyi atlatır', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 1.0)])
        ->assertOk();

    $unitCounts = UserNodeProgress::query()
        ->whereNotNull('placed_out_at')
        ->join('units', 'units.id', '=', 'user_node_progress.unit_id')
        ->selectRaw('user_node_progress.course_id, count(distinct units.id) as unit_count')
        ->groupBy('user_node_progress.course_id')
        ->pluck('unit_count')
        ->all();

    // 12 soruluk bir test bir dersin TAMAMI hakkında konuşamaz.
    foreach ($unitCounts as $count) {
        expect((int) $count)->toBe(1);
    }
});

it('cevaplanmayan soru yanlış sayılır', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    $result = actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => []])
        ->assertOk()
        ->json('data.result');

    // Boş bırakmak da bir bilgi; testin uzunluğu sabit kalmalı.
    expect($result['correct'])->toBe(0);
    expect($result['total'])->toBeGreaterThan(0);
});

// --------------------------------------------------- atlama ve kapanış

it('test atlanabilir ve bu bir başarısızlık DEĞİLDİR', function (): void {
    $token = placementToken();
    startPlacement($token);

    actingWithToken($token)->postJson('/api/v1/placement/skip')->assertOk();

    actingWithToken($token)->getJson('/api/v1/placement')
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    // Hiçbir ders "zayıf" işaretlenmedi, hiçbir adım açılmadı — öğrenci
    // zaten varsayılan olan yerde.
    expect(UserNodeProgress::query()->whereNotNull('placed_out_at')->count())->toBe(0);
});

it('kapanmış test ikinci kez gönderilemez', function (): void {
    $token = placementToken();
    [$id] = startPlacement($token);

    actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 1.0)])
        ->assertOk();

    actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => []])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'PLACEMENT_CLOSED');
});

it('başkasının testini gönderemez', function (): void {
    $mine = placementToken('benim-seviyem');
    $theirs = placementToken('baskasinin-seviyesi');

    [$theirId] = startPlacement($theirs);

    actingWithToken($mine)
        ->postJson("/api/v1/placement/{$theirId}/submit", ['answers' => []])
        ->assertNotFound();
});

it('daha önce OYNANMIŞ adımın skorunu ezmez', function (): void {
    $token = placementToken();

    // Seviye testinden ÖNCE gerçekten oynanmış, kusursuz bir adım.
    $played = UserNodeProgress::query()->create([
        'user_id' => User::query()
            ->where('name', 'Ege')->value('id'),
        'unit_node_id' => UnitNode::query()
            ->orderBy('id')->value('id'),
        'unit_id' => UnitNode::query()
            ->orderBy('id')->value('unit_id'),
        'course_id' => Unit::query()
            ->whereKey(UnitNode::query()
                ->orderBy('id')->value('unit_id'))->value('course_id'),
        'completed' => true,
        'best_accuracy' => 100,
        'attempts' => 2,
        'is_perfect' => true,
    ]);

    [$id] = startPlacement($token);

    actingWithToken($token)
        ->postJson("/api/v1/placement/{$id}/submit", ['answers' => placementAnswers($id, 1.0)])
        ->assertOk();

    $played->refresh();

    // Öğrencinin gerçek skoru, testten türetilen tahminden daha doğrudur.
    expect($played->attempts)->toBe(2);
    expect($played->is_perfect)->toBeTrue();
    expect($played->placed_out_at)->toBeNull();
});
