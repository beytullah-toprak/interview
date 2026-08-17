<?php

require_once __DIR__ . '/Home.php';
require_once __DIR__ . '/OrderController.php';

class Main
{
    /** Desteklenen diller. $_GET['lang'] doğrudan dosya yoluna girdiği için whitelist şart. */
    private const SUPPORTED_LANGS = ['tr', 'en'];
    private const DEFAULT_LANG = 'tr';

    public $router;

    public function __construct()
    {
        global $lang, $smarty;

        // Dil tercihini session'dan oku, yoksa varsayılan.
        // Whitelist dışındaki her değer (session'dan da gelse) yok sayılır:
        // aksi halde geçersiz bir ?lang session'a yazılıp sonraki tüm
        // istekleri de kırardı.
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

        // Oyun/ürün adları harici API'den geliyor; template'te otomatik HTML
        // escape ederek XSS riskini tek yerde kapatıyoruz.
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
    }

    private function resolveLang(?string $candidate): string
    {
        return in_array($candidate, self::SUPPORTED_LANGS, true) ? $candidate : self::DEFAULT_LANG;
    }

    public function run()
    {
        global $smarty;

        // Ana sayfa - oyun/ürün listesini gösterir
        $this->router->get('/', function () {
            (new Home())->index();
        });

        // Sipariş uçları - işin kendisi OrderController'da (bkz. OrderController.php)
        $this->router->get('/order-token', function () {
            (new OrderController())->issueToken();
        });

        $this->router->post('/order', function () {
            (new OrderController())->create();
        });

        $this->router->run();
        $smarty->display('index.html');
    }
}