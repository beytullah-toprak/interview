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
ediyorum. Sonuç SweetAlert2 ile gösteriliyor (native `alert()` yerine, çünkü stilize edilemiyor ve başarı/hata ayrımı yapamıyor), her şey TR/EN multi dil olarak dönüş sağlanmaktadır kullanıcıya.

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
- `composer.json`'da gereksiz bir kütüphane vardı: `aura/router`. Proje
zaten routing için `bramus/router`'ı kullanıyor, kodun hiçbir yerinde
`Aura\Router`'a tek bir referans bile yoktu — muhtemelen ilk kurulumda
denenip sonra kullanılmayan bir bağımlılık olarak kalmış. `composer.json`'dan
kaldırıp `composer update` ile `vendor/`'dan da temizledim; gereksiz paket
kurulum süresini uzatıyordu.
- Sipariş token'ları session'da hiç temizlenmeden birikiyordu, TTL ekledim.
- Route eşleşmeyince sayfa boş dönüyordu, gerçek 404 sayfası ekledim.



## Sorunlardan yola çıkıp eklediğim özellikler

Yukarıdaki bug'ların bir kısmı düzeltilirken, arkasında duran asıl soruna
bakıp kalıcı bir çözüm olarak küçük yardımcı sınıflar/özellikler ekledim:

- `AssetHelper` **(cache-busting):** CSS/JS dosyasını güncelleyip deploy
ettiğimde kullanıcının tarayıcısı hâlâ eski dosyayı cache'ten okuyabilir,
değişiklik görünmez. `AssetHelper::url()` dosyanın son değişim zamanını
(`filemtime`) alıp linke `?v=...` diye ekliyor; dosya değişince bu sayı da
değişiyor, tarayıcı "farklı bir URL" deyip yeniden indiriyor. Elle versiyon
numarası takip etmeye gerek kalmadı.
- `MoneyHelper` **(fiyat formatlama):** API'den gelen fiyatlar ham float
olarak (`0.001` gibi) basılıyordu, hem okunması zor hem 2 basamağa
yuvarlansa "0.00" gösterip yanıltıcı olurdu. `MoneyHelper` tutarı, kaç
basamak gerektiğine bakıp (2-4 arası) TR'de `1.234,56 ₺`, EN'de
`1,234.56 ₺` formatında basıyor. `intl` eklentisini bilerek kullanmadım
çünkü Docker imajında yüklü değil, ek kurulum gerektirmesin diye elle
(`number_format`) yazdım.
- `Logger` **(API hata kaydı):** Dockerfile zaten `storage/logs` klasörünü
oluşturuyordu ama hiçbir şey oraya yazmıyordu — yani API'den bir hata
dönse bile hiçbir iz kalmıyordu. `TurkpinApiClient` içindeki ağ hatalarını
ve API'nin döndürdüğü hata kodlarını `storage/logs/api.log`'a yazacak
şekilde bağladım.
- `APP_DEBUG`**:** `.env.example`'da bu değişken tanımladım. `index.php`'de bu değere
göre `display_errors`/`error_reporting`'i açıp kapatacak şekilde bağladım
— geliştirirken hatayı görüyorum, canlıda kullanıcıya stack trace
sızmıyor.
- **Marka renkleri ve rozet/buton tasarımı:** Sayfa saf Bootstrap'ti,
Turkpin'le hiçbir görsel bağı yoktu. turkpin.com'daki kırmızı tonu
(`#EF1414`) CSS değişkeni olarak tanımlayıp Bootstrap'in kendi
değişkenlerine bağladım. Bootstrap'in hazır rozetleri de (`text-bg-info`
açık mavi zemine siyah yazı, `text-bg-danger` markanın kırmızısıyla
çakışıyordu) kontrastı zayıf ve markaya uymuyordu; kendi yumuşak zeminli
rozetlerimi yazdım. Dolu kırmızı "Satın Al" butonu da tabloda altı kez
tekrar edince "sil/uyarı" gibi okunuyordu, koyu zemine çevirip kırmızıyı
sadece hover'da vurgu olarak bıraktım.
- **PHPUnit testleri (21 test):** `OrderValidator`'ın min/max/stok
kurallarını, `TurkpinApiClient`'ın XML parse mantığını (API'nin iki farklı
hata formatını da), servislerin API cevabını array'e çevirme mantığını ve
`MoneyHelper`'ı test ediyor. `ProductService`'teki ölü kodu da bu testi
yazarken buldum — yani testler sadece "güvence" değil, fiilen bug da
yakaladı. Çalıştırmak için: `vendor/bin/phpunit`.
- **Docker:** `.env`'i doldurup `docker compose up -d --build` demek  
yeterli olsun diye Dockerfile + docker-compose ekledim; credential  
kaynak kodda değil sadece `.env`'de duruyor, o dosya da `.gitignore`'da.

