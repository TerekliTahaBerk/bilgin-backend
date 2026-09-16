<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Provider;

use App\Modules\Identity\Domain\Enum\SocialProvider;
use App\Modules\Identity\Domain\Social\JwksProvider;
use App\Modules\Identity\Domain\Social\VerifierRegistry;
use App\Modules\Identity\Infrastructure\Eloquent\Repository\EloquentLearnerProfileReader;
use App\Modules\Identity\Infrastructure\Social\CachedJwksProvider;
use App\Modules\Identity\Infrastructure\Social\JwtIdentityVerifier;
use App\Shared\Domain\Learner\LearnerProfileReader;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(JwksProvider::class, CachedJwksProvider::class);

        $this->app->singleton(VerifierRegistry::class, static function (Application $app): VerifierRegistry {
            $registry = new VerifierRegistry;

            foreach (SocialProvider::cases() as $provider) {
                $config = (array) config("tekrarla.social.{$provider->value}", []);

                $registry->register(new JwtIdentityVerifier(
                    provider: $provider,
                    jwks: $app->make(JwksProvider::class),
                    jwksUrl: (string) ($config['jwks_url'] ?? ''),
                    expectedIssuer: (string) ($config['issuer'] ?? ''),
                    allowedAudiences: array_values((array) ($config['audiences'] ?? [])),
                ));
            }

            return $registry;
        });

        // Identity'nin dışarıya açtığı tek okuma yüzeyi. Diğer modüller
        // User modeline değil, bu arayüze bağımlıdır.
        $this->app->bind(LearnerProfileReader::class, EloquentLearnerProfileReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
