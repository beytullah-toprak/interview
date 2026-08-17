# Turkpin API Entegrasyon Projesi — Teknik Kararlar

Bu doküman, projede alınan teknik kararları ve tamamlanan işleri özetler.

---

## Mimari Genel Bakış

Proje, mevcut yapı (Smarty + Bramus Router + PSR-4) korunarak, üstüne katmanlı
bir servis mimarisi eklenerek geliştirildi. Sorumluluklar şöyle ayrıldı:

```
src/
  Api/
    TurkpinApiClient.php      # Ham HTTP/XML iletişimi (curl + SimpleXML)
  Services/
    GameService.php           # epinOyunListesi iş mantığı
    ProductService.php        # epinUrunleri iş mantığı
    OrderService.php          # epinSiparisYarat + sipariş iş akışı
  Validators/
    OrderValidator.php        # min/max/stok kural doğrulaması (localize edilmiş mesajlar)
  Helpers/
    AssetHelper.php           # CSS/JS cache-busting (versiyonlama)
    Logger.php                # storage/logs/api.log'a dosya tabanlı API hata logu
  classes/
    Main.php                  # Uygulama başlatma + route tanımları
    Home.php                  # Ana sayfa controller'ı (mevcut yapı korundu)
  languages/                  # tr.php / en.php (key setleri birebir eşleşiyor)
  templates/                  # index.html / home.html
assets/
  css/app.css
  js/app.js
tests/
  Validators/OrderValidatorTest.php
  Api/TurkpinApiClientTest.php
  Services/GameServiceTest.php
  Services/ProductServiceTest.php
  Services/OrderServiceTest.php
```

Katman ayrımının mantığı: `TurkpinApiClient` API'ye "nasıl" konuşulacağını bilir
(curl, XML), Service'ler "ne" isteneceğini bilir (iş kuralları), route'lar
(Main.php) sadece isteği alıp servise devreder ve cevabı döner.

---

## Tamamlanan Görevler

### 1. Oyun Listesi
- Oyunlar `GameService::getGames()` ile gerçek API'den (epinOyunListesi) çekiliyor.
- Ana sayfada dropdown'da gösteriliyor, başlangıçta hiçbir oyun seçili değil
  ("Tüm Oyunlar / All Games" varsayılan seçenek).

### 2. Ürün Listesi
- Oyun seçilince `ProductService::getProducts($gameId)` ile o oyunun ürünleri
  (epinUrunleri) çekilip mevcut tablo yapısında gösteriliyor.
- Oyun seçilmediğinde ürün listesi görünmüyor, bunun yerine bilgilendirme
  mesajı gösteriliyor.
- Oyun değişince liste otomatik güncelleniyor (sayfa yenilemesiyle — bkz.
  "Bilinen Eksikler").

### 3. Sipariş Sistemi
- Sipariş `OrderService::createOrder()` ile backend üzerinden API'ye
  (epinSiparisYarat) gönderiliyor.
- **Çift gönderim engelleme:** her sipariş öncesi `app.js` önce `/order-token`
  endpoint'inden tek kullanımlık bir token alır, ardından bu token ile
  `/order`'a POST atar. Sunucu tarafı aynı token'ın ikinci kez kullanılmasını
  reddeder (409). Buton, istek sürerken disable ediliyor.
- **Client-side doğrulama:** adet alanı boş/0 girilirse ya da ürünün gerçek
  min/max sınırlarının dışına çıkılırsa (input'un `min`/`max` attribute'ları
  okunarak) gönderim öncesi localize edilmiş bir uyarı gösteriliyor.
- **Server-side doğrulama:** `OrderValidator`, kullanıcının tarayıcıdan
  gönderdiği değerlere güvenmeden ürünü tekrar API'den çekip gerçek
  min_order/max_order/stock değerleriyle kontrol ediyor.

### 4. Sonuç Gösterimi
- Sipariş sonucu SweetAlert2 modal ile gösteriliyor.
- Başarılı/başarısız durumlar farklı ikon ve stillerle sunuluyor.
- Başarılı siparişte sipariş no ve tutar gösteriliyor.
- Hata durumunda API'den veya sunucudan gelen anlaşılır mesaj gösteriliyor.
- Modal mesajları `window.LANG` (Main.php'de $lang dizisinden `json_encode`
  ile üretilir) üzerinden TR/EN dil desteğine tam uyumlu.

---

## Mevcut Projede Tespit Edilen ve Düzeltilen Sorunlar

1. **Sipariş akışı fiilen çalışmıyordu** — `app.js`, `/order`'a POST atmadan
   önce `/order-token`'ı hiç çağırmıyordu. Sunucudaki çift-gönderim kontrolü
   bu yüzden her isteği token eksik/geçersiz diye 409 ile reddediyordu; yani
   sipariş verme özelliği baştan sona kırıktı. `app.js` yeniden yazılarak
   token akışı, buton disable ve gerçek hata/başarı ayrımı eklendi.
2. **TR/EN dil desteği yarım kalmıştı** — `en.php`'de SweetAlert2 mesajları
   için hazırlanmış key'ler (`order_success`, `order_no`, `network_error` vb.)
   vardı ama `tr.php`'ye hiç eklenmemişti; ayrıca bu key'ler `app.js`'e hiç
   bağlanmamıştı (JS'teki tüm mesajlar sabit Türkçe metindi). `Main.php`,
   `Home.php`, `OrderValidator` ve `home.html` içindeki sabit Türkçe hata
   mesajları da aynı şekilde hardcoded'du. İki dil dosyasının key setleri
   birebir eşitlendi, `window.LANG` köprüsü eklendi, tüm bu katmanlar
   `$lang` dizisinden okuyacak şekilde güncellendi.
3. **Session null uyarısı** — `Main.php`'de `$_SESSION['lang']` key'i yokken
   uyarı veriyordu; null coalescing (`??`) ile düzeltildi.
4. **Dosya adı büyük/küçük harf uyumsuzluğu** — `Main.php` içinde
   `require_once 'home.php'` yazıyordu ama dosya adı `Home.php`. macOS'ta
   çalışıyor ama case-sensitive Linux sunucuda Fatal Error verirdi.
   `__DIR__ . '/Home.php'` ile düzeltildi.
5. **Kullanılmayan bağımlılık** — `composer.json`'daki `aura/router` hiç
   kullanılmıyordu, kaldırıldı.
6. **Dil değişiminde seçili oyun kayboluyordu** — footer'daki dil formu
   sadece `lang` alanını gönderdiği için, dil değiştirilince URL'deki
   `game_id` düşüyor ve ürün listesi sıfırlanıyordu. Forma gizli bir
   `game_id` alanı eklendi.
7. **`max_order=0` (API'de "sınırsız" anlamına geliyor) template'te yanlış
   yorumlanıyordu** — `<input max="0">` olarak basılıyordu; artık sadece
   gerçek bir üst sınır varsa `max` attribute'u basılıyor.
8. **`curl_close()` çağrısı** — PHP 8.0'dan beri etkisiz, 8.5'te deprecated;
   kaldırıldı.
9. **`ProductService::getProducts()` içinde ölü kod** — `$urun->max_order !== ''`
   bir `SimpleXMLElement`'i string ile karşılaştırıyordu; bu karşılaştırma
   içerik ne olursa olsun her zaman `true` döner (tip farkı), yani "boş ise
   sınırsız" dalı hiçbir zaman çalışmıyordu. `ProductServiceTest` yazılırken
   ortaya çıktı; `(string)` cast'i eklenerek düzeltildi.

---

## Ek Değer Sağlayan Çalışmalar

- **Docker:** Dockerfile (PHP 8.2 + Apache, mod_rewrite/headers) ve
  docker-compose ile tek komutla çalışan ortam.
- **Güvenlik:** `.htaccess` güvenlik başlıkları; credential'lar yalnızca
  `.env`'de (kaynak kod ve commit geçmişinde yok); server-side doğrulama.
- **Asset versiyonlama:** `AssetHelper`, dosya değişiklik zamanına göre
  `?v=...` ekleyerek tarayıcı cache sorununu otomatik çözüyor.
- **APP_DEBUG:** `index.php`, bu değişkene göre `display_errors`/
  `error_reporting`'i gerçekten açıp kapatıyor (dev'de detaylı hata,
  prod'da sessiz).
- **Loglama:** `Helpers\Logger`, `TurkpinApiClient`'taki ağ/parse/API
  hatalarını `storage/logs/api.log`'a satır satır yazıyor.
- **Unit test:** `OrderValidator` (min/max/stok kuralları, `max_order=0`
  "sınırsız" senaryosu dahil), `TurkpinApiClient::parseResponse` (hem
  `<error>/<error_desc>` hem `<HATA_NO>/<HATA_ACIKLAMA>` formatları,
  geçersiz XML) ve `GameService`/`ProductService`/`OrderService` (XML→array
  eşlemesi, `TurkpinApiClient::request()` mock'lanarak) için PHPUnit
  testleri eklendi — bkz. `tests/`. Çalıştırmak için: `vendor/bin/phpunit`.
- **DRY:** `TurkpinApiClient::fromEnv()` ile credential okuma tek yerde.

---

## Eklenen Bağımlılıklar ve Gerekçeleri

- **SweetAlert2 (CDN):** Sonuç modalı için. Bootstrap modal'a göre daha az
  kod ile başarı/hata state'leri sunuyor. Composer bağımlılığı değil, CDN.
- **phpunit/phpunit (dev-only):** `OrderValidator` ve `TurkpinApiClient`'ın
  XML parse mantığını gerçek ağ isteği atmadan test edebilmek için.
  Runtime'a hiçbir etkisi yok (`require-dev`).
- Başka yeni Composer paketi eklenmedi. HTTP için PHP'nin yerleşik cURL'ü,
  XML için SimpleXML kullanıldı — gereksiz bağımlılık eklememek için.

---

## API Hakkında Notlar

- API tek endpoint (`api.php`), `multipart/form-data` içinde `DATA` alanı
  olarak XML alıyor, XML dönüyor.
- API tutarsızlığı: bazı komutlar hata alanını `<error>/<error_desc>`,
  bazıları `<HATA_NO>/<HATA_ACIKLAMA>` olarak dönüyor. `TurkpinApiClient`
  ikisini de kontrol ederek bu tutarsızlığı tek yerde soğuruyor.
- `max_order` alanı `0` veya boş dönebiliyor; ikisi de "sınırsız" anlamına
  geliyor. Bu kural `ProductService`, `OrderValidator` ve template'te
  tutarlı şekilde uygulanıyor.

---

## Bilinen Eksikler / İleride Yapılabilecekler

- Ürün listesi şu an oyun seçiminde sayfa yenilemesiyle geliyor; tam AJAX'a
  çevrilebilir (sipariş gönderimi zaten AJAX).
- Sipariş token'ları (`$_SESSION['order_tokens']`) kullanılmadan terk
  edilirse session'da birikir; kısa bir TTL/temizlik mekanizması eklenebilir.

---

## Kurulum

```bash
git clone https://github.com/beytullah-toprak/interview.git
cd interview
cp .env.example .env    # test kullanıcı bilgilerini gir
docker compose up -d --build
# http://localhost:8080
```

Docker olmadan yerel test için (`php -S localhost:8080 index.php`),
`index.php` içindeki `PHP_SAPI === 'cli-server'` bloğu statik dosyaları
(`assets/*`) doğrudan sunar; Apache/Docker ortamında bu bloğun bir etkisi
yoktur, aynı ayrımı `.htaccess` zaten yapar.
