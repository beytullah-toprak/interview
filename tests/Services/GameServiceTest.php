<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Api\TurkpinApiClient;
use Turkpin\InterviewTest\Services\GameService;

class GameServiceTest extends TestCase
{
    public function testGetGamesReturnsMappedListOnSuccess(): void
    {
        $data = simplexml_load_string(
            '<params><oyunListesi>'
            . '<oyun><id>1</id><name>Game 1</name></oyun>'
            . '<oyun><id>2</id><name>Game 2</name></oyun>'
            . '</oyunListesi></params>'
        );

        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->with('epinOyunListesi')->willReturn([
            'success' => true,
            'error_code' => '000',
            'error_message' => '',
            'data' => $data,
        ]);

        $result = (new GameService($client))->getGames();

        $this->assertTrue($result['success']);
        $this->assertSame(
            [
                ['id' => '1', 'name' => 'Game 1'],
                ['id' => '2', 'name' => 'Game 2'],
            ],
            $result['games']
        );
    }

    public function testGetGamesReturnsEmptyListOnFailure(): void
    {
        $client = $this->createMock(TurkpinApiClient::class);
        $client->method('request')->willReturn([
            'success' => false,
            'error_code' => '999',
            'error_message' => 'Yetkisiz erişim',
            'data' => null,
        ]);

        $result = (new GameService($client))->getGames();

        $this->assertFalse($result['success']);
        $this->assertSame('Yetkisiz erişim', $result['error_message']);
        $this->assertSame([], $result['games']);
    }
}
