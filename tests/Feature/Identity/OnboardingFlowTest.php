<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Database\Seeders\DatabaseSeeder;

/*
 | Onboarding'in uçtan uca akışı: misafir giriş → sınav/alan seçimi →
 | ders listesi. Tasarımdaki ilk üç ekranın sözleşmesi burada kilitli.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

function guestToken(string $device = 'test-cihaz'): string
{
    return test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => $device,
        'platform' => 'ios',
    ])->json('data.token');
}

it('kayıt ekranı olmadan misafir hesap açar', function (): void {
    $response = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'cihaz-1',
        'platform' => 'ios',
        'app_version' => '1.0.0',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.is_guest', true)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id']]]);

    expect(User::query()->count())->toBe(1);
});

it('aynı cihazdan ikinci giriş yeni hesap açmaz', function (): void {
    // Uygulamayı kapatıp açan kullanıcı ilerlemesini kaybetmemeli.
    $first = $this->postJson('/api/v1/auth/guest', ['device_identifier' => 'cihaz-1', 'platform' => 'ios']);
    $second = $this->postJson('/api/v1/auth/guest', ['device_identifier' => 'cihaz-1', 'platform' => 'ios']);

    expect($second->json('data.user.id'))->toBe($first->json('data.user.id'))
        ->and(User::query()->count())->toBe(1);
});

it('sınav kodu ve alanı tek varyanta çözer', function (): void {
    $token = guestToken();

    $this->withToken($token)
        ->postJson('/api/v1/onboarding', [
            'exam_code' => 'yks',
            'field' => 'say',
            'grade' => '11',
            'target_exam_year' => 2027,
            'name' => 'Ege',
            'daily_goal_rounds' => 3,
            'reminder_time' => '20:00',
        ])
        ->assertCreated()
        ->assertJsonPath('data.user.enrollment.exam_variant.code', 'yks_say')
        ->assertJsonPath('data.user.profile.grade', '11')
        ->assertJsonPath('data.next_step', 'placement');
});

it('belirsiz alan geçerli bir varyanttır', function (): void {
    // "Henüz bilmiyorum" bir istisna değil, gerçek bir varyant.
    $token = guestToken();

    $this->withToken($token)
        ->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'undecided'])
        ->assertCreated()
        ->assertJsonPath('data.user.enrollment.exam_variant.code', 'yks_undecided');

    $courses = $this->withToken($token)->getJson('/api/v1/me/courses')->json('data.sections');

    expect($courses)->toHaveCount(1)                 // yalnızca TYT
        ->and($courses[0]['code'])->toBe('tyt')
        ->and($courses[0]['courses'])->toHaveCount(9);
});

it('ders listesini oturum sekmelerine göre gruplar', function (): void {
    $token = guestToken();
    $this->withToken($token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $response = $this->withToken($token)->getJson('/api/v1/me/courses')->assertOk();

    expect($response->json('data.sections.0.code'))->toBe('tyt')
        ->and($response->json('data.sections.1.code'))->toBe('ayt')
        // Sayısal'da ders sırası Matematik ile başlar.
        ->and($response->json('data.sections.0.courses.0.code'))->toBe('tyt_matematik');
});

it('premium ders kilidini ve yakında etiketini bildirir', function (): void {
    $token = guestToken();
    $this->withToken($token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $tyt = collect($this->withToken($token)->getJson('/api/v1/me/courses')->json('data.sections.0.courses'));

    $din = $tyt->firstWhere('code', 'tyt_din');
    $matematik = $tyt->firstWhere('code', 'tyt_matematik');

    expect($din['locked'])->toBeTrue()
        ->and($din['lock_reason'])->toBe('PREMIUM_REQUIRED')
        ->and($matematik)->not->toHaveKey('locked');

    // Henüz yayınlanmamış ders listeden düşmez, "Yakında" görünür.
    expect($tyt->firstWhere('code', 'tyt_fizik')['placeholder_label'])->toBe('Yakında');
});

it('alan değişiminde ortak dersler aynı kayıt kalır', function (): void {
    // Ders sisteminin can alıcı iddiası: alan değiştiren öğrenci
    // TYT'de ve ortak AYT derslerinde öğrendiğini kaybetmez.
    $token = guestToken();
    $this->withToken($token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $before = collect($this->withToken($token)->getJson('/api/v1/me/courses')->json('data.sections'))
        ->flatMap(fn (array $s): array => $s['courses'])->pluck('id', 'code');

    $this->withToken($token)
        ->patchJson('/api/v1/me/enrollment', ['field' => 'ea'])
        ->assertOk()
        ->assertJsonPath('data.exam_variant.code', 'yks_ea')
        ->assertJsonPath('data.progress_preserved', true);

    $after = collect($this->withToken($token)->getJson('/api/v1/me/courses')->json('data.sections'))
        ->flatMap(fn (array $s): array => $s['courses'])->pluck('id', 'code');

    // TYT'nin tamamı ve AYT Matematik AYNI course id — içerik kopyalanmadı.
    foreach (['tyt_matematik', 'tyt_turkce', 'tyt_tarih', 'ayt_matematik'] as $code) {
        expect($after[$code])->toBe($before[$code], "{$code} kaydı değişmemeli");
    }

    // Alana özel dersler değişti.
    expect($before)->toHaveKey('ayt_fizik')
        ->and($after)->not->toHaveKey('ayt_fizik')
        ->and($after)->toHaveKey('ayt_edebiyat');
});

it('onboarding yapılmadan ders listesi istenemez', function (): void {
    $this->withToken(guestToken())
        ->getJson('/api/v1/me/courses')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ONBOARDING_REQUIRED');
});

it('kimliksiz istek reddedilir', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('geçersiz alan kodu doğrulamada yakalanır', function (): void {
    $this->withToken(guestToken())
        ->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'astroloji'])
        ->assertStatus(422);
});
