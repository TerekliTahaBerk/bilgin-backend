<?php

declare(strict_types=1);

return [
    // Oturum bu süre sonunda düşer; yarım bırakılan tur sonsuza kadar açık kalmaz.
    'session_ttl_minutes' => 60,

    /*
     | Hile önleme: bu sürenin altında verilen cevap XP üretmez ve
     | is_suspicious işaretlenir. Tip bazlı alt sınırlar
     | ExerciseType::minimumAnswerMs() içinde.
     |
     | Şüpheli davranış hesabı KAPATMAZ — işaretler ve XP'yi engeller.
     | Yanlış pozitifle öğrenci kaybetmek, birkaç hileciden pahalıdır.
     */
    'suspicious_answer_enabled' => true,

    // Yanlış cevaplar bu kadar gün sonra tekrar kuyruğuna düşer (Faz 2'de SM-2).
    'review_interval_days' => 3,
];
