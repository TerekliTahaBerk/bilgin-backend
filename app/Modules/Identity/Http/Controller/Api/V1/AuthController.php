<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Identity\Application\UseCase\AccountSummary;
use App\Modules\Identity\Application\UseCase\AuthenticateWithSocial;
use App\Modules\Identity\Application\UseCase\RegisterGuestUser;
use App\Modules\Identity\Domain\Enum\SocialProvider;
use App\Modules\Identity\Domain\Social\SocialVerificationFailed;
use App\Modules\Identity\Http\Request\GuestLoginRequest;
use App\Modules\Identity\Http\Request\SocialLoginRequest;
use App\Modules\Identity\Http\Resource\UserResource;
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
                currentUserId: $request->user()?->getAuthIdentifier() !== null
                    ? (int) $request->user()->getAuthIdentifier()
                    : null,
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

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::data(['logged_out' => true]);
    }
}
