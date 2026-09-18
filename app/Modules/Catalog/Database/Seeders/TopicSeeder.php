<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * YKS konu listesi.
 *
 * Konular İÇERİK DEĞİL, müfredat yapısıdır: başlıklar MEB kazanım
 * listesinden geliyor ve yıldan yıla neredeyse hiç değişmiyor. Bu yüzden
 * yapı seeder'larının arasında duruyor, `PilotContentSeeder` gibi
 * local'e kapatılmıyor.
 *
 * Bunlar olmadan panel çalışamıyor: hem `POST /exercises` hem `POST /units`
 * var olan bir `topic_id` istiyor. Konu tablosu boşken içerik ekibi tek bir
 * soru bile giremez.
 *
 * Konular DERS (subject) seviyesinde tutuluyor, kurs (course) seviyesinde
 * değil. Sebep: "Türev" konusu hem TYT hem AYT Matematik'te geçiyor ve
 * öğrencinin o konudaki ustalığı tek olmalı — ikiye bölünürse konu analizi
 * yanlış sonuç verir.
 */
final class TopicSeeder extends Seeder
{
    /**
     * Ders kodu → konu başlıkları.
     *
     * Sıra anlamlı: öğretim sırasına göre dizili ve `sort_order` buradan
     * üretiliyor. Panelde konu seçici bu sırayla görünüyor.
     *
     * @var array<string, list<string>>
     */
    private const TOPICS = [
        'turkce' => [
            'Sözcükte Anlam',
            'Cümlede Anlam',
            'Paragrafta Anlam',
            'Paragrafta Anlatım Teknikleri',
            'Düşünceyi Geliştirme Yolları',
            'Ses Bilgisi',
            'Yazım Kuralları',
            'Noktalama İşaretleri',
            'Sözcükte Yapı',
            'İsimler',
            'Zamirler',
            'Sıfatlar',
            'Zarflar',
            'Edat, Bağlaç ve Ünlem',
            'Fiiller',
            'Fiilimsiler',
            'Fiilde Çatı',
            'Cümlenin Ögeleri',
            'Cümle Türleri',
            'Anlatım Bozuklukları',
        ],

        'edebiyat' => [
            'Güzel Sanatlar ve Edebiyat',
            'Şiir Bilgisi ve Ölçü',
            'Söz Sanatları',
            'Nazım Biçimleri ve Türleri',
            'İslamiyet Öncesi Türk Edebiyatı',
            'Geçiş Dönemi Eserleri',
            'Divan Edebiyatı',
            'Halk Edebiyatı',
            'Tanzimat Edebiyatı',
            'Servet-i Fünun Edebiyatı',
            'Fecr-i Âti Edebiyatı',
            'Millî Edebiyat',
            'Cumhuriyet Dönemi Şiiri',
            'Cumhuriyet Dönemi Romanı ve Hikâyesi',
            'Edebî Akımlar',
            'Dünya Edebiyatı',
            'Tiyatro',
            'Deneme, Makale ve Eleştiri',
        ],

        'matematik' => [
            'Temel Kavramlar',
            'Sayı Basamakları',
            'Bölme ve Bölünebilme',
            'EBOB ve EKOK',
            'Rasyonel Sayılar',
            'Basit Eşitsizlikler',
            'Mutlak Değer',
            'Üslü Sayılar',
            'Köklü Sayılar',
            'Çarpanlara Ayırma',
            'Oran ve Orantı',
            'Denklem Çözme',
            'Sayı Problemleri',
            'Kesir Problemleri',
            'Yaş Problemleri',
            'İşçi ve Havuz Problemleri',
            'Hareket Problemleri',
            'Yüzde, Kâr-Zarar Problemleri',
            'Karışım Problemleri',
            'Grafik Problemleri',
            'Kümeler',
            'Kartezyen Çarpım',
            'Mantık',
            'Fonksiyonlar',
            'Polinomlar',
            'İkinci Dereceden Denklemler',
            'Parabol',
            'Permütasyon, Kombinasyon ve Binom',
            'Olasılık',
            'İstatistik',
            'Karmaşık Sayılar',
            'Logaritma',
            'Diziler',
            'Limit ve Süreklilik',
            'Türev',
            'İntegral',
            'Trigonometri',
            'Doğruda ve Üçgende Açılar',
            'Özel Üçgenler',
            'Üçgende Alan',
            'Üçgende Açıortay ve Kenarortay',
            'Üçgende Benzerlik',
            'Çokgenler',
            'Dörtgenler ve Paralelkenar',
            'Yamuk',
            'Eşkenar Dörtgen, Dikdörtgen ve Kare',
            'Çember ve Daire',
            'Katı Cisimler',
            'Analitik Geometri',
            'Dönüşüm Geometrisi',
            'Vektörler',
        ],

        'fizik' => [
            'Fizik Bilimine Giriş',
            'Madde ve Özellikleri',
            'Sıvıların Kaldırma Kuvveti',
            'Basınç',
            'Isı, Sıcaklık ve Genleşme',
            'Hareket ve Kuvvet',
            'Newton Hareket Yasaları',
            'İş, Güç ve Enerji',
            'Elektrostatik',
            'Elektrik Akımı ve Devreler',
            'Manyetizma ve Elektromanyetik İndüklenme',
            'Dalgalar',
            'Optik',
            'Vektörler',
            'Tork ve Denge',
            'Basit Makineler',
            'Bağıl Hareket',
            'Atışlar',
            'İtme ve Momentum',
            'Çembersel Hareket',
            'Basit Harmonik Hareket',
            'Kütle Çekim ve Kepler Yasaları',
            'Elektromanyetik Dalgalar',
            'Atom Fiziğine Giriş ve Radyoaktivite',
            'Modern Fizik',
            'Modern Fiziğin Teknolojideki Uygulamaları',
        ],

        'kimya' => [
            'Kimya Bilimi',
            'Atom ve Periyodik Sistem',
            'Kimyasal Türler Arası Etkileşimler',
            'Maddenin Hâlleri',
            'Doğa ve Kimya',
            'Kimyanın Temel Kanunları',
            'Mol Kavramı',
            'Kimyasal Hesaplamalar',
            'Karışımlar',
            'Asitler, Bazlar ve Tuzlar',
            'Kimya Her Yerde',
            'Modern Atom Teorisi',
            'Gazlar',
            'Sıvı Çözeltiler ve Çözünürlük',
            'Kimyasal Tepkimelerde Enerji',
            'Kimyasal Tepkimelerde Hız',
            'Kimyasal Tepkimelerde Denge',
            'Kimya ve Elektrik',
            'Karbon Kimyasına Giriş',
            'Organik Bileşikler',
            'Enerji Kaynakları ve Bilimsel Gelişmeler',
        ],

        'biyoloji' => [
            'Canlıların Ortak Özellikleri',
            'Canlıların Temel Bileşenleri',
            'Hücre ve Organeller',
            'Hücre Zarından Madde Geçişi',
            'Canlıların Sınıflandırılması',
            'Mitoz ve Eşeysiz Üreme',
            'Mayoz ve Eşeyli Üreme',
            'Kalıtım',
            'Ekosistem Ekolojisi',
            'Güncel Çevre Sorunları',
            'Sinir Sistemi',
            'Endokrin Sistem',
            'Duyu Organları',
            'Destek ve Hareket Sistemi',
            'Sindirim Sistemi',
            'Dolaşım ve Bağışıklık Sistemi',
            'Solunum Sistemi',
            'Üriner Sistem',
            'Üreme Sistemi ve Embriyonik Gelişim',
            'Komünite ve Popülasyon Ekolojisi',
            'Genden Proteine',
            'Canlılarda Enerji Dönüşümleri',
            'Bitki Biyolojisi',
            'Canlılar ve Çevre',
        ],

        'tarih' => [
            'Tarih Bilimi',
            'İlk ve Orta Çağlarda Türk Dünyası',
            'İlk Türk Devletleri',
            'İslam Tarihi ve Uygarlığı',
            'Türk-İslam Devletleri',
            'Türkiye Tarihi ve Anadolu Selçuklu',
            'Beylikten Devlete Osmanlı',
            'Dünya Gücü Osmanlı Devleti',
            'Arayış Yılları',
            'Avrupa ve Osmanlı',
            'En Uzun Yüzyıl',
            'XX. Yüzyıl Başlarında Osmanlı Devleti',
            'Birinci Dünya Savaşı',
            'Millî Mücadele',
            'Atatürkçülük ve Türk İnkılabı',
            'İkinci Dünya Savaşı',
            'Soğuk Savaş Dönemi',
            'Küreselleşen Dünya',
        ],

        'cografya' => [
            'Doğa ve İnsan',
            'Dünya\'nın Şekli ve Hareketleri',
            'Coğrafi Konum',
            'Harita Bilgisi',
            'İklim Bilgisi',
            'İç Kuvvetler',
            'Dış Kuvvetler',
            'Türkiye\'nin Yer Şekilleri',
            'Türkiye\'nin İklimi',
            'Toprak, Bitki ve Su Varlığı',
            'Nüfus ve Yerleşme',
            'Türkiye\'de Nüfus',
            'Ekonomik Faaliyetler',
            'Türkiye\'de Tarım ve Hayvancılık',
            'Türkiye\'de Madenler ve Enerji',
            'Türkiye\'de Sanayi',
            'Türkiye\'de Ulaşım ve Ticaret',
            'Türkiye\'de Turizm',
            'Bölgeler ve Ülkeler',
            'Çevre Sorunları',
            'Doğal Afetler',
        ],

        'felsefe' => [
            'Felsefeyi Tanıma',
            'Bilgi Felsefesi',
            'Varlık Felsefesi',
            'Ahlak Felsefesi',
            'Sanat Felsefesi',
            'Din Felsefesi',
            'Siyaset Felsefesi',
            'Bilim Felsefesi',
            'İlk Çağ Felsefesi',
            'Orta Çağ Felsefesi',
            'Yeni Çağ Felsefesi',
            'Yakın Çağ Felsefesi',
            'Psikolojiye Giriş',
            'Öğrenme ve Bellek',
            'Sosyolojiye Giriş',
            'Toplumsal Yapı ve Değişme',
            'Mantığa Giriş',
            'Klasik ve Sembolik Mantık',
        ],

        'din_kulturu' => [
            'Bilgi ve İnanç',
            'Din ve İslam',
            'İslam ve İbadet',
            'Ahlak ve Değerler',
            'Allah-İnsan İlişkisi',
            'Hz. Muhammed\'in Hayatı',
            'Vahiy ve Akıl',
            'İslam Düşüncesinde Yorumlar',
            'Din ve Hayat',
            'Kur\'an\'dan Mesajlar',
            'İslam ve Bilim',
            'Anadolu\'da İslam',
            'Güncel Dinî Meseleler',
            'Dinler ve Evrensel Öğütleri',
        ],

        'ingilizce' => [
            'Tenses',
            'Modals',
            'Passive Voice',
            'Conditionals',
            'Noun Clauses',
            'Adjective Clauses',
            'Adverbial Clauses',
            'Gerunds and Infinitives',
            'Prepositions',
            'Phrasal Verbs',
            'Conjunctions and Transitions',
            'Vocabulary',
            'Sentence Completion',
            'Cloze Test',
            'Reading Comprehension',
            'Paragraph Completion',
            'Irrelevant Sentence',
            'Dialogue Completion',
            'Translation',
        ],
    ];

    public function run(): void
    {
        $subjects = Subject::query()->pluck('id', 'code');

        $missing = array_diff(array_keys(self::TOPICS), $subjects->keys()->all());

        if ($missing !== []) {
            // Sessizce atlamak yerine bağırıyoruz: ders kodu değişmişse
            // konular yüklenmez ve panel yine kilitli kalır.
            $this->command->warn(
                'Bu derslerin karşılığı yok, konuları atlandı: '.implode(', ', $missing)
            );
        }

        $now = now();
        $rows = [];

        foreach (self::TOPICS as $subjectCode => $titles) {
            $subjectId = $subjects[$subjectCode] ?? null;

            if ($subjectId === null) {
                continue;
            }

            foreach ($titles as $index => $title) {
                $rows[] = [
                    'subject_id' => $subjectId,
                    'parent_id' => null,
                    'code' => $this->codeFor($title),
                    'name' => $title,
                    'grade_level' => null,
                    'exam_weight' => null,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // upsert: ikinci çalıştırmada ad ve sıra güncellenir, kimlikler korunur.
        // Kimliklerin korunması şart — soru ve üniteler onlara bağlı.
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('topics')->upsert(
                $chunk,
                ['subject_id', 'code'],
                ['name', 'sort_order', 'updated_at'],
            );
        }
    }

    /**
     * Başlıktan kararlı bir kod üretir.
     *
     * Kod, upsert'ün kimlik anahtarı: değişirse aynı konu ikinci kez eklenir
     * ve öğrencinin o konudaki geçmişi ikiye bölünür. Bu yüzden Türkçe
     * karakterler sabit bir eşlemeyle çevriliyor — `Str::slug`'ın yerel
     * ayara göre değişebilen davranışına bırakılmıyor.
     */
    private function codeFor(string $title): string
    {
        $ascii = strtr($title, [
            'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);

        return Str::of($ascii)->lower()->slug('_')->limit(96, '')->value();
    }
}
