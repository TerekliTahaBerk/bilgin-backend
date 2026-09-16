<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use LogicException;

final readonly class SocialAuthResult
{
    private function __construct(
        public ?User $user,
        public bool $isNew,
        public bool $upgraded,
        public ?AccountSummary $guestAccount = null,
        public ?AccountSummary $existingAccount = null,
    ) {}

    public static function signedIn(User $user, bool $isNew, bool $upgraded = false): self
    {
        return new self($user, $isNew, $upgraded);
    }

    /**
     * İki ilerleme var, biri kaybolacak.
     *
     * Kullanıcıya sorulmadan karar verilmiyor: hangi hesabın kaybolacağını
     * seçmek onun hakkı, bizim varsayımımız değil.
     */
    public static function conflict(AccountSummary $guest, AccountSummary $existing): self
    {
        return new self(null, false, false, $guest, $existing);
    }

    public function hasConflict(): bool
    {
        return $this->user === null;
    }

    /**
     * Çakışma yoksa kullanıcı KESİN vardır.
     *
     * Çağıranın her seferinde null kontrolü yapması, aslında var olan bir
     * garantiyi gizler; burada bir kez açıkça doğrulanıyor.
     */
    public function user(): User
    {
        return $this->user ?? throw new LogicException(
            'Çakışma durumunda kullanıcı yoktur; önce hasConflict() kontrol edilmeli.'
        );
    }
}
