<?php

namespace Turkpin\InterviewTest\Services;

use Turkpin\InterviewTest\Api\TurkpinApiClient;

/**
 * Sipariş oluşturma iş mantığını yönetir.
 * Turkpin API'nin "epinSiparisYarat" komutunu sarmalar.
 */
class OrderService
{
    public function __construct(private TurkpinApiClient $client)
    {
    }

    /**
     * Yeni bir Epin siparişi oluşturur.
     *
     * @param string $gameId    Oyun kodu
     * @param string $productId Ürün kodu
     * @param int    $quantity  Sipariş adedi (min_order/max_order kontrolü Controller/Validator'da yapılmalı)
     *
     * @return array{
     *     success: bool,
     *     error_code: string,
     *     error_message: string,
     *     order: array{order_no: string, total: float, epins: array}|null
     * }
     */
    public function createOrder(string $gameId, string $productId, int $quantity): array
    {
        $result = $this->client->request('epinSiparisYarat', [
            'oyunKodu' => $gameId,
            'urunKodu' => $productId,
            'adet' => $quantity,
        ]);

        if (!$result['success']) {
            return [
                'success' => false,
                'error_code' => $result['error_code'],
                'error_message' => $result['error_message'],
                'order' => null,
            ];
        }

        $data = $result['data'];
        $epins = [];
        foreach ($data->epin_list->epin ?? [] as $epin) {
            $epins[] = [
                'code' => (string) $epin->code,
                'desc' => (string) $epin->desc,
                'id' => (string) $epin->id,
            ];
        }

        return [
            'success' => true,
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'order' => [
                'order_no' => (string) $data->siparisNo,
                'total' => (float) $data->siparisTutari,
                'epins' => $epins,
            ],
        ];
    }
}