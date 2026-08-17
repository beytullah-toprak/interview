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
    Main.php                  # Uygulama başlatma + route tanımları (ince katman)
    Home.php                  # Ana sayfa controller'ı (mevcut yapı korundu)
    OrderController.php       # /order-token + /order uçları, sipariş akışı
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
(curl, XML), Service'ler "ne" isteneceğini bilir (iş kuralları), Controller'lar
(`Home`, `OrderController`) isteği alıp servise devreder ve cevabı döner,
`Main.php` ise yalnızca "hangi URL hangi controller'a gider" sorusunu cevaplar.

Başlangıçta sipariş mantığı `Main.php`'nin içinde inline closure olarak
duruyordu; her yeni kural (token, doğrulama, ürün yeniden çekme) bu dosyayı
şişiriyordu. `OrderController` çıkarılınca `Main.php` 149 satırdan 80 satıra
indi ve routing ile iş mantığı ayrıştı.

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
  reddeder (409). Buton, istek sürerken disable ediliyor. Token'lar 30 dakika
  TTL ile tutulur; süresi geçenler temizlenir ve session başına en fazla 20
  aktif token saklanır (kullanılmadan terk edilen token'lar birikmesin diye).
- **Canlı tutar:** adet değiştikçe satırın "Tutar" hücresi `adet × birim fiyat`
  ile anında güncellenir; kullanıcı "Satın Al"a basmadan ne ödeyeceğini görür.
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
9. **`?lang` doğrulanmıyordu (LFI + kalıcı çökme)** — `$_GET['lang']` hiçbir
   kontrolden geçmeden `require_once ".../languages/{$lang}.php"` içine
   giriyordu. Bu hem bir *local file inclusion / path traversal* açığıydı,
   hem de geçersiz değer **require'dan önce session'a yazıldığı** için tek bir
   `?lang=zz` isteği session'ı zehirliyor ve sonraki tüm istekler (`/` dahil)
   fatal error veriyordu; kullanıcı cookie'sini silene kadar siteye
   giremiyordu. Hem GET hem session değeri artık whitelist'ten geçiyor.
10. **Template'lerde XSS riski** — oyun/ürün adları harici API'den geliyor ama
   escape edilmeden basılıyordu (Smarty 5 varsayılan olarak otomatik escape
   yapmaz). `setEscapeHtml(true)` ile tek yerden kapatıldı; bilinçli olarak
   ham bırakılan tek değer (`LANG_JSON`) `nofilter` ile işaretlendi.
11. **Varsayılan oyun seçeneği yanıltıcıydı** — "Tüm Oyunlar" yazıyordu ama
   seçilince hiçbir ürün listelenmiyordu. README'nin istediği "Oyun Seçiniz"
   ifadesiyle değiştirildi.
12. **Parse edilip hiç gösterilmeyen veriler** — `ProductService`
   `min_order`/`max_order`/`pre_order` alanlarını okuyordu ve dil dosyalarında
   `min_order`/`max_order` çevirileri hazırdı, ama hiçbiri ekranda
   gösterilmiyordu. Tabloya sütun ve "Ön Sipariş" rozeti olarak eklendi.
13. **`ProductService::getProducts()` içinde ölü kod** — `$urun->max_order !== ''`
   bir `SimpleXMLElement`'i string ile karşılaştırıyordu; bu karşılaştırma
   içerik ne olursa olsun her zaman `true` döner (tip farkı), yani "boş ise
   sınırsız" dalı hiçbir zaman çalışmıyordu. `ProductServiceTest` yazılırken
   ortaya çıktı; `(string)` cast'i eklenerek düzeltildi.
14. **Mobilde "Satın Al" butonu ekran dışında kalıyordu** — sütun sayısı
   artınca tablo 375px ekranda yatay kaydırmaya düşüyor ve sayfanın var oluş
   sebebi olan buton görünmez oluyordu. README hem "mevcut tablo yapısını
   koruyun" hem "responsive tasarımı bozmayın" dediği için tabloyu kart
   listesine *çevirmek* yerine, HTML tablo yapısı aynı bırakılıp 768px altında
   satırlar CSS ile karta dönüştürüldü (sütun başlıkları her hücrenin
   `data-label` değerinden `::before` ile basılıyor). Markup hâlâ tablo,
   yalnızca sunum değişiyor.
15. **Sipariş token'ları session'da birikiyordu** — token'lar `[$token => true]`
   olarak tutuluyor ve kullanılmazsa hiç temizlenmiyordu; sayfa her
   yenilendiğinde session biraz daha büyüyordu. Artık üretim zamanı saklanıp
   30 dakikalık TTL uygulanıyor, süresi geçenler temizleniyor ve aktif token
   sayısı 20 ile sınırlanıyor.

---

## Ek Değer Sağlayan Çalışmalar

- **Docker:** Dockerfile (PHP 8.2 + Apache, mod_rewrite/headers) ve
  docker-compose ile tek komutla çalışan ortam.
- **Güvenlik:** `.htaccess` güvenlik başlıkları; credential'lar yalnızca
  `.env`'de (kaynak kod ve commit geçmişinde yok); server-side doğrulama;
  `?lang` whitelist'i (LFI koruması) ve Smarty global HTML escape (XSS).
- **Marka tasarımı:** Renk paleti (`#EF1414` kırmızı, koyu yüzeyler, nötr
  griler) turkpin.com skalasından alınıp `:root` altında CSS değişkeni olarak
  tanımlandı ve Bootstrap'in kendi `--bs-*` değişkenlerine bağlandı; böylece
  buton/link/focus renkleri element bazında override edilmeden markaya uyuyor.
- **Responsive:** `md` altında sipariş limiti sütunları gizleniyor, 768px
  altında ise tablo satırları karta dönüşüyor (yukarıda madde 14). 375px'te
  yatay taşma yok, buton tam genişlikte ve görünür.
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
  çevrilebilir (sipariş gönderimi zaten AJAX). Yenileme sırasında select
  disable edilerek kullanıcıya geri bildirim veriliyor.
- `OrderController`'ın token/TTL mantığı `$_SESSION`'a doğrudan bağlı olduğu
  için unit test edilmiyor; session erişimi küçük bir arayüzün arkasına
  alınırsa test edilebilir hale gelir. Şu an curl ile manuel doğrulandı
  (geçerli sipariş 200, aynı token tekrar 409, token'sız istek 409).
- Statik analiz (PHPStan) veya kod formatlama (php-cs-fixer) aracı
  eklenmedi; README'nin artı-değer listesinde yer alıyor.

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
