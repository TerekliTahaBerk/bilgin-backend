<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\Grader\DiagramLabelGrader;
use App\Modules\Learning\Domain\Grading\Grader\FillBlankGrader;
use App\Modules\Learning\Domain\Grading\Grader\FlashcardGrader;
use App\Modules\Learning\Domain\Grading\Grader\MatchingGrader;
use App\Modules\Learning\Domain\Grading\Grader\MultipleChoiceGrader;
use App\Modules\Learning\Domain\Grading\Grader\NumericInputGrader;
use App\Modules\Learning\Domain\Grading\Grader\OrderingGrader;
use App\Modules\Learning\Domain\Grading\Grader\TrueFalseGrader;
use App\Modules\Learning\Domain\Grading\Grader\WordOrderGrader;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/*
 | Değerlendirme mantığı — veritabanı YOK.
 |
 | Bunlar ürünün en çok kırılabilecek kuralları: bir grader'daki sessiz hata,
 | öğrenciye haksız yere can kaybettirir. O yüzden her tipin doğru, yanlış ve
 | bozuk girdi hâli ayrı ayrı korunuyor.
 */

function content(array $answerKey, array $body = []): ExerciseContent
{
    return new ExerciseContent($body, $answerKey);
}

function answer(array $payload): SubmittedAnswer
{
    return new SubmittedAnswer($payload);
}

describe('çoktan seçmeli', function (): void {
    $grader = new MultipleChoiceGrader;

    it('doğru şıkkı tanır', function () use ($grader): void {
        $result = $grader->grade(content(['correct_option_id' => 'b']), answer(['option_id' => 'b']));

        expect($result->isCorrect)->toBeTrue()
            ->and($result->partialScore)->toBe(1.0);
    });

    it('yanlış şıkta doğru cevabı geri bildirir', function () use ($grader): void {
        $result = $grader->grade(content(['correct_option_id' => 'b']), answer(['option_id' => 'a']));

        expect($result->isCorrect)->toBeFalse()
            ->and($result->correctAnswer)->toBe(['option_id' => 'b']);
    });

    it('cevapsız gönderimi yanlış sayar', function () use ($grader): void {
        expect($grader->grade(content(['correct_option_id' => 'b']), answer([]))->isCorrect)->toBeFalse();
    });
});

describe('doğru-yanlış', function (): void {
    $grader = new TrueFalseGrader;

    it('boolean cevabı karşılaştırır', function () use ($grader): void {
        expect($grader->grade(content(['value' => true]), answer(['value' => true]))->isCorrect)->toBeTrue()
            ->and($grader->grade(content(['value' => true]), answer(['value' => false]))->isCorrect)->toBeFalse();
    });

    it('string "true" gönderimini doğru saymaz', function () use ($grader): void {
        // Gevşek karşılaştırma, hatalı istemci gönderimini doğru sayardı.
        expect($grader->grade(content(['value' => true]), answer(['value' => 'true']))->isCorrect)->toBeFalse();
    });
});

describe('boşluk doldurma', function (): void {
    $grader = new FillBlankGrader;

    it('büyük-küçük harf farkını yok sayar', function () use ($grader): void {
        expect($grader->grade(content(['blanks' => ['Töre']]), answer(['blanks' => ['töre']]))->isCorrect)->toBeTrue();
    });

    it('Türkçe I/ı dönüşümünü doğru yapar', function () use ($grader): void {
        // mb_strtolower varsayılanı "I" → "i" yapar; Türkçe'de doğrusu "ı".
        expect($grader->grade(content(['blanks' => ['ışık']]), answer(['blanks' => ['IŞIK']]))->isCorrect)->toBeTrue();
    });

    it('şapkalı harfi farklı kelime sayar', function () use ($grader): void {
        // "kar" ile "kâr" farklı kelimelerdir; aksan kaldırmak anlam kaybettirir.
        expect($grader->grade(content(['blanks' => ['kâr']]), answer(['blanks' => ['kar']]))->isCorrect)->toBeFalse();
    });

    it('baştaki ve sondaki boşluğu temizler', function () use ($grader): void {
        expect($grader->grade(content(['blanks' => ['Töre']]), answer(['blanks' => ['  töre  ']]))->isCorrect)->toBeTrue();
    });

    it('çok boşluklu soruda hepsini ister', function () use ($grader): void {
        $exercise = content(['blanks' => ['Töre', 'Kurultay']]);

        expect($grader->grade($exercise, answer(['blanks' => ['Töre', 'Kurultay']]))->isCorrect)->toBeTrue()
            ->and($grader->grade($exercise, answer(['blanks' => ['Töre', 'Toy']]))->isCorrect)->toBeFalse()
            ->and($grader->grade($exercise, answer(['blanks' => ['Töre']]))->isCorrect)->toBeFalse();
    });
});

describe('eşleştirme', function (): void {
    $grader = new MatchingGrader;
    $pairs = ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'];

    it('tam eşleşmede doğru döner', function () use ($grader, $pairs): void {
        $result = $grader->grade(content(['pairs' => $pairs, 'partial_credit' => true]), answer(['pairs' => $pairs]));

        expect($result->isCorrect)->toBeTrue()
            ->and($result->partialScore)->toBe(1.0);
    });

    it('kısmi eşleşmeyi YANLIŞ sayar ama puanı korur', function () use ($grader, $pairs): void {
        // 4'ün 2'sini tutturan öğrenci kutlama animasyonu görmemeli;
        // ama emeği ilerleme yüzdesine yansımalı.
        $result = $grader->grade(
            content(['pairs' => $pairs, 'partial_credit' => true]),
            answer(['pairs' => ['1' => 'a', '2' => 'b', '3' => 'x', '4' => 'y']]),
        );

        expect($result->isCorrect)->toBeFalse()
            ->and($result->partialScore)->toBe(0.5);
    });

    it('kısmi puan kapalıysa hep ya 0 ya 1 döner', function () use ($grader, $pairs): void {
        $result = $grader->grade(
            content(['pairs' => $pairs]),
            answer(['pairs' => ['1' => 'a', '2' => 'b', '3' => 'x', '4' => 'y']]),
        );

        expect($result->partialScore)->toBe(0.0);
    });
});

describe('sıralama', function (): void {
    $grader = new OrderingGrader;

    it('doğru sırayı tanır', function () use ($grader): void {
        $result = $grader->grade(content(['order' => ['1', '2', '3']]), answer(['order' => ['1', '2', '3']]));

        expect($result->isCorrect)->toBeTrue();
    });

    it('tek öğe kaymasında sıfır vermez', function () use ($grader): void {
        // Kronolojiyi büyük ölçüde bilen öğrenci sıfırlanmamalı.
        $result = $grader->grade(
            content(['order' => ['1', '2', '3', '4']]),
            answer(['order' => ['1', '3', '2', '4']]),
        );

        expect($result->isCorrect)->toBeFalse()
            ->and($result->partialScore)->toBeGreaterThan(0.5);
    });

    it('tamamen ters sırada sıfıra yakın verir', function () use ($grader): void {
        $result = $grader->grade(
            content(['order' => ['1', '2', '3', '4']]),
            answer(['order' => ['4', '3', '2', '1']]),
        );

        expect($result->partialScore)->toBe(0.0);
    });

    it('listede olmayan öğe gönderilirse sıfırlar', function () use ($grader): void {
        $result = $grader->grade(
            content(['order' => ['1', '2', '3']]),
            answer(['order' => ['1', '2', '9']]),
        );

        expect($result->partialScore)->toBe(0.0);
    });

    it('eksik öğe sayısında yanlış sayar', function () use ($grader): void {
        expect($grader->grade(content(['order' => ['1', '2', '3']]), answer(['order' => ['1', '2']]))->isCorrect)
            ->toBeFalse();
    });
});

describe('cümle kurma', function (): void {
    $grader = new WordOrderGrader;

    it('kısmi puan vermez — cümle ya kuruludur ya değildir', function () use ($grader): void {
        $result = $grader->grade(
            content(['order' => ['1', '2', '3', '4']]),
            answer(['order' => ['1', '2', '4', '3']]),
        );

        expect($result->isCorrect)->toBeFalse()
            ->and($result->partialScore)->toBe(0.0);
    });
});

describe('sayısal giriş', function (): void {
    $grader = new NumericInputGrader;

    it('tam sayıyı karşılaştırır', function () use ($grader): void {
        expect($grader->grade(content(['value' => 375, 'tolerance' => 0]), answer(['value' => 375]))->isCorrect)->toBeTrue()
            ->and($grader->grade(content(['value' => 375, 'tolerance' => 0]), answer(['value' => 376]))->isCorrect)->toBeFalse();
    });

    it('string olarak gelen sayıyı kabul eder', function () use ($grader): void {
        // İstemci form alanından string gönderebilir; bu hile değil.
        expect($grader->grade(content(['value' => 751]), answer(['value' => '751']))->isCorrect)->toBeTrue();
    });

    it('tolerans içinde yuvarlamayı affeder', function () use ($grader): void {
        expect($grader->grade(content(['value' => 0.333, 'tolerance' => 0.01]), answer(['value' => 0.33]))->isCorrect)
            ->toBeTrue();
    });

    it('sayı olmayan girdiyi yanlış sayar', function () use ($grader): void {
        expect($grader->grade(content(['value' => 375]), answer(['value' => 'üç yüz']))->isCorrect)->toBeFalse();
    });
});

describe('flashcard ve diyagram', function (): void {
    it('flashcard öz değerlendirmeyi kaydeder', function (): void {
        $grader = new FlashcardGrader;

        expect($grader->grade(content([]), answer(['known' => true]))->isCorrect)->toBeTrue()
            ->and($grader->grade(content([]), answer(['known' => false]))->isCorrect)->toBeFalse();
    });

    it('diyagram etiketlemede kısmi puan her zaman açıktır', function (): void {
        // 8 etiketli hücre şemasında 6'yı bilmek gerçek bir bilgidir.
        $grader = new DiagramLabelGrader;
        $labels = ['s1' => 'çekirdek', 's2' => 'mitokondri', 's3' => 'ribozom', 's4' => 'lizozom'];

        $result = $grader->grade(
            content(['labels' => $labels]),
            answer(['labels' => ['s1' => 'çekirdek', 's2' => 'mitokondri', 's3' => 'ribozom', 's4' => 'yanlış']]),
        );

        expect($result->partialScore)->toBe(0.75)
            ->and($result->isCorrect)->toBeFalse();
    });
});
