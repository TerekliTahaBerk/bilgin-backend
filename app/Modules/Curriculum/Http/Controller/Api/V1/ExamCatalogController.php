<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Controller\Api\V1;

use App\Modules\Curriculum\Http\Resource\ExamCatalogResource;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ExamCatalogController extends ApiController
{
    /**
     * GET /v1/exams — kayıt akışının 1. ve 2. adımı.
     *
     * Sınavlar ve alanları TEK yanıtta: ikisi arka arkaya iki ekran ve
     * ayrı istek atmak, kullanıcıyı ikinci adımda bir kez daha
     * bekletirdi.
     *
     * Uygulama bu listeyi sabit yazmıyor; LGS açıldığında mağaza
     * güncellemesi beklemeden görünsün diye. Tek sınav varken istemci
     * adımı atlıyor — tek seçenekli bir soru, sorulmamış bir sorudur.
     */
    public function index(): JsonResponse
    {
        $exams = Exam::query()
            ->where('is_active', true)
            ->with(['variants' => static fn ($q) => $q
                ->where('is_active', true)
                ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::data(ExamCatalogResource::toArray($exams));
    }
}
