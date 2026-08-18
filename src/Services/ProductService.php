<?php

namespace Turkpin\InterviewTest\Services;

use Turkpin\InterviewTest\Api\TurkpinApiClient;

/**
 * Ürün listesi ile ilgili iş mantığını yönetir.
 * Turkpin API'nin "epinUrunleri" komutunu sarmalar.
 */
class ProductService
{
    public function __construct(private TurkpinApiClient $client)
    {
    }

    /**
     * Belirtilen oyuna ait, sipariş verilebilecek ürünleri getirir.
     *
     * @param string $gameId Oyun kodu (GameService::getGames()'ten gelen id)
     *
     * @return array{
     *     success: bool,
     *     error_code: string,
     *     error_message: string,
     *     products: array<int, array{
     *         id: string, name: string, stock: int,
     *         min_order: int, max_order: int|null,
     *         min_barem: float|null, max_barem: float|null, barem_step: float|null,
     *         price: float, tax_type: string, pre_order: bool
     *     }>
     * }
     */
    public function getProducts(string $gameId): array
    {
        $result = $this->client->request('epinUrunleri', ['oyunKodu' => $gameId]);

        if (!$result['success']) {
            return [
                'success' => false,
                'error_code' => $result['error_code'],
                'error_message' => $result['error_message'],
                'products' => [],
            ];
        }

        $products = [];
        foreach ($result['data']->epinUrunListesi->urun ?? [] as $urun) {
            $products[] = [
                'id' => (string) $urun->id,
                'name' => (string) $urun->name,
                'stock' => (int) $urun->stock,
                'min_order' => (int) $urun->min_order,
                // max_order boş string gelebiliyor (API dokümanında görüldü): 0/limitsiz anlamına gelir
                // (SimpleXMLElement hiçbir zaman === '' olmadığı için önce string'e çevrilmeli)
                'max_order' => (string) $urun->max_order !== '' ? (int) $urun->max_order : null,
                // Baremli (kademeli) ürünlerde bu üçü birden dolu gelir; sipariş
                // adet yerine min_barem-max_barem aralığında barem_step adımıyla verilir.
                'min_barem' => (string) $urun->min_barem !== '' ? (float) $urun->min_barem : null,
                'max_barem' => (string) $urun->max_barem !== '' ? (float) $urun->max_barem : null,
                'barem_step' => (string) $urun->barem_step !== '' ? (float) $urun->barem_step : null,
                'price' => (float) $urun->price,
                'tax_type' => (string) $urun->tax_type,
                'pre_order' => (string) $urun->pre_order === 'true',
            ];
        }

        return [
            'success' => true,
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'products' => $products,
        ];
    }
}