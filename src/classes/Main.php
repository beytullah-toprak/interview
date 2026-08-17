<?php

require_once __DIR__ . '/Home.php';

class Main
{
    public $router;

    public function __construct()
    {
        global $lang, $smarty;

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
    }

    public function run()
    {
        global $smarty;

        $this->router->get('/', function () {
            $home = new Home();
            $home->index();
        });

        // Sipariş oluşturma - AJAX ile çağrılır, JSON döner
        $this->router->post('/order', function () {
            header('Content-Type: application/json');

            $gameId = $_POST['game_id'] ?? '';
            $productId = $_POST['product_id'] ?? '';
            $quantity = (int) ($_POST['quantity'] ?? 0);

            if (!$gameId || !$productId || $quantity < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Eksik veya hatalı bilgi.']);
                exit;
            }

            $client = \Turkpin\InterviewTest\Api\TurkpinApiClient::fromEnv();
            $orderService = new \Turkpin\InterviewTest\Services\OrderService($client);

            $result = $orderService->createOrder($gameId, $productId, $quantity);

            echo json_encode($result);
            exit;
        });

        $this->router->run();
        $smarty->display('index.html');
    }
}