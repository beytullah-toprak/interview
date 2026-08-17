<?php

require_once __DIR__ . '/Home.php';

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
        global $smarty, $lang;

        // Ana sayfa - oyun/ürün listesini gösterir
        $this->router->get('/', function () {
            $home = new Home();
            $home->index();
        });

        // Çift gönderim engelleme: sipariş formu her açıldığında/tekrar
        // denendiğinde önce tek kullanımlık bir token alınır. Bu token
        // olmadan veya aynı token ikinci kez kullanılarak /order isteği
        // atılamaz.
        $this->router->get('/order-token', function () {
            header('Content-Type: application/json');
            $token = bin2hex(random_bytes(16));
            $_SESSION['order_tokens'][$token] = true;
            echo json_encode(['token' => $token]);
            exit;
        });

        // Sipariş oluşturma - AJAX ile çağrılır, JSON döner.
        // Sırasıyla: token kontrolü -> ürünü tekrar API'den çekip
        // min/max/stok doğrulaması -> gerçek siparişi API'ye gönderme.
        $this->router->post('/order', function () use ($lang) {
            header('Content-Type: application/json');

            $gameId = $_POST['game_id'] ?? '';
            $productId = $_POST['product_id'] ?? '';
            $quantity = (int) ($_POST['quantity'] ?? 0);
            $token = $_POST['order_token'] ?? '';

            // Temel alan kontrolü
            if (!$gameId || !$productId || $quantity < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $lang['missing_fields']]);
                exit;
            }

            // Çift gönderim engelleme: token geçersizse veya daha önce kullanıldıysa reddet
            if (!$token || !isset($_SESSION['order_tokens'][$token])) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $lang['duplicate_order']]);
                exit;
            }
            unset($_SESSION['order_tokens'][$token]);

            $client = \Turkpin\InterviewTest\Api\TurkpinApiClient::fromEnv();

            // Kullanıcının gönderdiği min/max bilgisine güvenmiyoruz (tarayıcıdan
            // manipüle edilebilir). Ürünü tekrar API'den çekip gerçek değerlerle
            // doğruluyoruz.
            $productService = new \Turkpin\InterviewTest\Services\ProductService($client);
            $productResult = $productService->getProducts($gameId);

            $product = null;
            foreach ($productResult['products'] as $p) {
                if ($p['id'] == $productId) {
                    $product = $p;
                    break;
                }
            }

            if (!$product) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => $lang['product_not_found']]);
                exit;
            }

            $validator = new \Turkpin\InterviewTest\Validators\OrderValidator($lang);
            $validationError = $validator->validate($product, $quantity);

            if ($validationError) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => $validationError]);
                exit;
            }

            // Tüm kontroller geçti, gerçek siparişi API'ye gönder
            $orderService = new \Turkpin\InterviewTest\Services\OrderService($client);
            $result = $orderService->createOrder($gameId, $productId, $quantity);

            echo json_encode($result);
            exit;
        });

        $this->router->run();
        $smarty->display('index.html');
    }
}