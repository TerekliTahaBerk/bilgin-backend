<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notification;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * E-posta doğrulama bağlantısı.
 *
 * Kuyruğa giriyor: kayıt isteğinin yanıt süresi posta sağlayıcısının
 * hızına bağlı olmamalı.
 */
final class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        // İmzalı ve SÜRELİ bağlantı: bağlantı sızsa bile 24 saat sonra
        // işe yaramaz ve içeriği değiştirilemez.
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addDay(),
            ['id' => $notifiable->getKey(), 'hash' => sha1((string) $notifiable->email)],
        );

        return (new MailMessage)
            ->subject('Tekrarla · E-postanı doğrula')
            ->greeting('Merhaba'.($notifiable->name !== null ? ' '.$notifiable->name : '').'!')
            ->line('Hesabını doğrulamak için aşağıdaki düğmeye dokun.')
            ->action('E-postamı doğrula', $url)
            ->line('Bu bağlantı 24 saat geçerli.')
            ->salutation('Kolay gelsin, Tekrarla');
    }
}
