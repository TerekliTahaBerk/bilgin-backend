<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\QuestionDrop;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use Illuminate\Support\Str;

/**
 * İçerik ekibinin konu adını bizim konu kodumuza EŞLEMEYE ÇALIŞIR.
 *
 * "Çalışır" kelimesi önemli: bu sınıf karar vermiyor, ÖNERİ üretiyor.
 * Yanlış eşlenen bir soru sessizce yanlış konuda durur, öğrencinin konu
 * analizini bozar ve kimse fark etmez. Bu yüzden son söz insanda:
 * tarayıcı bir eşleme dosyası üretiyor, aktarıcı yalnızca onaylanmış
 * satırları alıyor.
 *
 * Üç güven düzeyi var:
 *
 *   · `exact`  — ad birebir aynı koda düşüyor. Güvenle kabul edilebilir.
 *   · `fuzzy`  — kelime örtüşmesi yüksek. İnsan bakmalı.
 *   · `none`   — karşılık bulunamadı. Ya yeni konu açılacak ya atlanacak.
 */
final class TopicMatcher
{
    /** @var array<string, array{id: int, subject: string, name: string}> */
    private array $byCode = [];

    /** @var array<string, list<string>> ders dalı → o dalın kodları */
    private array $bySubject = [];

    public function __construct()
    {
        $topics = Topic::query()
            ->join('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->get(['topics.id', 'topics.code', 'topics.name', 'subjects.code as subject']);

        foreach ($topics as $topic) {
            $this->byCode[$topic->code] = [
                'id' => (int) $topic->id,
                'subject' => (string) $topic->subject,
                'name' => (string) $topic->name,
            ];
            $this->bySubject[(string) $topic->subject][] = (string) $topic->code;
        }
    }

    /**
     * @return array{code: string|null, confidence: string, score: float}
     */
    public function suggest(string $topicName, ?string $subject = null): array
    {
        $code = self::codeFor($topicName);

        if (isset($this->byCode[$code])) {
            return ['code' => $code, 'confidence' => 'exact', 'score' => 1.0];
        }

        /*
         | Bulanık arama ders dalıyla DARALTILIYOR.
         |
         | "Problemler" adı hem matematikteki problem konularına hem
         | fizikteki bir başlığa benziyor. Dal bilinmeden yapılan arama,
         | matematik sorusunu fizik konusuna yazabilirdi.
         */
        $pool = $subject !== null && isset($this->bySubject[$subject])
            ? $this->bySubject[$subject]
            : array_keys($this->byCode);

        $best = null;
        $bestScore = 0.0;

        foreach ($pool as $candidate) {
            $score = $this->similarity($code, $candidate);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        // 0.55 eşiği ölçerek seçildi: altında kalan öneriler insanı
        // yanıltmaktan başka işe yaramıyor, yok saymak daha dürüst.
        return $bestScore >= 0.55
            ? ['code' => $best, 'confidence' => 'fuzzy', 'score' => round($bestScore, 2)]
            : ['code' => null, 'confidence' => 'none', 'score' => 0.0];
    }

    /** Kelime kümesi örtüşmesi (Jaccard). */
    private function similarity(string $a, string $b): float
    {
        $tokensA = array_filter(explode('_', $a), static fn (string $t): bool => mb_strlen($t) > 2);
        $tokensB = array_filter(explode('_', $b), static fn (string $t): bool => mb_strlen($t) > 2);

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $shared = count(array_intersect($tokensA, $tokensB));

        // Her iki küme de boş olamaz (yukarıda elendi), yani bölen sıfır olmaz.
        $total = count(array_unique([...$tokensA, ...$tokensB]));

        return $shared / $total;
    }

    public function idFor(string $code): ?int
    {
        return $this->byCode[$code]['id'] ?? null;
    }

    public function subjectFor(string $code): ?string
    {
        return $this->byCode[$code]['subject'] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->byCode[$code]);
    }

    /**
     * Başlıktan kod üretir.
     *
     * `TopicSeeder::codeFor` ile AYNI kural olmak ZORUNDA: ayrışırsa
     * eşleme sessizce kayar ve aynı konu iki kez açılır. Kopyalanmış
     * olması hoş değil ama iki modül birbirinin Infrastructure'ına
     * giremiyor; alternatif Shared'a taşımaktı ve bu kural Catalog'a ait.
     */
    public static function codeFor(string $title): string
    {
        $ascii = strtr($title, [
            'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
            // Paket metinlerinde eğik tırnak var; TopicSeeder'daki
            // düz tırnakla aynı sonuca düşmesi için normalleştiriliyor.
            '’' => "'", '‘' => "'",
        ]);

        return Str::of($ascii)->lower()->slug('_')->limit(96, '')->value();
    }
}
