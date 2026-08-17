<?php

use Turkpin\InterviewTest\Api\TurkpinApiClient;
use Turkpin\InterviewTest\Services\OrderService;
use Turkpin\InterviewTest\Services\ProductService;
use Turkpin\InterviewTest\Validators\OrderValidator;

/**
 * Sipariş uçlarını (/order-token, /order) yöneten controller.
 *
 * Home.php ile aynı desende: route tanımı Main.php'de kalır, işin kendisi
 * burada. Böylece Main.php yalnızca "hangi URL hangi controller'a gider"
 * sorusunu cevaplayan ince bir katman olarak kalıyor.
 */
class OrderController
{
    /** Kullanılmayan token'ların session'da ne kadar yaşayacağı (saniye). */
    private const TOKEN_TTL = 1800;

    /** Aynı anda tutulacak en fazla token sayısı. */
    private const MAX_ACTIVE_TOKENS = 20;

    private array $lang;

    public function __construct()
    {
        global $lang;

        $this->lang = $lang;
    }

    /**
     * Çift gönderim engelleme: sipariş formu her gönderildiğinde önce buradan
     * tek kullanımlık bir token alınır. Bu token olmadan veya aynı token
     * ikinci kez kullanılarak /order isteği atılamaz.
     */
    public function issueToken(): void
    {
        $this->pruneTokens();

        $token = bin2hex(random_bytes(16));
        $_SESSION['order_tokens'][$token] = time();

        $this->json(['token' => $token]);
    }

    /**
     * Sipariş oluşturma - AJAX ile çağrılır, JSON döner.
     * Sırasıyla: temel alan kontrolü -> token kontrolü -> ürünü tekrar
     * API'den çekip min/max/stok doğrulaması -> gerçek siparişi gönderme.
     */
    public function create(): void
    {
        $gameId = $_POST['game_id'] ?? '';
        $productId = $_POST['product_id'] ?? '';
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $token = $_POST['order_token'] ?? '';

        if (!$gameId || !$productId || $quantity < 1) {
            $this->json(['success' => false, 'message' => $this->lang['missing_fields']], 400);
        }

        if (!$this->consumeToken($token)) {
            $this->json(['success' => false, 'message' => $this->lang['duplicate_order']], 409);
        }

        $client = TurkpinApiClient::fromEnv();

        // Kullanıcının gönderdiği min/max bilgisine güvenmiyoruz (tarayıcıdan
        // manipüle edilebilir). Ürünü tekrar API'den çekip gerçek değerlerle
        // doğruluyoruz.
        $productResult = (new ProductService($client))->getProducts($gameId);

        $product = null;
        foreach ($productResult['products'] as $p) {
            if ($p['id'] == $productId) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            $this->json(['success' => false, 'message' => $this->lang['product_not_found']], 404);
        }

        $validationError = (new OrderValidator($this->lang))->validate($product, $quantity);

        if ($validationError) {
            $this->json(['success' => false, 'message' => $validationError], 422);
        }

        // Tüm kontroller geçti, gerçek siparişi API'ye gönder
        $result = (new OrderService($client))->createOrder($gameId, $productId, $quantity);

        $this->json($result);
    }

    /**
     * Token'ı doğrular ve tek kullanımlık olduğu için hemen tüketir.
     */
    private function consumeToken(string $token): bool
    {
        if (!$token || !isset($_SESSION['order_tokens'][$token])) {
            return false;
        }

        $issuedAt = $_SESSION['order_tokens'][$token];
        unset($_SESSION['order_tokens'][$token]);

        return (time() - $issuedAt) <= self::TOKEN_TTL;
    }

    /**
     * Kullanılmadan terk edilen token'lar session'da birikmesin: süresi
     * geçenleri at, kalan sayı da sınırı aşıyorsa en eskileri düşür.
     */
    private function pruneTokens(): void
    {
        $tokens = $_SESSION['order_tokens'] ?? [];
        $now = time();

        foreach ($tokens as $token => $issuedAt) {
            if (($now - $issuedAt) > self::TOKEN_TTL) {
                unset($tokens[$token]);
            }
        }

        if (count($tokens) >= self::MAX_ACTIVE_TOKENS) {
            asort($tokens);
            $tokens = array_slice($tokens, -(self::MAX_ACTIVE_TOKENS - 1), null, true);
        }

        $_SESSION['order_tokens'] = $tokens;
    }

    /**
     * JSON cevabı yazıp isteği sonlandırır.
     */
    private function json(array $payload, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($payload);
        exit;
    }
}
