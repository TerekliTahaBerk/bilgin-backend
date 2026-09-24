<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Identity\Application\UseCase\AccountSummary;
use App\Modules\Identity\Application\UseCase\AuthenticateWithEmail;
use App\Modules\Identity\Application\UseCase\AuthenticateWithSocial;
use App\Modules\Identity\Application\UseCase\RegisterGuestUser;
use App\Modules\Identity\Application\UseCase\RegisterWithEmail;
use App\Modules\Identity\Application\UseCase\RequestPasswordReset;
use App\Modules\Identity\Application\UseCase\ResetPassword;
use App\Modules\Identity\Domain\Enum\SocialProvider;
use App\Modules\Identity\Domain\Exception\EmailAuthException;
use App\Modules\Identity\Domain\Social\SocialVerificationFailed;
use App\Modules\Identity\Http\Request\EmailLoginRequest;
use App\Modules\Identity\Http\Request\ForgotPasswordRequest;
use App\Modules\Identity\Http\Request\GuestLoginRequest;
use App\Modules\Identity\Http\Request\RegisterRequest;
use App\Modules\Identity\Http\Request\ResetPasswordRequest;
use App\Modules\Identity\Http\Request\SocialLoginRequest;
use App\Modules\Identity\Http\Resource\UserResource;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends ApiController
{
    /**
     * Misafir giriş — onboarding'in "Başla" butonu.
     *
     * Controller ince: istek → use case → kaynak. İş kuralı burada değil.
     */
    public function guest(GuestLoginRequest $request, RegisterGuestUser $register): JsonResponse
    {
        $user = $register(
            $request->string('device_identifier')->toString(),
            $request->string('platform')->toString(),
            $request->string('app_version')->toString() ?: null,
        );

        return ApiResponse::data([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('profile', 'enrollments.examVariant')),
        ], 201);
    }

    /**
     * Apple / Google ile giriş.
     *
     * Misafir token'ıyla çağrılırsa mevcut ilerleme kalıcı hesaba taşınır.
     * Bu kimlikle zaten bir hesap varsa ve misafirin de ilerlemesi varsa
     * 409 döner — hangi ilerlemenin kaybolacağına kullanıcı karar verir.
     */
    public function social(SocialLoginRequest $request, AuthenticateWithSocial $authenticate): JsonResponse
    {
        try {
            $result = $authenticate(
                provider: SocialProvider::from($request->string('provider')->toString()),
                identityToken: $request->string('identity_token')->toString(),
                // `auth('sanctum')` AÇIKÇA yazılıyor. `$request->user()`
                // varsayılan guard'a (web) bakıyor ve `auth.optional`ın
                // çözdüğü sanctum kullanıcısını GÖREMEYEBİLİYOR — ölçtük:
                // bir senaryoda doğru değer, yakın bir başkasında null
                // dönüyor. Null dönerse misafirin ilerlemesi sessizce
                // yeni bir hesapta kaybolur; sessiz olduğu için de kimse
                // fark etmez.
                currentUserId: self::optionalUserId(),
                discardGuestProgress: $request->boolean('discard_guest_progress'),
            );
        } catch (SocialVerificationFailed $e) {
            return ApiResponse::error('SOCIAL_VERIFICATION_FAILED', $e->getMessage(), 401);
        }

        if ($result->hasConflict()) {
            return ApiResponse::error(
                'ACCOUNT_ALREADY_LINKED',
                'Bu hesapla daha önce ilerleme kaydedilmiş. Hangisiyle devam edeceğini seçmen gerekiyor.',
                409,
                [
                    'guest_account' => self::summary($result->guestAccount),
                    'existing_account' => self::summary($result->existingAccount),
                ],
            );
        }

        $user = $result->user();

        return ApiResponse::data([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('profile', 'enrollments.examVariant')),
            'account_created' => $result->isNew,
            // İstemci "ilerlemen hesabına taşındı" mesajını buna göre gösterir.
            'guest_upgraded' => $result->upgraded,
        ], $result->isNew ? 201 : 200);
    }

    /** @return array<string, mixed>|null */
    private static function summary(?AccountSummary $summary): ?array
    {
        return $summary === null ? null : [
            'id' => $summary->uuid,
            'name' => $summary->name,
            'is_guest' => $summary->isGuest,
            'total_xp' => $summary->totalXp,
            'level' => $summary->level,
            'current_streak' => $summary->currentStreak,
        ];
    }

    /**
     * E-posta ile kayıt.
     *
     * Misafir token'ıyla çağrılırsa mevcut hesap kalıcıya çevrilir ve
     * ilerleme korunur — yeni hesap açmak, uygulamayı deneyip beğenen
     * kullanıcıyı tam kaydolduğu anda sıfırlamak olurdu.
     */
    public function register(RegisterRequest $request, RegisterWithEmail $register): JsonResponse
    {
        $user = $register(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            name: $request->string('name')->toString() ?: null,
            // `auth('sanctum')` AÇIKÇA yazılıyor: `$request->user()`
            // varsayılan guard'a bakar ve token'la gelen kullanıcıyı
            // görmez. Görmezse misafirin ilerlemesi sessizce yeni bir
            // hesapta kaybolurdu.
            guestUserId: self::optionalUserId(),
        );

        return ApiResponse::data([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('profile', 'enrollments.examVariant')),
        ], 201);
    }

    /** E-posta ve şifreyle giriş. */
    public function login(EmailLoginRequest $request, AuthenticateWithEmail $authenticate): JsonResponse
    {
        $user = $authenticate(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::data([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('profile', 'enrollments.examVariant')),
        ]);
    }

    /**
     * Şifre sıfırlama bağlantısı ister.
     *
     * E-posta kayıtlı olsun olmasın AYNI yanıt döner. Farklı yanıt vermek,
     * hangi adreslerin sistemde olduğunu sorgulayan bir araç yaratırdı.
     */
    public function forgotPassword(ForgotPasswordRequest $request, RequestPasswordReset $request_): JsonResponse
    {
        $request_($request->string('email')->toString());

        return ApiResponse::data([
            'message' => 'Bu e-posta kayıtlıysa şifre sıfırlama bağlantısı gönderildi.',
        ]);
    }

    /** Şifreyi sıfırlar; tüm eski oturumlar kapanır. */
    public function resetPassword(ResetPasswordRequest $request, ResetPassword $reset): JsonResponse
    {
        $user = $reset(
            $request->string('email')->toString(),
            $request->string('token')->toString(),
            $request->string('password')->toString(),
        );

        return ApiResponse::data([
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => new UserResource($user->load('profile', 'enrollments.examVariant')),
        ]);
    }

    /**
     * E-posta doğrulama bağlantısının indiği yer.
     *
     * Hash, kullanıcının O ANKİ e-postasından üretiliyor: bağlantı
     * gönderildikten sonra e-posta değişirse eski bağlantı geçersiz olur.
     */
    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->find((int) $id);

        if ($user === null || ! hash_equals($hash, sha1((string) $user->email))) {
            throw EmailAuthException::verificationTokenInvalid();
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return ApiResponse::data(['verified' => true]);
    }

    /**
     * `auth.optional` arkasındaki kullanıcının kimliği.
     *
     * Tek yerde toplandı: iki uç da aynı soruyu soruyor ve ikisinde de
     * yanlış cevap aynı sessiz sonuca varıyor — misafirin ilerlemesinin
     * kaybolmasına.
     */
    private static function optionalUserId(): ?int
    {
        $id = auth('sanctum')->user()?->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::data(['logged_out' => true]);
    }
}
