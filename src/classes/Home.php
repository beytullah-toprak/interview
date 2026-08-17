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
        global $smarty;

        $selectedGameId = $_GET['game_id'] ?? null;

        $gameResult = $this->gameService->getGames();

        if (!$gameResult['success']) {
            $smarty->assign('error', 'Oyun listesi alınamadı: ' . $gameResult['error_message']);
            $smarty->assign('games', []);
            $smarty->assign('products', []);
            $smarty->assign('template', 'home.html');
            return;
        }

        $products = [];
        if ($selectedGameId) {
            $productResult = $this->productService->getProducts($selectedGameId);
            if ($productResult['success']) {
                $products = $productResult['products'];
            } else {
                $smarty->assign('error', 'Ürünler alınamadı: ' . $productResult['error_message']);
            }
        }

        $smarty->assign('games', $gameResult['games']);
        $smarty->assign('game', $selectedGameId ? (int) $selectedGameId : 0);
        $smarty->assign('products', $products);
        $smarty->assign('template', 'home.html');
    }
}