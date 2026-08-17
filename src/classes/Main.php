<?php

require_once __DIR__ . '/Home.php';
require_once __DIR__ . '/Order.php';

class Main
{
    /** Desteklenen diller. $_GET['lang'] doğrudan dosya yoluna girdiği için whitelist şart. */
    private const SUPPORTED_LANGS = ['tr', 'en'];
    private const DEFAULT_LANG = 'tr';

    public $router;

    public function __construct()
    {
        global $lang, $smarty;

        // Dil tercihi session'dan okunur, yoksa varsayılan dil kullanılır
        $lang = $this->resolveLang($_SESSION['lang'] ?? null);

        if (isset($_GET['lang'])) {
            $lang = $this->resolveLang($_GET['lang']);
            $_SESSION['lang'] = $lang;
        }

        require_once __DIR__ . "/../languages/{$lang}.php";

        $smarty = new Smarty\Smarty();
        $this->router = new \Bramus\Router\Router();

        $smarty->setTemplateDir('src/templates');
        $smarty->setCompileDir('/tmp');

        // Oyun/ürün adları harici API'den geliyor; template'te otomatik HTML escape edilerek XSS riski tek yerde kapatılır
        $smarty->setEscapeHtml(true);

        $smarty->assign('LANG', $lang);

        // JS tarafında SweetAlert2 mesajlarını localize etmek için (window.LANG olarak kullanılır)
        $smarty->assign('LANG_JSON', json_encode($lang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $smarty->assign('langs', ['tr' => 'Türkçe', 'en' => 'English']);

        // Template'lerde {$base_url} olarak kullanılır (asset path'leri için)
        $smarty->assign('base_url', rtrim($_ENV['APP_URL'] ?? '', '/'));

        // Template'lerde {asset path='...'} olarak kullanılır (cache-busting)
        $smarty->registerPlugin('function', 'asset', function ($params) {
            return \Turkpin\InterviewTest\Helpers\AssetHelper::url($params['path']);
        });

        // Template'lerde {$product.price|money} olarak kullanılır
        $smarty->registerPlugin('modifier', 'money', function ($amount) use ($lang) {
            return \Turkpin\InterviewTest\Helpers\MoneyHelper::format((float) $amount, $lang['lang']);
        });
    }

    private function resolveLang(?string $candidate): string
    {
        return in_array($candidate, self::SUPPORTED_LANGS, true) ? $candidate : self::DEFAULT_LANG;
    }

    public function run()
    {
        global $smarty;

        // Ana sayfa
        $this->router->get('/', function () {
            (new Home())->index();
        });

        // Sipariş token oluşturma
        $this->router->get('/order-token', function () {
            (new Order())->issueToken();
        });

        // Sipariş oluşturma
        $this->router->post('/order', function () {
            (new Order())->create();
        });

        // 404 sayfası
        $this->router->set404(function () use ($smarty) {
            http_response_code(404);
            $smarty->assign('template', '404.html');
        });

        $this->router->run();
        $smarty->display('index.html');
    }
}