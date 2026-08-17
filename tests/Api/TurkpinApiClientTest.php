<?php

namespace Tests\Api;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Api\TurkpinApiClient;

class TurkpinApiClientTest extends TestCase
{
    private function makeClient(): TurkpinApiClient
    {
        return new TurkpinApiClient('https://example.test/api.php', 'user', 'pass');
    }

    public function testSuccessfulResponseIsParsed(): void
    {
        $xml = '<APIResponse><params><error>000</error><error_desc>OK</error_desc>'
            . '<oyunListesi><oyun><id>1</id><name>Test Oyun</name></oyun></oyunListesi>'
            . '</params></APIResponse>';

        $result = $this->makeClient()->parseResponse($xml);

        $this->assertTrue($result['success']);
        $this->assertSame('000', $result['error_code']);
        $this->assertSame('Test Oyun', (string) $result['data']->oyunListesi->oyun->name);
    }

    public function testErrorTagFormatIsParsed(): void
    {
        $xml = '<APIResponse><params><error>999</error><error_desc>Geçersiz kullanıcı</error_desc></params></APIResponse>';

        $result = $this->makeClient()->parseResponse($xml);

        $this->assertFalse($result['success']);
        $this->assertSame('999', $result['error_code']);
        $this->assertSame('Geçersiz kullanıcı', $result['error_message']);
    }

    public function testHataNoFormatIsParsed(): void
    {
        $xml = '<APIResponse><params><HATA_NO>5</HATA_NO><HATA_ACIKLAMA>Stok yetersiz</HATA_ACIKLAMA></params></APIResponse>';

        $result = $this->makeClient()->parseResponse($xml);

        $this->assertFalse($result['success']);
        $this->assertSame('5', $result['error_code']);
        $this->assertSame('Stok yetersiz', $result['error_message']);
    }

    public function testInvalidXmlIsHandled(): void
    {
        $result = $this->makeClient()->parseResponse('not xml at all <<<');

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_RESPONSE', $result['error_code']);
        $this->assertNull($result['data']);
    }
}
