# Teknik Kararlar

Mevcut yapıyı (Smarty + Bramus Router + PSR-4) bozmadan üzerine katmanlı bir
yapı kurdum: `TurkpinApiClient` API ile ham HTTP/XML konuşmasını yapıyor,
`Services/` altındaki sınıflar (`GameService`, `ProductService`,
`OrderService`) iş kurallarını taşıyor, `OrderValidator` sipariş öncesi
min/max/stok kontrolünü yapıyor. `Home.php` ve `Order.php` da bunları
çağırıp Smarty'ye veri basan ince controller'lar. `Main.php`'de sipariş
mantığının tamamı inline duruyordu, onu `Order.php`'ye çıkardım — dosya 149
satırdan 80'e indi ve artık sadece route tanımlıyor.

## Ne yaptım

Oyun listesi ve ürünler gerçek API'den geliyor, oyun seçilmeden ürün
listesi görünmüyor. Sipariş verirken çift gönderimi engellemek için önce
`/order-token`'dan tek kullanımlık bir token alıp onunla `/order`'a
gidiyorum; aynı token ikinci kez kullanılamıyor, token'lar 30 dakika sonra
kendiliğinden düşüyor. Adet alanını hem client'ta hem sunucuda
doğruluyorum — sunucu tarafında kullanıcının gönderdiği değerlere
güvenmeyip ürünü API'den tekrar çekiyorum, stoğu/limitleri oradan kontrol
ediyorum. Sonuç SweetAlert2 ile gösteriliyor (native `alert()` yerine,
çünkü stilize edilemiyor ve başarı/hata ayrımı yapamıyor), her şey TR/EN.

## Karşıma çıkan asıl sorun

Sipariş sistemi baştan çalışmıyordu — `app.js` `/order-token`'ı hiç
çağırmadan direkt `/order`'a POST atıyordu, sunucu da token yok diye her
isteği 409 ile reddediyordu. Onu düzeltmeden hiçbir sipariş geçmiyordu.

Bunun dışında bulduklarım:
- `?lang` parametresi hiç doğrulanmadan dosya yoluna gidiyordu — hem path
  traversal riski hem de geçersiz bir değer session'a yazılınca kullanıcı
  cookie silene kadar siteye giremiyordu. Whitelist ekledim.
- Oyun/ürün adları API'den geliyor ama escape edilmeden basılıyordu (XSS
  riski). Smarty'de global escape açtım.
- `en.php`'de sipariş sonucu mesajları için key'ler vardı ama `tr.php`'ye
  hiç eklenmemişti, JS de zaten bu key'leri kullanmıyordu — tüm mesajlar
  sabit Türkçeydi. İkisini eşitleyip JS'e bağladım.
- `ProductService`'te `max_order !== ''` bir XML nesnesini string'le
  kıyaslıyordu, bu her zaman true dönüyor — yani "boşsa sınırsız" kuralı
  hiç çalışmıyormuş. Test yazarken fark ettim.
- `Main.php`'de `require 'home.php'` ama dosya `Home.php` — macOS'ta sorun
  yok, case-sensitive Linux'ta patlardı.
- Mobilde tablo 8 sütunla yatay taşıyor, "Satın Al" butonu ekran dışında
  kalıyordu. Tabloyu bozmadan (README öyle istiyor) 768px altında satırları
  CSS ile karta çevirdim.
- `aura/router` composer'da duruyordu ama proje zaten Bramus kullanıyor,
  hiç çağrılmıyordu — kaldırdım.
- Sipariş token'ları session'da hiç temizlenmeden birikiyordu, TTL ekledim.
- Route eşleşmeyince sayfa boş dönüyordu, gerçek 404 sayfası ekledim.

## Ek olarak

Docker ile tek komutta ayağa kalkıyor, `.env` dışında credential hiçbir
yerde yok. `AssetHelper` dosya değişim zamanına göre `?v=...` ekliyor ki
CSS/JS güncelleyince kullanıcı elle hard-refresh yapmasın. API hataları
`storage/logs/api.log`'a yazılıyor. `OrderValidator`, XML parse mantığı,
servisler ve para formatlama için 21 PHPUnit testi var
(`vendor/bin/phpunit`).

## Eksik kalanlar

Ürün listesi hâlâ sayfa yenilemesiyle geliyor, tam AJAX'a çevrilebilir.
Token/TTL mantığı session'a bağlı olduğu için unit test edilmiyor, curl ile
elle doğruladım. PHPStan/php-cs-fixer gibi bir statik analiz aracı
eklemedim.
