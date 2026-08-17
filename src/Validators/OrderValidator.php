<?php

namespace Turkpin\InterviewTest\Validators;

/**
 * Sipariş oluşturulmadan önce ürünün min/max/stok kurallarına uyup uymadığını kontrol eder. 
 * API'ye istek atmaz, sadece elimizdeki ürün verisini (ProductService'ten gelen) denetler.
 */
class OrderValidator
{
    /**
     * @param array $product  ProductService::getProducts()'tan gelen tek ürün
     * @param int   $quantity Kullanıcının girdiği adet
     * @return string|null Hata mesajı varsa döner, her şey uygunsa null döner
     */
    public function validate(array $product, int $quantity): ?string
    {
        if ($quantity < $product['min_order']) {
            return "Sipariş adedi en az {$product['min_order']} olmalı.";
        }

        if ($product['max_order'] !== null && $product['max_order'] > 0 && $quantity > $product['max_order']) {
            return "Sipariş adedi en fazla {$product['max_order']} olmalı.";
        }

        if ($product['stock'] <= 0) {
            return "Ürün stokta yok.";
        }

        return null;
    }
}