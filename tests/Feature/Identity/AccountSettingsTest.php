<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Model\Device;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Infrastructure\Eloquent\Model\UserEnrollment;
use App\Modules\Identity\Infrastructure\Eloquent\Model\UserProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | AYARLAR, HESAP SİLME, ÇOKLU KAYIT VE CİHAZ.
 |
 | Dördü de aynı soruyu farklı yerlerden soruyor: kullanıcının kendi
 | hesabı üzerindeki denetimi gerçekten var mı, yoksa ekranda mı duruyor.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

/** Kayıt akışını tamamlamış bir öğrenci üretir. */
function onboardedToken(string $device = 'ayarlar-testi'): string
{
    app('auth')->forgetGuards();

    $token = test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => $device, 'platform' => 'ios',
    ])->json('data.token');

    actingWithToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks',
        'field' => 'say',
        'name' => 'Ege',
        'daily_goal_rounds' => 3,
    ])->assertCreated();

    return $token;
}

// ------------------------------------------------------------- ayarlar

it('yalnızca gönderilen alanı değiştirir', function (): void {
    $token = onboardedToken();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_time' => '07:30'])
        ->assertOk()
        // Ad gönderilmedi; dokunulmamış olmalı. Kısmi güncellemenin
        // bozulduğu gün bu satır, "Ege" yerine null görecek.
        ->assertJsonPath('data.name', 'Ege')
        ->assertJsonPath('data.profile.reminder_time', '07:30');
});

it('hatırlatma açılırken saat yoksa varsayılana düşer', function (): void {
    $token = onboardedToken();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_enabled' => false, 'reminder_time' => null])
        ->assertOk();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_enabled' => true])
        ->assertOk()
        // Açık ama saatsiz bir hatırlatma hiç kurulamaz.
        ->assertJsonPath('data.profile.reminder_time', '19:00');
});

it('hatırlatma kapatılınca saat SİLİNMEZ', function (): void {
    $token = onboardedToken();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_time' => '21:15'])
        ->assertOk();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_enabled' => false])
        ->assertOk()
        // Yarın tekrar açtığında eski saatini bulmalı.
        ->assertJsonPath('data.profile.reminder_time', '21:15');
});

it('geçersiz saat biçimini reddeder', function (): void {
    $token = onboardedToken();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['reminder_time' => '25:99'])
        ->assertStatus(422);
});

it('avatar null\'a çekilebilir', function (): void {
    $token = onboardedToken();

    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['avatar_key' => 'kulaklik'])
        ->assertOk();

    // `isset` ile yazılmış bir güncelleme burada sessizce hiçbir şey
    // yapmazdı: null, "değiştirme" ile aynı şey değil.
    actingWithToken($token)
        ->patchJson('/api/v1/me/settings', ['avatar_key' => null])
        ->assertOk()
        ->assertJsonPath('data.avatar_key', null);
});

// --------------------------------------------------------- hesap silme

it('hesabı ve bağlı tüm veriyi siler', function (): void {
    $token = onboardedToken();
    $user = User::query()->where('name', 'Ege')->firstOrFail();
    $userId = $user->id;

    actingWithToken($token)->postJson('/api/v1/devices', [
        'device_identifier' => 'silme-testi', 'platform' => 'ios', 'push_token' => 'abc',
    ])->assertOk();

    actingWithToken($token)->deleteJson('/api/v1/me')->assertOk();

    // Yumuşak silme DEĞİL: satır gerçekten gitmiş olmalı.
    expect(User::withTrashed()->whereKey($userId)->exists())->toBeFalse();
    expect(UserProfile::query()->where('user_id', $userId)->exists())->toBeFalse();
    expect(UserEnrollment::query()->where('user_id', $userId)->exists())->toBeFalse();
    expect(Device::query()->where('user_id', $userId)->exists())->toBeFalse();
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $userId)->exists())->toBeFalse();
});

it('silinen hesabın jetonu artık çalışmaz', function (): void {
    $token = onboardedToken();

    actingWithToken($token)->deleteJson('/api/v1/me')->assertOk();

    actingWithToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('silinen e-posta yeniden kullanılabilir', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'geri@ornek.com', 'password' => 'guclu-sifre-123', 'name' => 'Ada',
    ])->assertCreated();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'geri@ornek.com', 'password' => 'guclu-sifre-123',
    ])->json('data.token');

    actingWithToken($token)->deleteJson('/api/v1/me')->assertOk();

    /*
     | KVKK'nın asıl sınavı bu. Yumuşak silmede satır yerinde kalır,
     | `users.email` tekil dizini bu kaydı tutar ve kullanıcı kendi
     | adresiyle geri dönemez — "sildik" demek doğru olmazdı.
     */
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/register', [
        'email' => 'geri@ornek.com', 'password' => 'baska-sifre-456', 'name' => 'Ada',
    ])->assertCreated();
});

// ---------------------------------------------------------- çoklu kayıt

it('kayıtları listeler ve birincili işaretler', function (): void {
    $token = onboardedToken();

    $response = actingWithToken($token)->getJson('/api/v1/me/enrollments')->assertOk();

    expect($response->json('data.enrollments'))->toHaveCount(1);
    expect($response->json('data.enrollments.0.is_primary'))->toBeTrue();
    expect($response->json('data.enrollments.0.exam.code'))->toBe('yks');
});

it('aynı sınava ikinci kez kaydolmak satır ÇOĞALTMAZ', function (): void {
    $token = onboardedToken();

    actingWithToken($token)->postJson('/api/v1/me/enrollments', [
        'exam_code' => 'yks', 'field' => 'ea',
    ])->assertCreated();

    actingWithToken($token)->postJson('/api/v1/me/enrollments', [
        'exam_code' => 'yks', 'field' => 'ea',
    ])->assertCreated();

    $enrollments = actingWithToken($token)->getJson('/api/v1/me/enrollments')->json('data.enrollments');

    // İki farklı varyant (say + ea) var ama 'ea' bir kez.
    $codes = array_column(array_column($enrollments, 'exam_variant'), 'code');
    expect(array_count_values($codes)['yks_ea'])->toBe(1);
});

it('her zaman TEK bir birincil kayıt vardır', function (): void {
    $token = onboardedToken();

    actingWithToken($token)->postJson('/api/v1/me/enrollments', [
        'exam_code' => 'yks', 'field' => 'soz',
    ])->assertCreated();

    $enrollments = actingWithToken($token)->getJson('/api/v1/me/enrollments')->json('data.enrollments');
    $primaries = array_filter($enrollments, static fn (array $e): bool => $e['is_primary']);

    // Birden fazla birincil, ders listesinin hangi sınava ait olduğunu
    // belirsiz bırakır ve sorgu sırasına göre değişir.
    expect($primaries)->toHaveCount(1);
    expect(array_values($primaries)[0]['exam_variant']['code'])->toBe('yks_soz');
});

it('başkasının kaydına geçemez', function (): void {
    $mine = onboardedToken('benim-cihazim');
    $theirs = onboardedToken('baskasinin-cihazi');

    $theirId = actingWithToken($theirs)
        ->getJson('/api/v1/me/enrollments')
        ->json('data.enrollments.0.id');

    // 403 değil 404: o kaydın VARLIĞI bile sızdırılmamalı.
    actingWithToken($mine)
        ->patchJson("/api/v1/me/enrollments/{$theirId}/primary")
        ->assertNotFound();
});

it('olmayan sınava kaydolmayı reddeder', function (): void {
    $token = onboardedToken();

    // 500 değil 422: yanlış sınav kodu bir sunucu arızası değil.
    actingWithToken($token)
        ->postJson('/api/v1/me/enrollments', ['exam_code' => 'olmayan', 'field' => 'say'])
        ->assertStatus(422);
});

// --------------------------------------------------------------- cihaz

it('bildirim izni verilmese de cihazı kaydeder', function (): void {
    $token = onboardedToken();

    actingWithToken($token)->postJson('/api/v1/devices', [
        'device_identifier' => 'izinsiz-cihaz', 'platform' => 'android',
    ])->assertOk()
        // "İzin verilmedi" ile "henüz sorulmadı" farklı durumlar; cihaz
        // kaydı olmadan ikisi ayırt edilemezdi.
        ->assertJsonPath('data.push_enabled', false);
});

it('aynı cihaz için ikinci satır açmaz', function (): void {
    $token = onboardedToken();

    foreach (['ilk-jeton', 'yenilenmis-jeton'] as $pushToken) {
        actingWithToken($token)->postJson('/api/v1/devices', [
            'device_identifier' => 'tek-telefon',
            'platform' => 'ios',
            'push_token' => $pushToken,
        ])->assertOk();
    }

    $devices = Device::query()->where('device_identifier', 'tek-telefon')->get();

    // Telefon yeniden kurulduğunda jeton değişir, kimlik aynı kalır.
    // İki satır, aynı kişiye iki bildirim demekti.
    expect($devices)->toHaveCount(1);
    expect($devices->first()?->push_token)->toBe('yenilenmis-jeton');
});
