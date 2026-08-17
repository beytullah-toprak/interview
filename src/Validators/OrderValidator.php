<?php

namespace Turkpin\InterviewTest\Validators;

/**
 * Sipariş oluşturulmadan önce ürünün min/max/stok kurallarına uyup uymadığını kontrol eder.
 * API'ye istek atmaz, sadece elimizdeki ürün verisini (ProductService'ten gelen) denetler.
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
     * @param array $product  ProductService::getProducts()'tan gelen tek ürün
     * @param int   $quantity Kullanıcının girdiği adet
     * @return string|null Hata mesajı varsa döner, her şey uygunsa null döner
     */
    public function validate(array $product, int $quantity): ?string
    {
        if ($quantity < $product['min_order']) {
            return str_replace(':min', (string) $product['min_order'], $this->lang['min_order_error']);
        }

        if ($product['max_order'] !== null && $product['max_order'] > 0 && $quantity > $product['max_order']) {
            return str_replace(':max', (string) $product['max_order'], $this->lang['max_order_error']);
        }

        if ($product['stock'] <= 0) {
            return $this->lang['out_of_stock_error'];
        }

        return null;
    }
}