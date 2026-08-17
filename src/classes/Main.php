<?php

require_once __DIR__ . '/Home.php';

class Main
{
    public $router;

    public function __construct()
    {
        global $lang, $smarty;

        // Dil tercihini session'dan oku, yoksa varsayılan 'tr'
        $lang = $_SESSION['lang'] ?? 'tr';

        if (isset($_GET['lang'])) {
            $lang = $_GET['lang'];
            $_SESSION['lang'] = $lang;
        }

        require_once __DIR__ . "/../languages/{$lang}.php";

        $smarty = new Smarty\Smarty();
        $this->router = new \Bramus\Router\Router();

        $smarty->setTemplateDir('src/templates');
        $smarty->setCompileDir('/tmp');

        $smarty->assign('LANG', $lang);
        $smarty->assign('langs', ['tr' => 'Türkçe', 'en' => 'English']);

        // Template'lerde {$base_url} olarak kullanılır (asset path'leri için)
        $smarty->assign('base_url', rtrim($_ENV['APP_URL'] ?? '', '/'));

        // Template'lerde {asset path='...'} olarak kullanılır (cache-busting)
        $smarty->registerPlugin('function', 'asset', function ($params) {
            return \Turkpin\InterviewTest\Helpers\AssetHelper::url($params['path']);
        });
    }

    public function run()
    {
        global $smarty;

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
        $this->router->post('/order', function () {
            header('Content-Type: application/json');

            $gameId = $_POST['game_id'] ?? '';
            $productId = $_POST['product_id'] ?? '';
            $quantity = (int) ($_POST['quantity'] ?? 0);
            $token = $_POST['order_token'] ?? '';

            // Temel alan kontrolü
            if (!$gameId || !$productId || $quantity < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Eksik veya hatalı bilgi.']);
                exit;
            }

            // Çift gönderim engelleme: token geçersizse veya daha önce kullanıldıysa reddet
            if (!$token || !isset($_SESSION['order_tokens'][$token])) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Bu sipariş zaten gönderildi veya oturum süresi doldu.']);
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
                echo json_encode(['success' => false, 'message' => 'Ürün bulunamadı.']);
                exit;
            }

            $validator = new \Turkpin\InterviewTest\Validators\OrderValidator();
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