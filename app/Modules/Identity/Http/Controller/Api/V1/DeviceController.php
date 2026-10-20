<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Identity\Http\Request\RegisterDeviceRequest;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Cihaz ve bildirim jetonu kaydı.
 *
 * `devices` tablosu baştan beri vardı ama hiçbir yerden YAZILMIYORDU;
 * hatırlatma bildirimi göndermenin önündeki tek eksik buydu.
 *
 * Jeton `nullable`: kullanıcı bildirim iznini reddettiğinde de cihaz
 * kaydediliyor. "İzin verilmedi" ile "henüz sorulmadı" farklı şeyler ve
 * ikincisinde tekrar sormak meşru, birincisinde değil.
 */
final class DeviceController extends ApiController
{
    public function store(RegisterDeviceRequest $request, ClockInterface $clock): JsonResponse
    {
        $user = User::query()->findOrFail($this->userId($request));

        /*
         | Tekillik `user_id + device_identifier` üzerinde. Aynı telefon
         | yeniden kurulduğunda jeton değişir ama kimlik aynı kalır; yeni
         | satır açmak, aynı kişiye iki bildirim göndermek demekti.
         */
        $device = $user->devices()->updateOrCreate(
            ['device_identifier' => $request->string('device_identifier')->toString()],
            [
                'platform' => $request->string('platform')->toString(),
                'push_token' => $request->string('push_token')->toString() ?: null,
                'app_version' => $request->string('app_version')->toString() ?: null,
                'last_seen_at' => $clock->now(),
            ],
        );

        return ApiResponse::data([
            'device_id' => $device->id,
            'push_enabled' => $device->push_token !== null,
        ]);
    }
}
