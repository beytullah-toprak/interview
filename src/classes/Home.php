<?php

use Turkpin\InterviewTest\Api\TurkpinApiClient;
use Turkpin\InterviewTest\Services\GameService;
use Turkpin\InterviewTest\Services\ProductService;

class Home
{
    private GameService $gameService;
    private ProductService $productService;

    public function __construct()
    {
        $client = TurkpinApiClient::fromEnv();

        $this->gameService = new GameService($client);
        $this->productService = new ProductService($client);
    }

    public function index()
    {
        global $smarty, $lang;

        $selectedGameId = $_GET['game_id'] ?? null;
        $smarty->assign('game', $selectedGameId ? (int) $selectedGameId : 0);
        $smarty->assign('template', 'home.html');

        $gameResult = $this->gameService->getGames();

        if (!$gameResult['success']) {
            $smarty->assign('error', $lang['games_fetch_error'] . $gameResult['error_message']);
            $smarty->assign('games', []);
            $smarty->assign('products', []);
            return;
        }

        $products = [];
        if ($selectedGameId) {
            $productResult = $this->productService->getProducts($selectedGameId);
            if ($productResult['success']) {
                $products = $productResult['products'];
            } else {
                $smarty->assign('error', $lang['products_fetch_error'] . $productResult['error_message']);
            }
        }

        $smarty->assign('games', $gameResult['games']);
        $smarty->assign('products', $products);
    }
}