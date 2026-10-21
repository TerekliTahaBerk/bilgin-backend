# Soru paketi — sunucuya aktarma

İçerik ekibinin gönderdiği soru paketi bu klasörde **ham hâliyle
durmuyor**. Ham paket 308 MB ve 1652 dosya; büyük kısmı ilerleme raporu,
doğrulama çıktısı ve aynı sorunun eski sürümleri. Depoya koymak her
klonlamaya 308 MB bindirmek olurdu ve o veri bir daha hiç okunmayacaktı.

Bunun yerine paket **bir kez yerelde indirgenip** depoya girdi:

| dosya | boyut | ne |
|---|---|---|
| `sorular.ndjson.gz` | 3,1 MB | 13.615 soru, konu kodu çözülmüş, doğrulanmış, tekrarları ayıklanmış |
| `sorular.meta.json` | 264 B | arşivin özeti — kaç soru, hangi tip, ne zaman üretildi |
| `topic-map.json` | 434 KB | ekibin konu adlarını bizim taksonomimize bağlayan onaylı eşleme |

## Sunucuda çalıştırma

Ham klasöre **ihtiyaç yok**. Deponun içindeki arşiv yeterli:

```bash
php artisan content:paket-aktar database/question-drop/sorular.ndjson.gz
php artisan content:tip-turet
```

Beklenen sonuç: 13.615 çoktan seçmeli soru + 5.916 türetilmiş soru
(sayısal giriş, bilgi kartı, eşleştirme) = **241 konuda 20.511 soru**.

Hepsi `draft` ve `pending_editorial_review` olarak giriyor. Yayına almak
editörün kararı — `--yayinla` bayrağı bilinçli bir tercih olsun diye var,
varsayılan değil.

### İki kez çalıştırmak güvenli

Her sorunun kimliği `sha256(konu + tip + içerik)` ile üretiliyor, yani
aynı soru ikinci kez yazılmıyor. Komutu tekrar çalıştırmak "yazılan 0,
zaten vardı 13615" veriyor. Yarıda kesilen bir aktarma da kaldığı yerden
devam edebilir.

### Önce denemek

```bash
php artisan content:paket-aktar database/question-drop/sorular.ndjson.gz --deneme
```

Hiçbir şey yazmadan ne olacağını raporluyor.

## Arşivi yeniden üretme (yalnızca yerelde)

Ekip yeni soru gönderdiğinde ya da eşleme güncellendiğinde:

```bash
# 1. Ekibin konu adlarını bizimkilerle eşleştiren raporu üret
php artisan content:paket-tara ~/Downloads/bilgin-app-questions

# 2. topic-map.json içindeki our_code alanlarını doldur/gözden geçir

# 3. Arşivi yeniden paketle
php artisan content:paket-paketle ~/Downloads/bilgin-app-questions

# 4. Depoya al
git add database/question-drop && git commit -m "içerik: soru arşivi güncellendi"
```

Arşiv her yeniden paketlemede yeni bir 3 MB'lık ikili nesne olarak git
geçmişine ekleniyor. Yılda birkaç kez güncellenecek bir dosya için bu
kabul edilebilir; ayda birkaç kez güncellenmeye başlarsa Git LFS'e
geçmek gerekir.

## Eşlemesi olmayan 3.342 soru

Arşiv paketlenirken 3.342 soru **bilerek dışarıda kaldı**. Sebebi ekibin
konu kırılımı bizimkinden daha kaba: tek bir "Cumhuriyet Dönemi" satırı
altında 258 soru var, bizde ise o dönem beş ayrı konuya bölünmüş.

Tahminle yerleştirmek konu bazlı analizi sessizce bozardı — öğrenci
"Çok Partili Hayat" konusunda zayıf görünürken aslında soruların yarısı
başka konudan gelmiş olurdu. Bu yüzden bu satırların `our_code` alanı boş
bırakıldı ve sebebi `topic-map.json` içine yazıldı.

Çözülmesi içerik kararı: ya ekip bu başlıkları bölerek yeniden gönderir,
ya da biz o konuları birleştiririz.
