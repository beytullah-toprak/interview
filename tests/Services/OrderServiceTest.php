<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Api\TurkpinApiClient;
use Turkpin\InterviewTest\Services\OrderService;

class OrderServiceTest extends TestCase
{
    public function testCreateOrderMapsOrderAndEpinsOnSuccess(): void
    {
        $data = simplexml_load_string(
            '<params><siparisNo>2026081712345</siparisNo><siparisTutari>19.80</siparisTutari>'
            . '<epin_list>'
            . '<epin><id>1</id><code>AAAA-BBBB</code><desc>Epin 1</desc></epin>'
            . '<epin><id>2</id><code>CCCC-DDDD</code><desc>Epin 2</desc></epin>'
            . '</epin_list></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')
            ->with('epinSiparisYarat', ['oyunKodu' => '1', 'urunKodu' => '10', 'adet' => 2])
            ->willReturn([
                'success' => true,
                'error_code' => '000',
                'error_message' => '',
                'data' => $data,
            ]);

        $result = (new OrderService($client))->createOrder('1', '10', 2);

        $this->assertTrue($result['success']);
        $this->assertSame('2026081712345', $result['order']['order_no']);
        $this->assertSame(19.80, $result['order']['total']);
        $this->assertCount(2, $result['order']['epins']);
        $this->assertSame('AAAA-BBBB', $result['order']['epins'][0]['code']);
    }

    public function testCreateOrderReturnsNullOrderOnFailure(): void
    {
        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->willReturn([
            'success' => false,
            'error_code' => '404',
            'error_message' => 'Stok yetersiz',
            'data' => null,
        ]);

        $result = (new OrderService($client))->createOrder('1', '10', 100);

        $this->assertFalse($result['success']);
        $this->assertSame('Stok yetersiz', $result['error_message']);
        $this->assertNull($result['order']);
    }
}
