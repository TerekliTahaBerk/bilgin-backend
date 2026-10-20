<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\QuestionDrop\QuestionDeriver;
use App\Modules\Catalog\Domain\Enum\ExerciseType;

/*
 | SORU TİPİ TÜRETME.
 |
 | Buradaki testlerin tamamı "ne türetiliyor" değil, NE TÜRETİLMİYOR
 | sorusunu soruyor. Türetme sessizce yanlış çalışırsa veritabanına
 | çözülemeyen bir eşleştirme ya da cevabı yanlış bir sayısal soru
 | girer — ikisi de öğrenciyi haksız yere yanlışa düşürür ve kimse
 | sebebini anlamaz.
 */

function mc(string $stem, array $options, string $correct): array
{
    return [
        'content' => [
            'stem' => $stem,
            'options' => array_map(
                static fn (string $id, string $text): array => ['id' => $id, 'text' => $text],
                array_keys($options),
                array_values($options),
            ),
        ],
        'answer_key' => ['correct_option_id' => $correct],
    ];
}

// ------------------------------------------------------- sayısal giriş

it('tüm şıklar sayıysa sayısal girişe çevirir', function (): void {
    $q = mc('15 ile −8 sayılarının farkı kaçtır?', ['a' => '23', 'b' => '-23', 'c' => '7'], 'a');

    $out = (new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']);

    expect($out)->not->toBeNull()
        ->and($out['type'])->toBe(ExerciseType::NumericInput)
        ->and($out['answer_key']['value'])->toBe(23.0)
        // Tolerans SIFIR: şıkları sayı olan soruda cevap kesindir.
        // Tolerans vermek yanlış cevabı doğru saymak olurdu.
        ->and($out['answer_key']['tolerance'])->toBe(0);
});

it('şıklardan biri metinse ÇEVİRMEZ', function (): void {
    $q = mc('Hangisi doğrudur?', ['a' => '23', 'b' => 'Hiçbiri', 'c' => '7'], 'a');

    // "Hiçbiri" şıkkı sorunun cevabının bir sayı olmadığını gösterir;
    // şıkları kaldırmak soruyu cevaplanamaz hâle getirirdi.
    expect((new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']))->toBeNull();
});

it('Türkçe eksi işaretini ve ondalık virgülü çözer', function (): void {
    // Metinlerde eksi çoğu zaman U+2212, ASCII '-' değil; ondalık
    // ayıracı da virgül. İkisi de çözülmezse soru sessizce atlanırdı.
    $q = mc('Sonuç kaçtır?', ['a' => '−3,5', 'b' => '2,5', 'c' => '0'], 'a');

    $out = (new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']);

    expect($out['answer_key']['value'])->toBe(-3.5);
});

it('binlik ayıracını ondalıkla karıştırmaz', function (): void {
    $q = mc('Nüfus kaçtır?', ['a' => '1.234', 'b' => '2.500', 'c' => '900'], 'a');

    $out = (new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']);

    // "1.234" bin iki yüz otuz dört; 1,234 değil. Yanlış okunursa
    // öğrencinin doğru cevabı yanlış sayılırdı.
    expect($out['answer_key']['value'])->toBe(1234.0);
});

it('birimi soruya ek olarak taşır', function (): void {
    $q = mc('Hipotenüs kaç br?', ['a' => '5 br', 'b' => '6 br', 'c' => '7 br'], 'a');

    $out = (new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']);

    expect($out['content']['suffix'])->toBe('br')
        ->and($out['answer_key']['value'])->toBe(5.0);
});

it('şıkların birimi FARKLIYSA çevirmez', function (): void {
    $q = mc('Uzunluk nedir?', ['a' => '5 cm', 'b' => '2 m', 'c' => '9 km'], 'a');

    // Hangi birimle yazacağı belirsiz kalırdı: "5" mi "0,05" mi?
    expect((new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']))->toBeNull();
});

it('sayıyla başlayan cümleyi sayı sanmaz', function (): void {
    $q = mc('Oran nedir?', ['a' => '3 katıdır', 'b' => '4 katıdır', 'c' => '5 katıdır'], 'a');

    expect((new QuestionDeriver)->toNumericInput($q['content'], $q['answer_key']))->toBeNull();
});

// ---------------------------------------------------------- bilgi kartı

it('kısa ve bağımsız soruyu karta çevirir', function (): void {
    $q = mc('Kavimler Göçü hangi yılda gerçekleşti?', ['a' => '375', 'b' => '395'], 'a');

    $out = (new QuestionDeriver)->toFlashcard($q['content'], $q['answer_key'], 'Hunların batıya göçüyle başladı.');

    expect($out['content']['front'])->toBe('Kavimler Göçü hangi yılda gerçekleşti?')
        ->and($out['content']['back'])->toContain('375')
        // Açıklama arka yüze giriyor: kart "cevabı ezberle"den
        // "neden öyle olduğunu hatırla"ya dönüşüyor.
        ->and($out['content']['back'])->toContain('Hunların');
});

it('şıklara BAĞIMLI soruyu karta çevirmez', function (): void {
    $q = mc('Aşağıdakilerden hangisi doğrudur?', ['a' => 'Birincisi', 'b' => 'İkincisi'], 'a');

    /*
     | Bu sorunun şıkları olmadan hiçbir anlamı yok. Karta çevrilseydi
     | ön yüzü "Aşağıdakilerden hangisi doğrudur?", arka yüzü
     | "Birincisi" olurdu — öğrenciye hiçbir şey öğretmeyen bir kart.
    */
    expect((new QuestionDeriver)->toFlashcard($q['content'], $q['answer_key'], null))->toBeNull();
});

it('uzun gövdeli soruyu karta çevirmez', function (): void {
    $q = mc(str_repeat('Çok uzun bir soru gövdesi. ', 8), ['a' => 'X', 'b' => 'Y'], 'a');

    // 200 karakterlik bir kartın ön yüzü kart değil, paragraf.
    expect((new QuestionDeriver)->toFlashcard($q['content'], $q['answer_key'], null))->toBeNull();
});

// ----------------------------------------------------------- eşleştirme

it('dört çiftten eşleştirme kurar', function (): void {
    $pairs = [
        ['stem' => 'Kavimler Göçü', 'answer' => '375'],
        ['stem' => 'İstanbul\'un fethi', 'answer' => '1453'],
        ['stem' => 'Malazgirt', 'answer' => '1071'],
        ['stem' => 'Mısır Seferi', 'answer' => '1517'],
    ];

    $out = (new QuestionDeriver)->toMatching($pairs);

    expect($out['type'])->toBe(ExerciseType::Matching)
        ->and($out['content']['left'])->toHaveCount(4)
        ->and($out['content']['right'])->toHaveCount(4)
        ->and($out['answer_key']['pairs'])->toBe(['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'])
        ->and($out['answer_key']['partial_credit'])->toBeTrue();
});

it('AYNI CEVAPLI çiftlerden eşleştirme kurmaz', function (): void {
    $pairs = [
        ['stem' => 'Birinci soru', 'answer' => 'Aynı cevap'],
        ['stem' => 'İkinci soru', 'answer' => 'Aynı cevap'],
        ['stem' => 'Üçüncü soru', 'answer' => 'Başka'],
        ['stem' => 'Dördüncü soru', 'answer' => 'Farklı'],
    ];

    /*
     | Türetmenin en sinsi hatası bu.
     |
     | İki satırın cevabı aynıysa eşleştirmenin TEK doğru çözümü
     | kalmıyor: öğrenci mantıken doğru eşleştirse bile puan
     | kaybedebiliyor. Ekranda hiçbir şey bozuk görünmediği için de
     | şikâyet "bence doğruydu ama yanlış saydı" olarak geliyor ve
     | sebebi bulunamıyor.
    */
    expect((new QuestionDeriver)->toMatching($pairs))->toBeNull();
});

it('üçten az çiftle eşleştirme kurmaz', function (): void {
    $pairs = [
        ['stem' => 'Tek soru', 'answer' => 'Tek cevap'],
        ['stem' => 'İki soru', 'answer' => 'İki cevap'],
    ];

    // İki satırlık eşleştirme, bir doğru-yanlış sorusundan farksız.
    expect((new QuestionDeriver)->toMatching($pairs))->toBeNull();
});

it('dörtten fazla çifti kırpar', function (): void {
    $pairs = [];

    for ($i = 1; $i <= 7; $i++) {
        $pairs[] = ['stem' => "Soru {$i}", 'answer' => "Cevap {$i}"];
    }

    $out = (new QuestionDeriver)->toMatching($pairs);

    // Daha uzun ızgara telefon ekranında kaydırmasız sığmıyor.
    expect($out['content']['left'])->toHaveCount(4);
});

it('uzun cevaplı soruyu eşleştirmeye almaz', function (): void {
    $q = mc('Kısa soru?', ['a' => str_repeat('uzun cevap ', 8), 'b' => 'X'], 'a');

    expect((new QuestionDeriver)->pairFor($q['content'], $q['answer_key']))->toBeNull();
});
