<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Notification\ResetPasswordNotification;
use App\Modules\Identity\Notification\VerifyEmailNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
 | E-POSTA İLE KAYIT VE GİRİŞ.
 |
 | Buradaki testlerin çoğu "çalışıyor mu" değil "sızdırıyor mu" sorusunu
 | soruyor: hangi e-postanın kayıtlı olduğu, misafirin ilerlemesinin ne
 | olacağı, eski oturumların ne zaman kapandığı.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    Notification::fake();
});

function emailAuthGuestToken(string $device = 'eposta-testi'): string
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => $device, 'platform' => 'ios',
    ])->json('data.token');
}

// ---------------------------------------------------------------- kayıt

it('e-posta ile hesap açar', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'ogrenci@ornek.com',
        'password' => 'guclu-sifre-123',
        'name' => 'Ege',
    ])->assertCreated();

    expect($response->json('data.token'))->not->toBeEmpty()
        ->and($response->json('data.user.is_guest'))->toBeFalse()
        ->and($response->json('data.user.name'))->toBe('Ege');

    $user = User::query()->where('email', 'ogrenci@ornek.com')->firstOrFail();

    // Şifre DÜZ METİN saklanmamalı.
    expect($user->password)->not->toBe('guclu-sifre-123')
        ->and(Hash::check('guclu-sifre-123', (string) $user->password))->toBeTrue();
});

it('doğrulama e-postası gönderir', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'yeni@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    Notification::assertSentTo(
        User::query()->where('email', 'yeni@ornek.com')->firstOrFail(),
        VerifyEmailNotification::class,
    );
});

it('e-posta küçük harfe indirilir', function (): void {
    // Aksi hâlde aynı kişi "Ali@x.com" ve "ali@x.com" ile iki hesap açar.
    $this->postJson('/api/v1/auth/register', [
        'email' => '  Ogrenci@Ornek.COM  ', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    expect(User::query()->where('email', 'ogrenci@ornek.com')->exists())->toBeTrue();
});

it('zayıf şifre reddedilir', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'a@ornek.com', 'password' => 'kisa',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'b@ornek.com', 'password' => 'yalnizcaharfler',
    ])->assertStatus(422);
});

it('aynı e-posta ikinci kez kullanılamaz', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'ayni@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/register', [
        'email' => 'ayni@ornek.com', 'password' => 'baska-sifre-456',
    ])->assertStatus(409)->assertJsonPath('error.code', 'EMAIL_TAKEN');
});

// ------------------------------------------------- misafir yükseltme

it('MİSAFİR İLERLEMESİ korunur', function (): void {
    $token = emailAuthGuestToken();

    $this->withToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ])->assertCreated();

    $guestId = User::query()->where('is_guest', true)->latest('id')->value('id');

    // Misafire XP verelim ki kaybedilecek bir şey olsun.
    DB::table('xp_ledger')->insert([
        'user_id' => $guestId, 'amount' => 250, 'source_type' => 'test',
        'source_id' => $guestId, 'counts_for_league' => true, 'awarded_at' => now(),
    ]);

    app('auth')->forgetGuards();

    $response = $this->withToken($token)->postJson('/api/v1/auth/register', [
        'email' => 'misafir@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    // AYNI hesap kalıcıya çevrilmeli — yeni hesap açılmamalı.
    expect($response->json('data.user.id'))
        ->toBe(User::query()->find($guestId)?->uuid)
        ->and(User::query()->find($guestId)?->is_guest)->toBeFalse()
        ->and(DB::table('xp_ledger')->where('user_id', $guestId)->sum('amount'))->toBe(250)
        // Kayıt öncesi seçtiği alan da duruyor.
        ->and($response->json('data.user.enrollment.exam_variant.field'))->toBe('say');
});

it('misafirin kendi girdiği ad EZİLMEZ', function (): void {
    $token = emailAuthGuestToken();

    $this->withToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11', 'name' => 'Deniz',
    ])->assertCreated();

    app('auth')->forgetGuards();

    $response = $this->withToken($token)->postJson('/api/v1/auth/register', [
        'email' => 'deniz@ornek.com', 'password' => 'guclu-sifre-123', 'name' => 'Başka',
    ])->assertCreated();

    // Ligde görünen ad kullanıcının tercihi; form onu ezmemeli.
    expect($response->json('data.user.name'))->toBe('Deniz');
});

it('ilerlemeli misafir, dolu bir e-postaya kaydolamaz — KARAR KULLANICININ', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'dolu@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    $token = emailAuthGuestToken('ikinci-cihaz');
    $guestId = User::query()->where('is_guest', true)->latest('id')->value('id');

    DB::table('xp_ledger')->insert([
        'user_id' => $guestId, 'amount' => 120, 'source_type' => 'test',
        'source_id' => $guestId, 'counts_for_league' => true, 'awarded_at' => now(),
    ]);

    app('auth')->forgetGuards();

    // Sessizce birini seçmek kullanıcının çalışmasını kaybettirirdi.
    $this->withToken($token)->postJson('/api/v1/auth/register', [
        'email' => 'dolu@ornek.com', 'password' => 'baska-sifre-456',
    ])->assertStatus(409)->assertJsonPath('error.code', 'PROGRESS_CONFLICT');

    expect(DB::table('xp_ledger')->where('user_id', $guestId)->sum('amount'))->toBe(120);
});

it('kalıcı hesapla ikinci kez kaydolunamaz', function (): void {
    $token = $this->postJson('/api/v1/auth/register', [
        'email' => 'kalici@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated()->json('data.token');

    app('auth')->forgetGuards();

    $this->withToken($token)->postJson('/api/v1/auth/register', [
        'email' => 'ikinci@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_REGISTERED');
});

// ---------------------------------------------------------------- giriş

it('doğru şifreyle giriş yapılır', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'giris@ornek.com', 'password' => 'guclu-sifre-123', 'name' => 'Ada',
    ])->assertCreated();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'giris@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertOk()->assertJsonPath('data.user.name', 'Ada');
});

it('OLMAYAN e-posta ile YANLIŞ şifre AYNI hatayı döner', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'var@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    app('auth')->forgetGuards();
    $yanlisSifre = $this->postJson('/api/v1/auth/login', [
        'email' => 'var@ornek.com', 'password' => 'tamamen-yanlis-9',
    ])->assertStatus(422);

    app('auth')->forgetGuards();
    $olmayanHesap = $this->postJson('/api/v1/auth/login', [
        'email' => 'hic-yok@ornek.com', 'password' => 'tamamen-yanlis-9',
    ])->assertStatus(422);

    // Farklı yanıt, hangi e-postanın kayıtlı olduğunu sızdırırdı.
    expect($yanlisSifre->json('error.code'))->toBe('INVALID_CREDENTIALS')
        ->and($olmayanHesap->json('error.code'))->toBe('INVALID_CREDENTIALS')
        ->and($yanlisSifre->json('error.message'))->toBe($olmayanHesap->json('error.message'));
});

it('misafir hesaba şifreyle girilemez', function (): void {
    emailAuthGuestToken();

    app('auth')->forgetGuards();

    // Misafirin şifresi yok; `whereNotNull('password')` onu dışarıda tutuyor.
    $this->postJson('/api/v1/auth/login', [
        'email' => '', 'password' => 'herhangi-sifre-1',
    ])->assertStatus(422);
});

// ------------------------------------------------------ şifre sıfırlama

it('şifre sıfırlama bağlantısı gönderir', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'sifirla@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'sifirla@ornek.com',
    ])->assertOk();

    Notification::assertSentTo(
        User::query()->where('email', 'sifirla@ornek.com')->firstOrFail(),
        ResetPasswordNotification::class,
    );
});

it('OLMAYAN e-posta için de AYNI yanıt döner', function (): void {
    // Farklı yanıt, hangi adreslerin sistemde olduğunu sorgulayan bir araç
    // yaratırdı.
    $this->postJson('/api/v1/auth/register', [
        'email' => 'kayitli@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    app('auth')->forgetGuards();
    $kayitli = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'kayitli@ornek.com']);

    app('auth')->forgetGuards();
    $kayitsiz = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'yok@ornek.com']);

    expect($kayitli->status())->toBe($kayitsiz->status())
        ->and($kayitli->json('data.message'))->toBe($kayitsiz->json('data.message'));

    Notification::assertCount(2); // kayıt doğrulaması + sıfırlama; kayıtsıza hiçbir şey
});

it('şifre sıfırlanınca ESKİ OTURUMLAR kapanır', function (): void {
    $eskiToken = $this->postJson('/api/v1/auth/register', [
        'email' => 'oturum@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated()->json('data.token');

    $user = User::query()->where('email', 'oturum@ornek.com')->firstOrFail();

    app('auth')->forgetGuards();
    $this->withToken($eskiToken)->getJson('/api/v1/me')->assertOk();

    $resetToken = Password::broker()->createToken($user);

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'oturum@ornek.com',
        'token' => $resetToken,
        'password' => 'yepyeni-sifre-456',
    ])->assertOk();

    // Sıfırlamanın en yaygın sebebi hesabın ele geçirilmesi; eski token'ı
    // geçerli bırakmak saldırganı içeride tutmak olurdu.
    app('auth')->forgetGuards();
    $this->withToken($eskiToken)->getJson('/api/v1/me')->assertUnauthorized();

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/login', [
        'email' => 'oturum@ornek.com', 'password' => 'yepyeni-sifre-456',
    ])->assertOk();
});

it('sıfırlama token\'ı TEK KULLANIMLIK', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'tek@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    $user = User::query()->where('email', 'tek@ornek.com')->firstOrFail();
    $token = Password::broker()->createToken($user);

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'tek@ornek.com', 'token' => $token, 'password' => 'birinci-sifre-1',
    ])->assertOk();

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'tek@ornek.com', 'token' => $token, 'password' => 'ikinci-sifre-2',
    ])->assertStatus(422)->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');
});

it('uydurma sıfırlama token\'ı reddedilir', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'email' => 'uydurma@ornek.com', 'password' => 'guclu-sifre-123',
    ])->assertCreated();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'uydurma@ornek.com', 'token' => 'uydurma-token', 'password' => 'yeni-sifre-1',
    ])->assertStatus(422);
});

it('auth.optional arkasındaki kimlik AÇIK guard ile okunur', function (): void {
    // Gerileme koruması. `$request->user()` varsayılan guard'a (web) bakıyor
    // ve `auth.optional`ın çözdüğü sanctum kullanıcısını göremeyebiliyor;
    // ölçtük, bir senaryoda doğru değer bir başkasında null dönüyor.
    //
    // Yanlış ifadeye dönülürse misafirin ilerlemesi sessizce yeni bir
    // hesapta kaybolur. Sessiz olduğu için de kimse fark etmez — bu test
    // onun yerine fark ediyor.
    $source = file_get_contents(
        app_path('Modules/Identity/Http/Controller/Api/V1/AuthController.php')
    );

    expect($source)->toContain('optionalUserId')
        ->and($source)->not->toContain('$request->user()?->getAuthIdentifier()');
});
