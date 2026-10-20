<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Identity\Application\UseCase\DeleteAccount;
use App\Modules\Identity\Application\UseCase\UpdateSettings;
use App\Modules\Identity\Http\Request\UpdateSettingsRequest;
use App\Modules\Identity\Http\Resource\UserResource;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = User::query()
            ->with(['profile', 'enrollments.examVariant'])
            ->findOrFail($this->userId($request));

        return ApiResponse::data(new UserResource($user));
    }

    /**
     * PATCH /v1/me/settings — ayarlar ekranının tek ucu.
     *
     * Yanıt tam `UserResource`: istemci gönderdiği alanı kendi durumuna
     * yazmak yerine sunucunun söylediğini yazsın. İkisi ayrışırsa
     * (ör. ad kırpıldıysa) ekranda doğru olan sunucudaki değerdir.
     */
    public function updateSettings(
        UpdateSettingsRequest $request,
        UpdateSettings $update,
    ): JsonResponse {
        $user = $update($this->userId($request), $request->validated());

        return ApiResponse::data(new UserResource($user));
    }

    /**
     * DELETE /v1/me — hesabı ve tüm kişisel veriyi siler (KVKK md. 7).
     *
     * Onay İSTEMCİDE alınıyor ve burada ikinci kez sorulmuyor: bu uca
     * ulaşan istek zaten kullanıcının jetonunu taşıyor ve silme ekranı
     * yazarak onaylatıyor. Sunucuda ayrıca şifre istemek, şifresi olmayan
     * misafir ve sosyal girişli kullanıcıları hesaplarına hapsederdi.
     */
    public function destroy(Request $request, DeleteAccount $delete): JsonResponse
    {
        $delete($this->userId($request));

        return ApiResponse::data(['deleted' => true]);
    }
}
