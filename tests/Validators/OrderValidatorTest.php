<?php

namespace Tests\Validators;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Validators\OrderValidator;

class OrderValidatorTest extends TestCase
{
    private function makeLang(): array
    {
        return [
            'min_order_error' => 'Sipariş adedi en az :min olmalı.',
            'max_order_error' => 'Sipariş adedi en fazla :max olmalı.',
            'out_of_stock_error' => 'Ürün stokta yok.',
            'barem_required_error' => 'Lütfen bir tutar girin.',
            'barem_range_error' => 'Tutar :min ile :max arasında olmalı.',
            'barem_step_error' => 'Tutar :step adımlarıyla artmalı.',
        ];
    }

    private function makeProduct(array $overrides = []): array
    {
        return array_merge([
            'id' => '1',
            'name' => 'Test Ürün',
            'stock' => 10,
            'min_order' => 2,
            'max_order' => 5,
            'min_barem' => null,
            'max_barem' => null,
            'barem_step' => null,
            'price' => 9.99,
            'tax_type' => 'included',
            'pre_order' => false,
        ], $overrides);
    }

    private function makeTieredProduct(array $overrides = []): array
    {
        return $this->makeProduct(array_merge([
            'stock' => 0,
            'pre_order' => true,
            'min_barem' => 25.0,
            'max_barem' => 1250.0,
            'barem_step' => 0.01,
        ], $overrides));
    }

    public function testValidQuantityPasses(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $this->assertNull($validator->validate($this->makeProduct(), 3));
    }

    public function testQuantityBelowMinOrderFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['min_order' => 2]), 1);

        $this->assertSame('Sipariş adedi en az 2 olmalı.', $error);
    }

    public function testQuantityAboveMaxOrderFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['max_order' => 5]), 6);

        $this->assertSame('Sipariş adedi en fazla 5 olmalı.', $error);
    }

    public function testMaxOrderZeroMeansUnlimited(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['max_order' => 0]), 1000);

        $this->assertNull($error);
    }

    public function testMaxOrderNullMeansUnlimited(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['max_order' => null]), 1000);

        $this->assertNull($error);
    }

    public function testOutOfStockFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['stock' => 0, 'min_order' => 1]), 1);

        $this->assertSame('Ürün stokta yok.', $error);
    }

    /**
     * Ön sipariş ürünleri stoktan bağımsız işleme alınır (canlı API testiyle
     * doğrulandı: stok=0 olan bir pre_order ürünü başarıyla sipariş edilebiliyor).
     */
    public function testPreOrderProductBypassesOutOfStockCheck(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeProduct(['stock' => 0, 'pre_order' => true, 'min_order' => 1]), 1);

        $this->assertNull($error);
    }

    public function testValidBaremPasses(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $this->assertNull($validator->validate($this->makeTieredProduct(), 1, 25.01));
    }

    public function testMissingBaremFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeTieredProduct(), 1, null);

        $this->assertSame('Lütfen bir tutar girin.', $error);
    }

    public function testBaremBelowMinFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeTieredProduct(), 1, 10.0);

        $this->assertSame('Tutar 25 ile 1250 arasında olmalı.', $error);
    }

    public function testBaremAboveMaxFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeTieredProduct(), 1, 2000.0);

        $this->assertSame('Tutar 25 ile 1250 arasında olmalı.', $error);
    }

    public function testBaremNotOnStepFails(): void
    {
        $validator = new OrderValidator($this->makeLang());

        $error = $validator->validate($this->makeTieredProduct(), 1, 25.015);

        $this->assertSame('Tutar 0.01 adımlarıyla artmalı.', $error);
    }

    public function testTieredProductIgnoresQuantityAndStockRules(): void
    {
        $validator = new OrderValidator($this->makeLang());

        // min_order=2 olsa ve quantity=1 gönderilse dahi (baremli üründe
        // adet zaten kullanılmıyor) barem geçerliyse hata dönmemeli.
        $product = $this->makeTieredProduct(['min_order' => 2, 'stock' => 0]);

        $this->assertNull($validator->validate($product, 1, 100.0));
    }
}
