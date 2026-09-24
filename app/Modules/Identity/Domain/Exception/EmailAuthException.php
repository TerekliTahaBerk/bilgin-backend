<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exception;

use RuntimeException;

/**
 * E-posta ile kayıt ve giriş akışının beklenen hataları.
 *
 * Her biri sözleşmedeki bir hata KODUNA karşılık gelir; istemci koda göre
 * dallanır, metne göre değil.
 */
final class EmailAuthException extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Giriş başarısız.
     *
     * Kullanıcı yok ile şifre yanlış AYNI hatayı döner: hangi e-postanın
     * kayıtlı olduğu bilgisi sızdırılmaz. Yönetici girişinde de aynı kural.
     */
    public static function invalidCredentials(): self
    {
        return new self(
            'INVALID_CREDENTIALS',
            'E-posta veya şifre hatalı.',
            422,
        );
    }

    public static function emailTaken(): self
    {
        return new self(
            'EMAIL_TAKEN',
            'Bu e-posta ile bir hesap zaten var. Giriş yapmayı dene.',
            409,
        );
    }

    /**
     * Misafirin ilerlemesi var ama e-posta başka bir hesaba bağlı.
     *
     * Sessizce birini seçmek kullanıcının çalışmasını kaybettirir; kararı
     * ona bırakıyoruz. Sosyal girişte de aynı davranış.
     */
    public static function progressWouldBeLost(): self
    {
        return new self(
            'PROGRESS_CONFLICT',
            'Bu e-postaya ait başka bir hesap var. Misafir ilerlemen o hesaba '
                .'taşınamaz; devam edersen bu cihazdaki ilerleme kaybolur.',
            409,
        );
    }

    public static function resetTokenInvalid(): self
    {
        return new self(
            'RESET_TOKEN_INVALID',
            'Bağlantı geçersiz ya da süresi dolmuş. Yeniden şifre sıfırlama iste.',
            422,
        );
    }

    public static function verificationTokenInvalid(): self
    {
        return new self(
            'VERIFICATION_TOKEN_INVALID',
            'Doğrulama bağlantısı geçersiz ya da süresi dolmuş.',
            422,
        );
    }

    /**
     * Zaten kalıcı bir hesapla giriş yapılmışken kayıt denenmesi.
     *
     * Sessizce yeni hesap açmak, kullanıcının hangi hesapta olduğunu
     * kaybetmesine yol açar.
     */
    public static function alreadyRegistered(): self
    {
        return new self(
            'ALREADY_REGISTERED',
            'Zaten bir hesabın var.',
            409,
        );
    }
}
