<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Social\JwksProvider;
use App\Modules\Identity\Infrastructure\Eloquent\Model\AuthIdentity;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Database\Seeders\DatabaseSeeder;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;

/*
 | Apple / Google giriş ve misafir hesabın kalıcıya çevrilmesi.
 |
 | Testler GERÇEK doğrulama kodunu koşuyor: burada kendi RSA anahtar çiftimizi
 | üretip token'ı onunla imzalıyor, açık anahtarı JWKS sağlayıcısı üzerinden
 | veriyoruz. Doğrulayıcıyı sahtelemek, tam da en kritik kodu (imza, aud, exp
 | kontrolü) test dışı bırakırdı.
 */

beforeEach(function (): void {
    config([
        'tekrarla.social.apple.audiences' => ['app.tekrarla.ios'],
        'tekrarla.social.google.audiences' => ['tekrarla-android.apps.googleusercontent.com'],
    ]);

    $this->seed(DatabaseSeeder::class);

    // Test için tek kullanımlık anahtar çifti.
    $this->keyPair = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    $details = openssl_pkey_get_details($this->keyPair);

    // JWKS sağlayıcısını açık anahtarımızla değiştiriyoruz; doğrulama
    // kodunun kendisine dokunmuyoruz.
    $this->app->instance(JwksProvider::class, new class($details) implements JwksProvider
    {
        public function __construct(private array $details) {}

        public function keys(string $url): array
        {
            return ['keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => rtrim(strtr(base64_encode($this->details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($this->details['rsa']['e']), '+/', '-_'), '='),
            ]]];
        }
    });
});

function issueToken(array $claims = [], ?int $expiresIn = 3600): string
{
    return JWT::encode(array_merge([
        'iss' => 'https://appleid.apple.com',
        'aud' => 'app.tekrarla.ios',
        'sub' => 'apple-user-001',
        'email' => 'ege@example.com',
        'email_verified' => 'true',
        'iat' => time(),
        'exp' => time() + ($expiresIn ?? 3600),
    ], $claims), test()->keyPair, 'RS256', 'test-key');
}

function socialLogin(array $payload = [], ?string $token = null)
{
    app('auth')->forgetGuards();

    $request = test();

    if ($token !== null) {
        $request = $request->withToken($token);
    }

    return $request->postJson('/api/v1/auth/social', array_merge([
        'provider' => 'apple',
        'identity_token' => issueToken(),
    ], $payload));
}

function guestWithProgress(string $device = 'misafir-cihaz'): array
{
    app('auth')->forgetGuards();

    $token = test()->postJson('/api/v1/auth/guest', [
        'device_identifier' => $device, 'platform' => 'ios',
    ])->json('data.token');

    test()->withToken($token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'name' => 'Ege',
    ]);

    return ['token' => $token, 'user' => User::query()->latest('id')->firstOrFail()];
}

it('geçerli Apple token\'ıyla yeni hesap açar', function (): void {
    $response = socialLogin()->assertCreated();

    expect($response->json('data.account_created'))->toBeTrue()
        ->and($response->json('data.user.is_guest'))->toBeFalse()
        ->and($response->json('data.token'))->not->toBeEmpty();

    expect(AuthIdentity::query()->where('provider', 'apple')->count())->toBe(1);
});

it('imzası geçersiz token\'ı reddeder', function (): void {
    // Başka bir anahtarla imzalanmış token.
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    $forged = JWT::encode([
        'iss' => 'https://appleid.apple.com', 'aud' => 'app.tekrarla.ios',
        'sub' => 'sahte', 'iat' => time(), 'exp' => time() + 3600,
    ], $other, 'RS256', 'test-key');

    socialLogin(['identity_token' => $forged])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'SOCIAL_VERIFICATION_FAILED');

    expect(User::query()->count())->toBe(0);
});

it('başka uygulama için üretilmiş token\'ı reddeder', function (): void {
    // Kabul edilseydi o uygulamanın kullanıcıları bizim hesaplara girerdi.
    $response = socialLogin(['identity_token' => issueToken(['aud' => 'baska.uygulama'])])
        ->assertUnauthorized();

    expect($response->json('error.message'))->toContain('başka bir uygulama');
});

it('süresi dolmuş token\'ı reddeder', function (): void {
    socialLogin(['identity_token' => issueToken(expiresIn: -60)])->assertUnauthorized();
});

it('beklenmeyen kaynaklı token\'ı reddeder', function (): void {
    socialLogin(['identity_token' => issueToken(['iss' => 'https://kotu-adam.example'])])
        ->assertUnauthorized();
});

it('sağlayıcı yapılandırılmamışsa giriş kapalıdır', function (): void {
    // "Yapılandırılmamışsa herkesi kabul et" davranışı olmamalı.
    config(['tekrarla.social.apple.audiences' => []]);

    socialLogin()->assertUnauthorized();
});

it('misafirin ilerlemesini kalıcı hesaba taşır', function (): void {
    $guest = guestWithProgress();

    $response = socialLogin(token: $guest['token'])->assertOk();

    expect($response->json('data.guest_upgraded'))->toBeTrue()
        ->and($response->json('data.user.id'))->toBe($guest['user']->uuid)
        ->and($response->json('data.user.is_guest'))->toBeFalse()
        // Onboarding'de girilen ad sağlayıcıdan gelen adla EZİLMEZ.
        ->and($response->json('data.user.name'))->toBe('Ege')
        ->and($response->json('data.user.enrollment.exam_variant.code'))->toBe('yks_say');

    // Yeni kullanıcı açılmadı, mevcut olan yükseltildi.
    expect(User::query()->count())->toBe(1);
});

it('aynı kimlikle ikinci giriş mevcut hesaba bağlanır', function (): void {
    socialLogin()->assertCreated();

    $response = socialLogin()->assertOk();

    expect($response->json('data.account_created'))->toBeFalse()
        ->and(User::query()->count())->toBe(1);
});

it('çakışmada hiçbir ilerlemeyi sessizce silmez', function (): void {
    // Önce bu Apple kimliğiyle bir hesap var.
    socialLogin()->assertCreated();

    // Sonra başka bir cihazda misafir olarak ilerleme kaydedilmiş.
    $guest = guestWithProgress('ikinci-cihaz');

    $response = socialLogin(token: $guest['token'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ACCOUNT_ALREADY_LINKED');

    // Kullanıcı hangi ilerlemenin kaybolacağını görebilmeli.
    expect($response->json('error.details.guest_account.is_guest'))->toBeTrue()
        ->and($response->json('error.details.existing_account.is_guest'))->toBeFalse()
        ->and($response->json('error.details.guest_account'))->toHaveKeys(['total_xp', 'level', 'current_streak']);

    // İki hesap da duruyor; hiçbir şey silinmedi.
    expect(User::query()->count())->toBe(2);
});

it('açık tercihle mevcut hesaba geçer', function (): void {
    socialLogin()->assertCreated();
    $guest = guestWithProgress('ucuncu-cihaz');

    $response = socialLogin(['discard_guest_progress' => true], token: $guest['token'])->assertOk();

    // Mevcut hesaba girildi; misafir hesabı olduğu gibi duruyor (silinmiyor,
    // yalnızca terk ediliyor) — destek gerekirse geri dönülebilir.
    expect($response->json('data.user.id'))->not->toBe($guest['user']->uuid)
        ->and(User::query()->count())->toBe(2);
});

it('Google girişi de çalışır', function (): void {
    $token = JWT::encode([
        'iss' => 'https://accounts.google.com',
        'aud' => 'tekrarla-android.apps.googleusercontent.com',
        'sub' => 'google-user-001',
        'email' => 'ege@gmail.com',
        'email_verified' => true,
        'name' => 'Ege',
        'iat' => time(), 'exp' => time() + 3600,
    ], $this->keyPair, 'RS256', 'test-key');

    $response = socialLogin(['provider' => 'google', 'identity_token' => $token])->assertCreated();

    expect($response->json('data.user.name'))->toBe('Ege');

    expect(DB::table('users')->where('email', 'ege@gmail.com')->whereNotNull('email_verified_at')->exists())
        ->toBeTrue();
});

it('doğrulanmamış e-postayı hesaba yazmaz', function (): void {
    // Apple "e-postamı gizle" seçeneğinde takma adres verir; doğrulanmamış
    // e-postayla hesap eşleştirmek, başkasının hesabını ele geçirmenin yolu.
    socialLogin(['identity_token' => issueToken(['email_verified' => 'false'])])->assertCreated();

    $user = User::query()->firstOrFail();

    expect($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    // Kimlik kaydında yine de saklanır — destek ekibi için.
    expect(AuthIdentity::query()->firstOrFail()->email)->toBe('ege@example.com');
});
