<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controller\Api\V1;

use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Konu bazlı zayıf/güçlü analizi — tasarımdaki "Tekrar gerekli / Güçlü konu".
 *
 * Ücretsiz planda ÖZET (kaç zayıf, kaç güçlü), premium'da konu konu detay.
 * Bu, premium'un satılabilir ama ücretsizi sakatlamayan bir farkı: öğrenci
 * zayıf olduğunu görüyor, hangi konuda olduğunu premium'la öğreniyor.
 */
final class TopicAnalysisController extends ApiController
{
    public function index(Request $request, EntitlementReader $entitlements): JsonResponse
    {
        $userId = $this->userId($request);
        $premium = $entitlements->for($userId)->premium;

        $rows = DB::table('user_topic_stats as uts')
            ->join('topics as t', 't.id', '=', 'uts.topic_id')
            ->join('subjects as s', 's.id', '=', 't.subject_id')
            ->where('uts.user_id', $userId)
            ->when(
                $request->filled('course_id'),
                // Konular subject seviyesinde olduğu için ders filtresi
                // dersin subject'i üzerinden yapılır — TYT ve AYT aynı
                // konuları paylaşır ve analiz birleşik kalır.
                fn ($q) => $q->whereIn('t.subject_id', DB::table('courses')
                    ->where('id', $request->integer('course_id'))
                    ->select('subject_id')),
            )
            ->orderBy('uts.accuracy')
            ->get([
                'uts.topic_id', 'uts.attempts', 'uts.correct', 'uts.accuracy',
                'uts.mastery', 'uts.last_practiced_at', 't.name as topic_name',
                's.name as subject_name', 's.color',
            ]);

        $summary = [
            'weak' => $rows->where('mastery', 'weak')->count(),
            'developing' => $rows->where('mastery', 'developing')->count(),
            'strong' => $rows->where('mastery', 'strong')->count(),
            'total' => $rows->count(),
        ];

        if (! $premium) {
            return ApiResponse::data([
                'summary' => $summary,
                'detailed' => false,
                // En zayıf tek konu ücretsizde de gösteriliyor: premium'un
                // ne verdiğini somut kılan şey bu örnek.
                'weakest_topic' => $rows->first()?->topic_name,
            ]);
        }

        return ApiResponse::data([
            'summary' => $summary,
            'detailed' => true,
            'topics' => $rows->map(static fn (object $row): array => [
                'topic_id' => (int) $row->topic_id,
                'topic' => $row->topic_name,
                'subject' => $row->subject_name,
                'color' => $row->color,
                'attempts' => (int) $row->attempts,
                'correct' => (int) $row->correct,
                'accuracy' => (int) $row->accuracy,
                'mastery' => $row->mastery,
                'last_practiced_at' => $row->last_practiced_at,
            ])->all(),
        ]);
    }
}
