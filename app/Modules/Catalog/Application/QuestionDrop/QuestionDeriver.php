<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\QuestionDrop;

use App\Modules\Catalog\Domain\Enum\ExerciseType;

/**
 * Çoktan seçmeli sorudan başka tipler türetir.
 *
 * ## Neden gerek var
 *
 * İçerik paketindeki 19.714 sorunun TAMAMI çoktan seçmeli. Uygulamada
 * on tip var ve ünite şablonları çeşitliliğe göre kurulu: eşleştirme,
 * sıralama ve bilgi kartı adımları ayrı ayrı tanımlı. Paketi olduğu gibi
 * alırsak her adım "test" olur ve o adımlar boş kalır.
 *
 * ## Neden yalnızca üç tip
 *
 * Her tip türetilemez. Türetilebilenler mekanik ve doğrulanabilir;
 * türetilemeyenleri zorlamak çöp üretir:
 *
 *   · `numeric_input` — tüm şıkları sayı olan soru. Şıklar kaldırılınca
 *     soru DAHA ZOR oluyor: beşten eleyerek tutturma ihtimali kalkıyor.
 *   · `flashcard`     — kısa gövdeli soru. Ön yüz soru, arka yüz cevap
 *     ve açıklama.
 *   · `matching`      — aynı konudan dört kısa soru-cevap çifti.
 *
 * TÜRETİLMEYENLER ve sebepleri:
 *
 *   · `true_false`  — "X kaçtır?" + "23" → "X 23'tür" dönüşümü Türkçe
 *     morfoloji istiyor (ünlü uyumu, ses düşmesi, kesme işareti).
 *     19 bin soruda otomatik yapmak binlerce bozuk cümle üretir.
 *   · `fill_blank`  — gövdede boşluk bırakılacak yer yok; uydurmak gerekir.
 *   · `ordering`, `word_order` — doğal bir sıra ya da düzgün bir cümle
 *     gerekiyor; çoktan seçmeli sorunun içinde bu bilgi yok.
 *   · `image_hotspot`, `diagram_label` — görsel yok.
 *
 * ## Bilinçli olarak YAPILMAYAN
 *
 * Türetilen soru kaynağına BAĞLANMIYOR. Bu bir eksiklik değil, verilmiş
 * bir karar: şemaya `derived_from_id` eklenmedi. Bedeli şu — öğrenci
 * aynı soruyu bir turda hem test hem sayısal olarak görebilir ve tekrar
 * kuyruğu ikisini ayrı kazanım sayar. Sorun görülürse çözümü bir sütun
 * ve seçiciye tek koşul eklemek.
 */
final class QuestionDeriver
{
    /**
     * Bilgi kartı ve eşleştirmeye uygun azami gövde uzunluğu.
     *
     * Ölçüldü: soruların %45'inin gövdesi 150 karakterden uzun. 200
     * karakterlik bir kartın ön yüzü kart değil, paragraf olur.
     */
    private const SHORT_STEM = 90;

    /** Eşleştirme ızgarasına sığan azami cevap uzunluğu. */
    private const SHORT_ANSWER = 45;

    /**
     * Gövdesi şıklara BAĞIMLI olan soruları eleyen kalıplar.
     *
     * "Aşağıdakilerden hangisi doğrudur?" sorusunun şıkları olmadan
     * hiçbir anlamı yok; bilgi kartına çevrilirse ön yüzü boş bir soru,
     * arka yüzü bağlamsız bir cümle olur. Eşleştirmede daha da kötü:
     * sol sütunda birbirinin aynı dört satır çıkar.
     */
    private const OPTION_DEPENDENT = [
        'aşağıdaki', 'yukarıdaki', 'hangisi', 'hangileri',
        'verilenlerden', 'seçeneklerden', 'bunlardan',
    ];

    /**
     * Sayısal girişe çevirir.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $answerKey
     * @return array{type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>}|null
     */
    public function toNumericInput(array $content, array $answerKey): ?array
    {
        $stem = $this->text($content['stem'] ?? null);
        $options = $this->options($content);
        $correctId = $this->text($answerKey['correct_option_id'] ?? null);

        if ($stem === null || $options === [] || $correctId === null) {
            return null;
        }

        $parsed = [];

        foreach ($options as $id => $text) {
            $value = $this->parseNumber($text);

            // TÜM şıklar sayı olmalı. Biri metinse soru "hangisi doğru"
            // tipindedir ve sayısal girişe çevrilemez.
            if ($value === null) {
                return null;
            }

            $parsed[$id] = $value;
        }

        if (! isset($parsed[$correctId])) {
            return null;
        }

        [$value, $unit] = $parsed[$correctId];

        // Birimler şıklar arasında AYNI olmalı: biri "cm" diğeri "m" ise
        // doğru cevabın birimi soruya yazılamaz ve öğrenci neyi
        // yazacağını bilemez.
        $units = array_unique(array_map(static fn (array $p): string => $p[1], $parsed));

        if (count($units) > 1) {
            return null;
        }

        return [
            'type' => ExerciseType::NumericInput,
            'content' => array_filter([
                'stem' => $stem,
                'suffix' => $unit !== '' ? $unit : null,
            ], static fn ($v): bool => $v !== null),
            'answer_key' => [
                'value' => $value,
                // Tolerans sıfır: şıkları sayı olan sorularda cevap
                // kesin. Tolerans vermek yanlış cevabı doğru saymak olur.
                'tolerance' => 0,
            ],
        ];
    }

    /**
     * Bilgi kartına çevirir.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $answerKey
     * @return array{type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>}|null
     */
    public function toFlashcard(array $content, array $answerKey, ?string $explanation): ?array
    {
        $stem = $this->text($content['stem'] ?? null);
        $options = $this->options($content);
        $correctId = $this->text($answerKey['correct_option_id'] ?? null);

        if ($stem === null || $correctId === null || ! isset($options[$correctId])) {
            return null;
        }

        if (! $this->isStandalone($stem)) {
            return null;
        }

        $answer = $options[$correctId];

        // Arka yüz: cevap + açıklama. Açıklama %99'unda var ve kartı
        // "cevabı ezberle"den "neden öyle olduğunu hatırla"ya çeviriyor.
        $back = $explanation !== null && trim($explanation) !== ''
            ? $answer."\n\n".trim($explanation)
            : $answer;

        return [
            'type' => ExerciseType::Flashcard,
            'content' => ['front' => $stem, 'back' => $back],
            'answer_key' => ['self_assessed' => true],
        ];
    }

    /**
     * Dört soru-cevap çiftinden eşleştirme kurar.
     *
     * @param  list<array{stem: string, answer: string}>  $pairs
     * @return array{type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>}|null
     */
    public function toMatching(array $pairs): ?array
    {
        if (count($pairs) < 3) {
            return null;
        }

        // En fazla dört satır: daha uzun bir ızgara telefon ekranında
        // kaydırmasız sığmıyor ve eşleştirme okunamaz hâle geliyor.
        $pairs = array_slice($pairs, 0, 4);

        /*
         | Hem sorular hem CEVAPLAR benzersiz olmalı.
         |
         | İki satırın cevabı aynıysa eşleştirmenin tek doğru çözümü
         | kalmıyor: öğrenci doğru eşleştirse bile puanı kaybedebilir.
         | Bu, fark edilmesi en zor türetme hatası olurdu.
        */
        $stems = array_map(static fn (array $p): string => $p['stem'], $pairs);
        $answers = array_map(static fn (array $p): string => $p['answer'], $pairs);

        if (count(array_unique($stems)) !== count($pairs)
            || count(array_unique($answers)) !== count($pairs)) {
            return null;
        }

        $left = [];
        $right = [];
        $map = [];

        foreach ($pairs as $i => $pair) {
            $leftId = (string) ($i + 1);
            $rightId = chr(ord('a') + $i);

            $left[] = ['id' => $leftId, 'text' => $pair['stem']];
            $right[] = ['id' => $rightId, 'text' => $pair['answer']];
            $map[$leftId] = $rightId;
        }

        return [
            'type' => ExerciseType::Matching,
            'content' => [
                'instruction' => 'Soruyu doğru cevabıyla birleştir.',
                'left' => $left,
                'right' => $right,
            ],
            'answer_key' => ['pairs' => $map, 'partial_credit' => true],
        ];
    }

    /**
     * Soru, eşleştirme ya da kart için yeterince kısa ve bağımsız mı.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $answerKey
     * @return array{stem: string, answer: string}|null
     */
    public function pairFor(array $content, array $answerKey): ?array
    {
        $stem = $this->text($content['stem'] ?? null);
        $options = $this->options($content);
        $correctId = $this->text($answerKey['correct_option_id'] ?? null);

        if ($stem === null || $correctId === null || ! isset($options[$correctId])) {
            return null;
        }

        $answer = $options[$correctId];

        if (! $this->isStandalone($stem) || mb_strlen($answer) > self::SHORT_ANSWER) {
            return null;
        }

        return ['stem' => $stem, 'answer' => $answer];
    }

    /** Gövde kendi başına anlamlı mı: kısa ve şıklara bağımlı değil. */
    private function isStandalone(string $stem): bool
    {
        if (mb_strlen($stem) > self::SHORT_STEM) {
            return false;
        }

        $lower = mb_strtolower($stem, 'UTF-8');

        foreach (self::OPTION_DEPENDENT as $needle) {
            if (str_contains($lower, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Metni sayıya ve birime ayırır.
     *
     * @return array{0: float, 1: string}|null
     */
    private function parseNumber(string $text): ?array
    {
        $normalized = trim($text);

        // Türkçe metinde eksi işareti çoğu zaman U+2212, ASCII '-' değil.
        $normalized = str_replace(['−', '–', '—'], '-', $normalized);

        // Binlik ayıracı nokta, ondalık ayıracı virgül olabilir:
        // "1.234,5" → "1234.5". Önce binlikleri at, sonra virgülü çevir.
        $normalized = preg_replace('/(?<=\d)\.(?=\d{3}\b)/', '', $normalized) ?? $normalized;
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^([+-]?\d+(?:\.\d+)?)\s*(.*)$/u', $normalized, $m)) {
            return null;
        }

        $unit = trim($m[2]);

        // Birim uzunsa bu bir sayı değil, sayıyla başlayan bir cümledir
        // ("3 katıdır" gibi) ve sayısal girişe çevrilemez.
        if (mb_strlen($unit) > 6) {
            return null;
        }

        return [(float) $m[1], $unit];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string> şık kimliği → metin
     */
    private function options(array $content): array
    {
        $raw = $content['options'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $option) {
            if (! is_array($option)) {
                continue;
            }

            $id = $this->text($option['id'] ?? null);
            $text = $this->text($option['text'] ?? null);

            if ($id !== null && $text !== null) {
                $out[$id] = $text;
            }
        }

        return $out;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
