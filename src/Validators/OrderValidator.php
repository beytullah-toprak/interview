<?php

namespace Turkpin\InterviewTest\Validators;

/**
 * Sipariş oluşturulmadan önce ürünün min/max/stok/barem kurallarına uyup
 * uymadığını kontrol eder. API'ye istek atmaz, sadece elimizdeki ürün
 * verisini (ProductService'ten gelen) denetler.
 */
class OrderValidator
{
    /**
     * @param array $lang Aktif dilin çeviri dizisi (src/languages/*.php)
     */
    public function __construct(private array $lang)
    {
    }

    /**
     * @param array      $product  ProductService::getProducts()'tan gelen tek ürün
     * @param int        $quantity Kullanıcının girdiği adet (baremli ürünlerde kullanılmaz)
     * @param float|null $barem    Baremli ürünlerde kullanıcının girdiği barem tutarı
     * @return string|null Hata mesajı varsa döner, her şey uygunsa null döner
     */
    public function validate(array $product, int $quantity, ?float $barem = null): ?string
    {
        if ($this->isTiered($product)) {
            return $this->validateBarem($product, $barem);
        }

        if ($quantity < $product['min_order']) {
            return str_replace(':min', (string) $product['min_order'], $this->lang['min_order_error']);
        }

        if ($product['max_order'] !== null && $product['max_order'] > 0 && $quantity > $product['max_order']) {
            return str_replace(':max', (string) $product['max_order'], $this->lang['max_order_error']);
        }

        // Ön sipariş ürünleri stoktan bağımsız işleme alınır (Turkpin API
        // dokümantasyonu ve canlı test ile doğrulandı: stok=0 olan bir
        // pre_order ürünü "Pending" durumuyla başarıyla sipariş edilebiliyor).
        if (!$product['pre_order'] && $product['stock'] <= 0) {
            return $this->lang['out_of_stock_error'];
        }

        return null;
    }

    private function isTiered(array $product): bool
    {
        return ($product['min_barem'] ?? null) !== null
            && ($product['max_barem'] ?? null) !== null
            && ($product['barem_step'] ?? null) !== null;
    }

    private function validateBarem(array $product, ?float $barem): ?string
    {
        if ($barem === null) {
            return $this->lang['barem_required_error'];
        }

        if ($barem < $product['min_barem'] || $barem > $product['max_barem']) {
            return str_replace(
                [':min', ':max'],
                [(string) $product['min_barem'], (string) $product['max_barem']],
                $this->lang['barem_range_error']
            );
        }

        $steps = ($barem - $product['min_barem']) / $product['barem_step'];

        // Ondalıklı bölme sonucu tam sayıya yakınlık kontrolü (float hassasiyeti için)
        if (abs($steps - round($steps)) > 0.0001) {
            return str_replace(':step', (string) $product['barem_step'], $this->lang['barem_step_error']);
        }

        return null;
    }
}
