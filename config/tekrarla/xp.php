<?php

declare(strict_types=1);

/*
 | XP ve level. Node tipi çarpanları Catalog\Domain\Enum\NodeType'ta,
 | zorluk tabanları DifficultyLevel'da — ikisi de içerik şemasının parçası.
 | Burada yalnızca bonuslar ve level eğrisi var.
 */

return [
    'bonuses' => [
        'perfect' => 20,            // hatasız tamamlama
        'first_completion' => 60,   // bir node'un ilk kez bitirilmesi
    ],

    // Tekrar oynamada taban XP bu oranla çarpılır ve first_completion verilmez.
    // Aynı node'u grind ederek lig manipülasyonunu engeller.
    'replay_factor' => 0.5,

    // Node'un "tamamlandı" sayılması için gereken doğruluk.
    'completion_accuracy' => 80,

    // Eşiğin altında kalan oturum da XP alır ama yarısını: emek ödüllendirilir,
    // başarısızlık cezalandırılmaz.
    'below_threshold_factor' => 0.5,

    /*
     | Level eşikleri (kümülatif toplam XP). Tablo bitince doğrusal devam eder.
     | Erken seviyeler hızlı geçilir — ilk oturumda level atlamak güçlü bir kanca.
     */
    'level_thresholds' => [0, 100, 250, 450, 700, 1000, 1350, 1750, 2200, 2700, 3250],
    'level_step_after_table' => 600,
];
