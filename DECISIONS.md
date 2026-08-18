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
- Dil dosyalarında (`tr.php`/`en.php`) key'ler birebir eşleşmiyordu —
mesela `en.php`'de sipariş sonucu için (`order_success`, `order_no` gibi)
key'ler vardı ama `tr.php`'de yoktu. Böyle bir key kullanılan yerde çağrılınca
PHP "Undefined array key" hatası/uyarısı basıyordu, yani dil değiştirince
bazı sayfalar hataya düşüyordu. İki dosyayı satır satır karşılaştırıp
eksik olan key'leri tamamladım, ayrıca JS tarafı (SweetAlert2 mesajları)
bu key'leri hiç kullanmıyordu, onları da `window.LANG` üzerinden JS'e
bağladım ki uyarı/hata mesajları da seçilen dile göre değişsin.
- `ProductService`'te `max_order !== ''` bir XML nesnesini string'le
kıyaslıyordu, bu her zaman true dönüyor — yani "boşsa sınırsız" kuralı
hiç çalışmıyormuş. Test yazarken fark ettim.
- Ana sayfa rotası yanlış dosya yoluna bakıyordu: `Main.php` içinde
`require 'home.php'` yazıyordu ama gerçek dosya adı `Home.php`. macOS
büyük/küçük harf duyarsız çalıştığı için burada bir sorun görünmüyordu,
ama sunucu genelde Linux (case-sensitive) olacağı için deploy edilince
"dosya bulunamadı" fatal error verirdi. `__DIR__ . '/Home.php'` ile doğru
yola sabitledim.
- Mobilde tablo 8 sütunla yatay taşıyor, "Satın Al" butonu ekran dışında
kalıyordu. Tabloyu bozmadan (README öyle istiyor) 768px altında satırları
CSS ile karta çevirdim.
- `composer.json`'da gereksiz bir kütüphane vardı: `aura/router`. Proje
zaten routing için `bramus/router`'ı kullanıyor, kodun hiçbir yerinde
`Aura\Router`'a tek bir referans bile yoktu — muhtemelen ilk kurulumda
denenip sonra kullanılmayan bir bağımlılık olarak kalmış. `composer.json`'dan
kaldırıp `composer update` ile `vendor/`'dan da temizledim; gereksiz paket
kurulum süresini uzatıyordu.
- Sipariş token'ları session'da hiç temizlenmeden birikiyordu, TTL ekledim.
- Route eşleşmeyince sayfa boş dönüyordu, gerçek 404 sayfası ekledim.



## Ek olarak

Docker ile tek komutta ayağa kalkıyor, `.env` dışında credential hiçbir  
yerde yok. `AssetHelper` dosya değişim zamanına göre `?v=...` ekliyor ki  
CSS/JS güncelleyince kullanıcı elle hard-refresh yapmasın. API hataları  
`storage/logs/api.log`'a yazılıyor. `OrderValidator`, XML parse mantığı,  
servisler ve para formatlama için 21 PHPUnit testi var  
(`vendor/bin/phpunit`).