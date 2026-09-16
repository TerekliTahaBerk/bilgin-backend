<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Social;

use App\Modules\Identity\Domain\Enum\SocialProvider;

final class VerifierRegistry
{
    /** @var array<string, SocialIdentityVerifier> */
    private array $verifiers = [];

    /** @param  iterable<SocialIdentityVerifier>  $verifiers */
    public function __construct(iterable $verifiers = [])
    {
        foreach ($verifiers as $verifier) {
            $this->register($verifier);
        }
    }

    public function register(SocialIdentityVerifier $verifier): void
    {
        $this->verifiers[$verifier->provider()->value] = $verifier;
    }

    public function for(SocialProvider $provider): SocialIdentityVerifier
    {
        return $this->verifiers[$provider->value]
            ?? throw SocialVerificationFailed::notConfigured($provider->value);
    }
}
