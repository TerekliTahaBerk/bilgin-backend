<?php

declare(strict_types=1);

use App\Modules\Admin\Infrastructure\Provider\AdminServiceProvider;
use App\Modules\Ads\Infrastructure\Provider\AdsServiceProvider;
use App\Modules\Billing\Infrastructure\Provider\BillingServiceProvider;
use App\Modules\Catalog\Infrastructure\Provider\CatalogServiceProvider;
use App\Modules\Curriculum\Infrastructure\Provider\CurriculumServiceProvider;
use App\Modules\Gamification\Infrastructure\Provider\GamificationServiceProvider;
use App\Modules\Hearts\Infrastructure\Provider\HeartsServiceProvider;
use App\Modules\Identity\Infrastructure\Provider\IdentityServiceProvider;
use App\Modules\League\Infrastructure\Provider\LeagueServiceProvider;
use App\Modules\Learning\Infrastructure\Provider\LearningServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SharedServiceProvider;

/*
 | Sıra önemlidir:
 |   - Catalog geçici bağlamalarını (NullProgressReader, NullReviewQueueReader)
 |     kaydeder; Learning sonra gelip gerçek implementasyonlarla üzerine yazar.
 |   - SharedServiceProvider FreeEntitlementReader'ı bağlar; Billing sonra
 |     gelip gerçek abonelik okuyucusuyla değiştirir. Hearts ondan SONRA
 |     gelmeli ki doğru okuyucuyu alsın.
 */
return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    CurriculumServiceProvider::class,
    BillingServiceProvider::class,
    HeartsServiceProvider::class,
    GamificationServiceProvider::class,
    LearningServiceProvider::class,
    AdsServiceProvider::class,
    LeagueServiceProvider::class,
    AdminServiceProvider::class,
];
