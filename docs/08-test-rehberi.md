# 08 · Test Rehberi

Uygulamayı gerçek içerikle uçtan uca denemek için.

---

## 1. Önce sunucuyu hazırla

Coolify'da **Redeploy**. Konteyner açılışında provision şunları yükler:

```
TopicSeeder ............. 11 dersin konu listesi (~250 konu)
ContentPackageSeeder .... 10 ünite, 388 soru
```

Her seeder yalnızca kendi tablosu boşsa çalışır; ikinci deploy'da geçer ve
panelden girilen içeriği ezmez.

Sonra lig ve profil ekranlarının dolu görünmesi için:

```bash
php artisan demo:seed --force     # üretimde --force şart
```

Bu, 14 sahte öğrenci üretip dağılmış XP veriyor. Geri almak için
`php artisan demo:clear`.

---

## 2. Ne var elimizde

| Ders | Ünite | Soru |
|---|---|---|
| TYT Türkçe | Anlam Bilgisi · Dil Bilgisi | 79 |
| TYT Matematik | Sayılar ve Bölünebilme · Problemler | 81 |
| TYT Tarih | İlk ve Orta Çağlarda Türk Dünyası | 44 |
| TYT Fizik | Hareket ve Kuvvet | 40 |
| TYT Kimya | Atom ve Periyodik Sistem | 40 |
| TYT Biyoloji | Hücre ve Canlıların Yapısı | 40 |
| AYT Matematik | Türev | 24 |
| AYT Fizik | Vektörler ve Hareket | 40 |

**Türkçe ve Matematik'te ikişer ünite var** — yol ilerlemesini ve ünite
kilidini ancak orada görebilirsin.

Sekiz alıştırma tipinin hepsi temsil ediliyor: çoktan seçmeli, doğru/yanlış,
boşluk doldurma, eşleştirme, sıralama, kelime dizme, sayısal giriş, bilgi
kartı. `image_hotspot` ve `diagram_label` yok — görsel altyapısı ne
backend'de ne mobilde var.

---

## 3. Mobil: sırayla denenecekler

### Kayıt akışı
1. Uygulamayı aç → "Hadi başlayalım"
2. **Sayısal** seç → Devam
3. Sınıf seç → Devam
4. **Adını gir** (ligde görünecek) → Devam. Boş bırakırsan düğme "Geç" demeli.
5. Kaynak seç ya da geç
6. Günlük hedef → **Başla**

Kontrol: profilde adın görünüyor mu, ders listesinde "YKS · Sayısal" yazıyor mu.

### Öğrenme yolu
- Dersler sekmesinde TYT altında 9, AYT altında 4 ders olmalı
- **TYT Matematik**'e gir: iki ünite görmelisin, ikincisi kilitli
- Kilitli düğüme dokun → sebebi yazmalı ("Önceki adımı bitir")
- Açık düğüme dokun → künye (kaç soru, kaç XP, can harcar mı)

### Tur
- Turu başlat, soruları cevapla
- **Yanlış cevap ver**: doğru şık işaretlenmeli, açıklama çıkmalı, can düşmeli
- Üst çubukta ilerleme ve can göstergesi
- Çıkmayı dene → onay sormalı
- Turu bitir → özet: doğruluk, XP, seri, açılan adım

### Canlar
- Can göstergesine dokun → geri sayım işlemeli (saniye saniye)
- Canları tüket → "Canların bitti" ekranı çıkmalı, çıkmaz sokak olmamalı
- "Premium ile sınırsız can" → satış ekranı açılmalı

### Deneme sınavı
- Dersler ekranının sağ üstündeki simge → Denemeler
- Bir deneme başlat → onay penceresi süreyi söylemeli
- **Geri bildirim ÇIKMAMALI** — sınav provasında sonuç anında görünmez
- Soru gezgininden ileri geri atla, soru boş bırak
- Bitir → net, doğru/yanlış/boş ve **tahmini** puan

### Lig
- Bu Hafta sekmesi: `demo:seed` sonrası 15 kişilik tablo
- Kendi satırın kalın çerçeveli ve "(sen)" yazmalı
- Yükselme/düşme okları ve alttaki açıklama

### Başarılar
- Seviye çubuğu, seri, doğru cevap sayısı
- Rozetler: kazanılanlar renkli, kilitlilerde ilerleme ("73/100")
- Konu analizi: zayıf/gelişiyor/güçlü + en zayıf konu

---

## 4. Panel

```
https://bilginbackend.cryptoping.io/api/admin/v1
```

- Giriş → `abilities` alanı menüyü kuruyor
- `GET /courses` → 21 ders
- `GET /courses/{id}/topics` → konu listesi dolu olmalı
- `GET /units/{id}/nodes` → adımlar
- `GET /nodes/{id}/preview-selection` → **iki sayı** döner:
  `available`/`passes` yayın kararını, `live_available`/`live_passes`
  öğrencinin şu an aldığını söyler. Ayrıştıklarında `live_warning` dolu gelir.

---

## 5. Bilerek eksik bırakılanlar

Bunlar hata değil, karşılaşınca şaşırma:

| Ne | Neden |
|---|---|
| Apple/Google giriş | Firebase kurulumu bekliyor |
| Satın alma düğmesi pasif | RevenueCat anahtarı yok |
| "Reklam izle, can kazan" pasif | AdMob reklam birimi yok |
| Hatırlatma saati adımı yok | Push bildirimi yok — kurulan hatırlatma çalmaz, o yüzden sorulmuyor |
| Görsel soru tipleri | Medya yükleme altyapısı yok |

Pasif düğmeler bilerek **görünür**: gizlemek seçeneğin varlığını saklardı,
aktif göstermek çalışmayan bir şeye tıklatırdı.

---

## 6. Bir sorun bulursan

Mobilde: `flutter logs` ya da hata ekranındaki metin.
Sunucuda: Coolify → Logs. `LOG_LEVEL=warning` olduğu için yalnızca gerçek
sorunlar düşer.

Sık karşılaşılan iki durum ve anlamı:

- **"Bu adım henüz hazır değil"** → o düğümün kuralı yeterli soru
  getirmiyor. İçerik sorunu, kod sorunu değil.
- **Tur ortasında 500** → sunucu logunda gerçek sebep var; ekrandaki
  mesaj bilerek genel.
