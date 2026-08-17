<?php

namespace Turkpin\InterviewTest\Services;

use Turkpin\InterviewTest\Api\TurkpinApiClient;

/**
 * Oyun listesi ile ilgili iş mantığını yönetir.
 * Turkpin API'nin "epinOyunListesi" komutunu sarmalar.
 */
class GameService
{
    public function __construct(private TurkpinApiClient $client)
    {
    }

    /**
     * Sipariş verilebilecek tüm oyunları getirir.
     *
     * @return array{
     *     success: bool,
     *     error_code: string,
     *     error_message: string,
     *     games: array<int, array{id: string, name: string}>
     * }
     */
    public function getGames(): array
    {
        $result = $this->client->request('epinOyunListesi');

        if (!$result['success']) {
            return [
                'success' => false,
                'error_code' => $result['error_code'],
                'error_message' => $result['error_message'],
                'games' => [],
            ];
        }

        $games = [];
        foreach ($result['data']->oyunListesi->oyun ?? [] as $oyun) {
            $games[] = [
                'id' => (string) $oyun->id,
                'name' => (string) $oyun->name,
            ];
        }

        return [
            'success' => true,
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'games' => $games,
        ];
    }
}