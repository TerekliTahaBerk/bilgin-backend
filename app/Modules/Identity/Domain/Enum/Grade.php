<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enum;

/**
 * Öğrencinin sınıfı. PathStrategy üniteleri buna göre sıralar: 10. sınıf
 * öğrencisi ile mezun aynı dersi farklı sırada görür.
 */
enum Grade: string
{
    case Sekiz = '8';
    case Dokuz = '9';
    case On = '10';
    case OnBir = '11';
    case OnIki = '12';
    case Mezun = 'mezun';

    /** Bu sınıftaki öğrencinin "şu an işlediği" müfredat seviyesi. */
    public function curriculumLevel(): int
    {
        return match ($this) {
            self::Sekiz => 8,
            self::Dokuz => 9,
            self::On => 10,
            self::OnBir => 11,
            // Mezun tüm müfredatı görmüş sayılır; 12 ile aynı davranır.
            self::OnIki, self::Mezun => 12,
        };
    }

    public function label(): string
    {
        return $this === self::Mezun ? 'Mezun' : "{$this->value}. sınıf";
    }
}
