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
        $this->assertFalse($result['order']['pending']);
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

    /**
     * pre_order/barem parametreleri ve "Pending" durumu API dokümantasyonu +
     * canlı testle doğrulandı: baremli üründe adet sabit 1 gider, gerçek
     * tutar "barem" alanından okunur.
     */
    public function testCreateOrderSendsPreOrderAndBaremParamsAndDetectsPending(): void
    {
        $data = simplexml_load_string(
            '<params><siparisNo>26081813260801</siparisNo><siparisTutari>0.025</siparisTutari>'
            . '<siparisSonuc>Pending</siparisSonuc><epin_list></epin_list></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')
            ->with('epinSiparisYarat', [
                'oyunKodu' => '1',
                'urunKodu' => '4',
                'adet' => 1,
                'pre_order' => 'true',
                'barem' => 25.01,
            ])
            ->willReturn([
                'success' => true,
                'error_code' => '000',
                'error_message' => '',
                'data' => $data,
            ]);

        $result = (new OrderService($client))->createOrder('1', '4', 1, true, 25.01);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['order']['pending']);
        $this->assertSame([], $result['order']['epins']);
    }

    public function testCreateOrderWithoutBaremSendsPlainQuantity(): void
    {
        $data = simplexml_load_string(
            '<params><siparisNo>1</siparisNo><siparisTutari>1.0</siparisTutari><epin_list></epin_list></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')
            ->with('epinSiparisYarat', ['oyunKodu' => '1', 'urunKodu' => '10', 'adet' => 3])
            ->willReturn([
                'success' => true,
                'error_code' => '000',
                'error_message' => '',
                'data' => $data,
            ]);

        $result = (new OrderService($client))->createOrder('1', '10', 3);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['order']['pending']);
    }
}
