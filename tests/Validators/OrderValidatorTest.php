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
            'price' => 9.99,
            'tax_type' => 'included',
            'pre_order' => false,
        ], $overrides);
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
}
