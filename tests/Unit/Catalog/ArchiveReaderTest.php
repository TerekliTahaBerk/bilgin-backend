<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\QuestionDrop\ArchiveReader;

/*
 | PAKETLENMİŞ SORU ARŞİVİ.
 |
 | Arşiv depoda duruyor ve sunucuda ham paket YOK. Yani arşiv bozulursa
 | ya da sessizce eksik okunursa yerine bakılacak bir kaynak kalmıyor.
 | Buradaki testler "doğru okuyor mu"dan çok BOZUK SATIRI SESSİZCE
 | YUTUYOR MU sorusunu soruyor.
 */

function arsiv(array $lines): string
{
    $file = tempnam(sys_get_temp_dir(), 'arsiv').'.ndjson.gz';
    $handle = gzopen($file, 'wb');

    foreach ($lines as $line) {
        gzwrite($handle, (is_string($line) ? $line : json_encode($line, JSON_UNESCAPED_UNICODE)).PHP_EOL);
    }

    gzclose($handle);

    return $file;
}

function satir(string $topic = 'kavimler_gocu'): array
{
    return [
        't' => $topic,
        'y' => 'multiple_choice',
        'd' => 3,
        's' => ['tyt'],
        'c' => ['stem' => 'Kavimler Göçü hangi yılda?', 'options' => [
            ['id' => 'a', 'text' => '375'],
            ['id' => 'b', 'text' => '395'],
        ]],
        'a' => ['correct_option_id' => 'a'],
        'e' => 'Hunların batıya göçüyle başladı.',
    ];
}

it('arşivdeki satırı bizim şemamıza çevirir', function (): void {
    $out = iterator_to_array((new ArchiveReader)->read(arsiv([satir()])));

    expect($out)->toHaveCount(1)
        ->and($out[0]['topic_code'])->toBe('kavimler_gocu')
        ->and($out[0]['type'])->toBe('multiple_choice')
        ->and($out[0]['difficulty'])->toBe(3)
        ->and($out[0]['scopes'])->toBe(['tyt'])
        ->and($out[0]['explanation'])->toBe('Hunların batıya göçüyle başladı.')
        ->and($out[0]['content']['stem'])->toBe('Kavimler Göçü hangi yılda?');
});

it('Türkçe karakterler gzip turunda bozulmuyor', function (): void {
    $out = iterator_to_array((new ArchiveReader)->read(arsiv([satir()])));

    // Arşiv gzip + JSON iki katmandan geçiyor; biri bozuksa Türkçe
    // karakterler mojibake olur ve 13 bin soru okunamaz hâle gelir.
    expect($out[0]['content']['stem'])->toContain('Göçü');
});

it('bozuk satırı atlar ama RAPOR EDER', function (): void {
    $reader = new ArchiveReader;
    $out = iterator_to_array($reader->read(arsiv([satir('a'), '{bozuk json', satir('b')])));

    /*
     | Tek bozuk satır tüm aktarmayı durdurmamalı — 13 bin sorunun
     | 13.614'ü sağlamken hepsini reddetmek işe yaramaz. Ama sessizce
     | geçmek de olmaz: hangi satır olduğu raporlanmazsa arşivin
     | eksik olduğu asla fark edilmez.
    */
    expect($out)->toHaveCount(2)
        ->and($reader->skipped())->toHaveCount(1)
        ->and($reader->skipped()[0])->toContain('satır 2');
});

it('eksik alanlı satırı atlar', function (): void {
    $reader = new ArchiveReader;
    $eksik = satir();
    unset($eksik['t']);

    $out = iterator_to_array($reader->read(arsiv([$eksik, satir()])));

    // Konu kodu olmayan bir soru hiçbir yere bağlanamaz; yazılırsa
    // veritabanında sahipsiz bir satır bırakır.
    expect($out)->toHaveCount(1)
        ->and($reader->skipped())->toHaveCount(1);
});

it('boş satırları hata saymaz', function (): void {
    $reader = new ArchiveReader;
    $out = iterator_to_array($reader->read(arsiv([satir(), '', satir('b')])));

    expect($out)->toHaveCount(2)
        ->and($reader->skipped())->toBe([]);
});

it('açılamayan dosyada patlamaz, rapor eder', function (): void {
    $reader = new ArchiveReader;
    $out = iterator_to_array($reader->read('/olmayan/dosya.ndjson.gz'));

    expect($out)->toBe([])
        ->and($reader->skipped())->toHaveCount(1);
});

it('eksik kapsamı boş bırakmaz', function (): void {
    $satir = satir();
    unset($satir['s']);

    $out = iterator_to_array((new ArchiveReader)->read(arsiv([$satir])));

    // Kapsam boş kalırsa soru hiçbir sınavda seçilmez: sessizce
    // kaybolmuş olur.
    expect($out[0]['scopes'])->toBe(['tyt']);
});

it('depodaki gerçek arşiv okunabiliyor', function (): void {
    // Birim testi uygulamayı ayağa kaldırmıyor; `database_path()` yok.
    $file = __DIR__.'/../../../database/question-drop/sorular.ndjson.gz';

    if (! is_file($file)) {
        $this->markTestSkipped('Arşiv henüz paketlenmemiş.');
    }

    $reader = new ArchiveReader;
    $count = 0;
    $first = null;

    foreach ($reader->read($file) as $row) {
        $first ??= $row;
        $count++;
    }

    /*
     | Bu test arşivin KENDİSİNİ doğruluyor.
     |
     | Dosya ikili ve depoda duruyor; bozuk bir birleştirme ya da
     | yanlış satır sonu çevirisi onu sessizce okunamaz hâle
     | getirebilir. O gün bu test kırmızı yanıyor; aksi hâlde sorun
     | ancak sunucuda aktarma sıfır soru yazdığında anlaşılırdı.
    */
    expect($count)->toBeGreaterThan(10_000)
        ->and($reader->skipped())->toBe([])
        ->and($first['topic_code'])->not->toBeEmpty();
});
