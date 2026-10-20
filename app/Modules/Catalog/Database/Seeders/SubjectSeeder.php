<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Subject;
use Illuminate\Database\Seeder;

/**
 * Kavramsal dersler. Sınavdan ve oturumdan bağımsızdır.
 *
 * Türkçe ile Türk Dili ve Edebiyatı ayrı subject'tir: TYT Türkçe paragraf ve
 * dil bilgisi, AYT Edebiyat edebi akımlar ve metin tahlili — konu ağaçları
 * neredeyse hiç kesişmez, birleştirmek analizi bozar.
 */
final class SubjectSeeder extends Seeder
{
    private const SUBJECTS = [
        ['code' => 'turkce', 'name' => 'Türkçe', 'color' => '#14976B', 'icon' => 'book-open'],
        ['code' => 'matematik', 'name' => 'Matematik', 'color' => '#0C4A38', 'icon' => 'sigma'],
        ['code' => 'tarih', 'name' => 'Tarih', 'color' => '#8A6D3B', 'icon' => 'landmark'],
        ['code' => 'cografya', 'name' => 'Coğrafya', 'color' => '#1F7A8C', 'icon' => 'globe'],
        ['code' => 'felsefe', 'name' => 'Felsefe', 'color' => '#6B5B95', 'icon' => 'brain'],
        ['code' => 'din_kulturu', 'name' => 'Din Kültürü ve Ahlak Bilgisi', 'color' => '#4A6FA5', 'icon' => 'moon'],
        ['code' => 'fizik', 'name' => 'Fizik', 'color' => '#B5651D', 'icon' => 'atom'],
        ['code' => 'kimya', 'name' => 'Kimya', 'color' => '#2E8B57', 'icon' => 'flask'],
        ['code' => 'biyoloji', 'name' => 'Biyoloji', 'color' => '#3C8D3C', 'icon' => 'leaf'],
        ['code' => 'edebiyat', 'name' => 'Türk Dili ve Edebiyatı', 'color' => '#9B2C2C', 'icon' => 'feather'],
        ['code' => 'ingilizce', 'name' => 'İngilizce', 'color' => '#2B6CB0', 'icon' => 'languages'],

        /*
         | KPSS'ye özgü iki ders. YKS'de karşılıkları yok; ayrı birer
         | dal olmaları gerekiyor çünkü konuları da ayrı.
         |
         | "Güncel Bilgiler" diğerlerinden farklı: içeriği her yıl
         | tamamen değişiyor. Konu ağacı sabit kalır ama soruların
         | yıllık tazelenmesi gerekir.
        */
        // LGS'de fizik/kimya/biyoloji ayrı okutulmuyor; tek ders.
        ['code' => 'fen_bilimleri', 'name' => 'Fen Bilimleri', 'color' => '#2F855A', 'icon' => 'microscope'],
        ['code' => 'vatandaslik', 'name' => 'Vatandaşlık', 'color' => '#B7791F', 'icon' => 'scale'],
        ['code' => 'guncel_bilgiler', 'name' => 'Güncel Bilgiler', 'color' => '#4C7A8C', 'icon' => 'newspaper'],
    ];

    public function run(): void
    {
        foreach (self::SUBJECTS as $index => $subject) {
            Subject::query()->updateOrCreate(
                ['code' => $subject['code']],
                [...$subject, 'sort_order' => $index + 1],
            );
        }
    }
}
