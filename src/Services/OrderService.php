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
     * @param string     $gameId    Oyun kodu
     * @param string     $productId Ürün kodu
     * @param int        $quantity  Sipariş adedi (baremli üründe yok sayılır, min/max/stok
     *                              kontrolü Controller/Validator'da yapılmalı)
     * @param bool       $preOrder  Ön sipariş ürünü ise true gönderilmeli (API dokümantasyonu)
     * @param float|null $barem     Baremli üründe adet yerine gönderilen tutar
     *
     * @return array{
     *     success: bool,
     *     error_code: string,
     *     error_message: string,
     *     order: array{order_no: string, total: float, pending: bool, epins: array}|null
     * }
     */
    public function createOrder(
        string $gameId,
        string $productId,
        int $quantity,
        bool $preOrder = false,
        ?float $barem = null
    ): array {
        $params = [
            'oyunKodu' => $gameId,
            'urunKodu' => $productId,
            // Baremli üründe API "adet"i yine zorunlu tutuyor ama gerçek tutarı
            // "barem" alanından okuyor; dokümantasyon ve canlı testle doğrulandı.
            'adet' => $barem !== null ? 1 : $quantity,
        ];

        if ($preOrder) {
            $params['pre_order'] = 'true';
        }

        if ($barem !== null) {
            $params['barem'] = $barem;
        }

        $result = $this->client->request('epinSiparisYarat', $params);

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
                // Ön sipariş/barem ürünlerde epin kodları hemen gelmiyor, sipariş
                // "Pending" durumunda dönüyor (canlı testle doğrulandı).
                'pending' => (string) ($data->siparisSonuc ?? '') === 'Pending',
                'epins' => $epins,
            ],
        ];
    }
}
