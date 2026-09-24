<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notification;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        // Bağlantı uygulamaya derin bağlantıyla dönüyor; token'ı kullanıcının
        // elle kopyalaması istenmiyor.
        $url = rtrim((string) config('app.url'), '/')
            .'/sifre-sifirla?token='.$this->token
            .'&email='.urlencode((string) $notifiable->email);

        return (new MailMessage)
            ->subject('Tekrarla · Şifre sıfırlama')
            ->greeting('Merhaba!')
            ->line('Şifreni sıfırlamak için aşağıdaki düğmeye dokun.')
            ->action('Şifremi sıfırla', $url)
            ->line("Bu bağlantı {$minutes} dakika geçerli ve yalnızca bir kez kullanılabilir.")
            ->line('Bu isteği sen yapmadıysan bu e-postayı yok sayabilirsin; şifren değişmez.')
            ->salutation('Kolay gelsin, Tekrarla');
    }
}
