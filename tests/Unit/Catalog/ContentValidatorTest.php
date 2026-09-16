<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Validation\Validator\FillBlankValidator;
use App\Modules\Catalog\Domain\Validation\Validator\MatchingValidator;
use App\Modules\Catalog\Domain\Validation\Validator\MultipleChoiceValidator;
use App\Modules\Catalog\Domain\Validation\Validator\NumericInputValidator;
use App\Modules\Catalog\Domain\Validation\Validator\OrderingValidator;
use App\Modules\Catalog\Domain\Validation\Validator\TrueFalseValidator;

/*
 | İçerik doğrulama — panelde yazılan hatalı sorunun öğrenciye ulaşmasını
 | engelleyen katman.
 |
 | Buradaki her test, olmasa çalışma anında patlayacak ya da daha kötüsü
 | öğrenciyi haksız yere yanlışa düşürecek bir durumu karşılıyor.
 */

describe('çoktan seçmeli', function (): void {
    $validator = new MultipleChoiceValidator;

    $valid = [
        'content' => [
            'stem' => 'Orhun Yazıtları hangi devlete aittir?',
            'options' => [
                ['id' => 'a', 'text' => 'Asya Hun'],
                ['id' => 'b', 'text' => 'II. Göktürk'],
            ],
        ],
        'answer_key' => ['correct_option_id' => 'b'],
    ];

    it('geçerli soruyu kabul eder', function () use ($validator, $valid): void {
        expect($validator->validate($valid['content'], $valid['answer_key'])->passes())->toBeTrue();
    });

    it('şıklar arasında olmayan doğru cevabı reddeder', function () use ($validator, $valid): void {
        // Bu soru ÇÖZÜLEMEZ olurdu: öğrenci ne seçerse seçsin yanlış sayılır
        // ve canını kaybeder.
        $result = $validator->validate($valid['content'], ['correct_option_id' => 'z']);

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain('şıklar arasında yok');
    });

    it('tek şıklı soruyu reddeder', function () use ($validator): void {
        $result = $validator->validate(
            ['stem' => 'Soru', 'options' => [['id' => 'a', 'text' => 'Tek şık']]],
            ['correct_option_id' => 'a'],
        );

        expect($result->passes())->toBeFalse();
    });

    it('tekrar eden şık id\'sini yakalar', function () use ($validator): void {
        $result = $validator->validate([
            'stem' => 'Soru',
            'options' => [['id' => 'a', 'text' => 'Bir'], ['id' => 'a', 'text' => 'İki']],
        ], ['correct_option_id' => 'a']);

        expect($result->passes())->toBeFalse()
            ->and(implode(' ', $result->errors))->toContain('tekrar eden id');
    });

    it('boş soru kökünü reddeder', function () use ($validator, $valid): void {
        $result = $validator->validate(['stem' => '   '] + $valid['content'], $valid['answer_key']);

        expect($result->passes())->toBeFalse();
    });
});

describe('doğru-yanlış', function (): void {
    $validator = new TrueFalseValidator;

    it('string "true" cevabını reddeder', function () use ($validator): void {
        // Grader katı karşılaştırma yapıyor; string cevap her zaman yanlış
        // sayılırdı ve hata soru çözülene kadar görünmezdi.
        $result = $validator->validate(['statement' => 'Uygurlar yerleşiktir.'], ['value' => 'true']);

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain('true veya false');
    });

    it('boolean cevabı kabul eder', function () use ($validator): void {
        expect($validator->validate(['statement' => 'İfade'], ['value' => false])->passes())->toBeTrue();
    });
});

describe('boşluk doldurma', function (): void {
    $validator = new FillBlankValidator;

    it('boşluksuz şablonu reddeder', function () use ($validator): void {
        $result = $validator->validate(
            ['template' => 'Burada boşluk yok.', 'choices' => ['Töre']],
            ['blanks' => ['Töre']],
        );

        expect($result->passes())->toBeFalse();
    });

    it('boşluk ve cevap sayısı uyuşmazsa reddeder', function () use ($validator): void {
        // Uyuşmazlıkta grader HER ZAMAN "yanlış" döner.
        $result = $validator->validate(
            ['template' => '{{0}} ve {{1}} kavramları.', 'choices' => ['Töre', 'Kut']],
            ['blanks' => ['Töre']],
        );

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain('2 boşluk var ama 1 cevap');
    });

    it('seçenekler arasında olmayan doğru cevabı reddeder', function () use ($validator): void {
        $result = $validator->validate(
            ['template' => '{{0}} denir.', 'choices' => ['Kut', 'Toy']],
            ['blanks' => ['Töre']],
        );

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain('seçenekler arasında yok');
    });

    it('geçerli soruyu kabul eder', function () use ($validator): void {
        expect($validator->validate(
            ['template' => 'Yazısız hukuk kurallarına {{0}} denir.', 'choices' => ['Töre', 'Kut']],
            ['blanks' => ['Töre']],
        )->passes())->toBeTrue();
    });
});

describe('eşleştirme', function (): void {
    $validator = new MatchingValidator;

    $content = [
        'left' => [['id' => '1', 'text' => 'Kurultay'], ['id' => '2', 'text' => 'Töre']],
        'right' => [['id' => 'a', 'text' => 'Meclis'], ['id' => 'b', 'text' => 'Hukuk']],
    ];

    it('eşleşmesi eksik sol öğeyi yakalar', function () use ($validator, $content): void {
        // Öğrenci hepsini doğru yapsa bile tam puan alamazdı.
        $result = $validator->validate($content, ['pairs' => ['1' => 'a']]);

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain("'2' için eşleşme tanımlanmamış");
    });

    it('tanımsız sağ öğeye işaret eden eşleşmeyi reddeder', function () use ($validator, $content): void {
        $result = $validator->validate($content, ['pairs' => ['1' => 'a', '2' => 'z']]);

        expect($result->passes())->toBeFalse()
            ->and(implode(' ', $result->errors))->toContain('tanımsız sağ öğe');
    });

    it('tam eşleşmeyi kabul eder', function () use ($validator, $content): void {
        expect($validator->validate($content, ['pairs' => ['1' => 'a', '2' => 'b']])->passes())->toBeTrue();
    });
});

describe('sıralama', function (): void {
    $validator = new OrderingValidator;

    $content = [
        'instruction' => 'Eskiden yeniye sırala.',
        'items' => [
            ['id' => '1', 'text' => 'Göktürkler'],
            ['id' => '2', 'text' => 'Uygurlar'],
            ['id' => '3', 'text' => 'Karahanlılar'],
        ],
    ];

    it('eksik öğe içeren sırayı reddeder', function () use ($validator, $content): void {
        $result = $validator->validate($content, ['order' => ['1', '2']]);

        expect($result->passes())->toBeFalse()
            ->and($result->errors[0])->toContain('tam olarak bir kez');
    });

    it('tekrar eden öğe içeren sırayı reddeder', function () use ($validator, $content): void {
        expect($validator->validate($content, ['order' => ['1', '2', '2']])->passes())->toBeFalse();
    });

    it('tam sırayı kabul eder', function () use ($validator, $content): void {
        expect($validator->validate($content, ['order' => ['3', '1', '2']])->passes())->toBeTrue();
    });
});

describe('sayısal giriş', function (): void {
    $validator = new NumericInputValidator;

    it('sayı olmayan cevabı reddeder', function () use ($validator): void {
        expect($validator->validate(['stem' => 'Kaç?'], ['value' => 'üç yüz'])->passes())->toBeFalse();
    });

    it('negatif toleransı reddeder', function () use ($validator): void {
        expect($validator->validate(['stem' => 'Kaç?'], ['value' => 375, 'tolerance' => -1])->passes())
            ->toBeFalse();
    });

    it('geçerli soruyu kabul eder', function () use ($validator): void {
        expect($validator->validate(['stem' => 'Kaç?'], ['value' => 375, 'tolerance' => 0])->passes())
            ->toBeTrue();
    });
});
