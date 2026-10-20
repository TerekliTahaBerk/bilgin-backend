<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use App\Modules\Identity\Domain\Enum\DailyGoal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ayarlar ekranının gönderdiği kısmi güncelleme.
 *
 * Her alan `sometimes`: ekran tek bir anahtarı da gönderebilir. Tümünü
 * zorunlu kılmak, "hatırlatma saatini değiştir" isteğinin kullanıcının
 * adını da taşımasını gerektirirdi ve iki ekran arasındaki yarışta eski
 * adın geri yazılmasına yol açardı.
 */
final class UpdateSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:40'],
            'avatar_key' => ['sometimes', 'nullable', 'string', 'max:32'],

            'daily_goal_rounds' => ['sometimes', Rule::enum(DailyGoal::class)],

            'reminder_enabled' => ['sometimes', 'boolean'],
            // "HH:MM" — saniye yok, saat dilimi yok. Saat kullanıcının
            // YEREL saati; dönüşüm `timezone` ile bildirim kuyruğunda
            // yapılıyor, burada saklanan şey öğrencinin söylediği saat.
            'reminder_time' => ['sometimes', 'nullable', 'date_format:H:i'],

            // Telefonun saat dilimi değişebilir (taşınma, seyahat). Seriyi
            // ve hatırlatmayı doğru güne oturtan tek şey bu.
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.min' => 'Adın en az 2 karakter olmalı.',
            'name.max' => 'Ad en fazla 40 karakter olabilir.',
            'reminder_time.date_format' => 'Saat SS:DD biçiminde olmalı.',
        ];
    }
}
