<?php

// `php -S` ile yerel geliştirme sunucusu (Apache/.htaccess yok): statik dosyalar
// (assets/*) için PHP'nin kendi dosya sunumuna düş. Apache/Docker ortamında bu
// bloğun hiçbir etkisi yok, .htaccess zaten aynı ayrımı yapıyor.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requestedFile !== __DIR__ . '/' && is_file($requestedFile)) {
        return false;
    }
}

session_start();
require_once 'vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

// APP_DEBUG=false (varsayılan) iken hata detayları kullanıcıya gösterilmez.
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : 0);

require_once 'src/classes/Main.php';

$main = new Main();
$main->run();
