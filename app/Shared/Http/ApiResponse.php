<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Http\JsonResponse;

/**
 * Tek yanıt zarfı: { data, meta } / { error }.
 *
 * İstemci hata KODUNA göre dallanır, mesaja göre değil. Mesaj kullanıcıya
 * gösterilebilir Türkçe metindir ve sunucu deploy'u olmadan değişebilir.
 */
final class ApiResponse
{
    /**
     * JSON_PRESERVE_ZERO_FRACTION: ondalık alanlar HER ZAMAN ondalık serileşir.
     *
     * Bu bayrak olmadan 106.0 → "106", 106.5 → "106.5" olur. Dart bunları
     * farklı tiplere (int / double) çözer ve istemcideki `as double` cast'i
     * yalnızca tam sayıya denk gelen sonuçlarda patlar — üretimde ancak
     * bir öğrencinin neti tam sayı çıktığında ortaya çıkan bir hata.
     */
    private const JSON_FLAGS = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE;

    /** @param  array<string, mixed>  $meta */
    public static function data(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => ['server_time' => now()->toIso8601String()] + $meta,
        ], $status, [], self::JSON_FLAGS);
    }

    /** @param  array<string, mixed>  $details */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
    ): JsonResponse {
        return response()->json([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ], static fn ($v): bool => $v !== null),
        ], $status, [], self::JSON_FLAGS);
    }
}
