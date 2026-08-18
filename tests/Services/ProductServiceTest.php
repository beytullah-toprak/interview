<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Api\TurkpinApiClient;
use Turkpin\InterviewTest\Services\ProductService;

class ProductServiceTest extends TestCase
{
    public function testGetProductsMapsFieldsAndTreatsEmptyMaxOrderAsUnlimited(): void
    {
        $data = simplexml_load_string(
            '<params><epinUrunListesi>'
            . '<urun><id>10</id><name>Product 1</name><stock>5</stock>'
            . '<min_order>1</min_order><max_order></max_order>'
            . '<price>9.90</price><tax_type>included</tax_type><pre_order>false</pre_order></urun>'
            . '<urun><id>11</id><name>Product 2</name><stock>0</stock>'
            . '<min_order>2</min_order><max_order>0</max_order>'
            . '<price>19.90</price><tax_type>excluded</tax_type><pre_order>true</pre_order></urun>'
            . '</epinUrunListesi></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->with('epinUrunleri', ['oyunKodu' => '1'])->willReturn([
            'success' => true,
            'error_code' => '000',
            'error_message' => '',
            'data' => $data,
        ]);

        $result = (new ProductService($client))->getProducts('1');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['products']);

        $this->assertSame([
            'id' => '10',
            'name' => 'Product 1',
            'stock' => 5,
            'min_order' => 1,
            'max_order' => null, // boş string -> sınırsız
            'min_barem' => null,
            'max_barem' => null,
            'barem_step' => null,
            'price' => 9.90,
            'tax_type' => 'included',
            'pre_order' => false,
        ], $result['products'][0]);

        $this->assertSame(0, $result['products'][1]['max_order']); // "0" -> sınırsız anlamına gelir
        $this->assertTrue($result['products'][1]['pre_order']);
    }

    public function testGetProductsParsesBaremFields(): void
    {
        $data = simplexml_load_string(
            '<params><epinUrunListesi>'
            . '<urun><id>4</id><name>Product Barem</name><stock>0</stock>'
            . '<min_order>1</min_order><max_order></max_order>'
            . '<min_barem>25</min_barem><max_barem>1250</max_barem><barem_step>0.01</barem_step>'
            . '<price>0.001</price><tax_type>0</tax_type><pre_order>true</pre_order></urun>'
            . '</epinUrunListesi></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->willReturn([
            'success' => true,
            'error_code' => '000',
            'error_message' => '',
            'data' => $data,
        ]);

        $result = (new ProductService($client))->getProducts('1');

        $this->assertSame(25.0, $result['products'][0]['min_barem']);
        $this->assertSame(1250.0, $result['products'][0]['max_barem']);
        $this->assertSame(0.01, $result['products'][0]['barem_step']);
    }

    public function testGetProductsReturnsEmptyListOnFailure(): void
    {
        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->willReturn([
            'success' => false,
            'error_code' => '500',
            'error_message' => 'Oyun bulunamadı',
            'data' => null,
        ]);

        $result = (new ProductService($client))->getProducts('999');

        $this->assertFalse($result['success']);
        $this->assertSame([], $result['products']);
    }
}
