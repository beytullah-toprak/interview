<?php

namespace Turkpin\InterviewTest\Api;

use Turkpin\InterviewTest\Helpers\Logger;

/**
 * Turkpin API (https://www.turkpin.net/api.php) ile ham HTTP/XML iletişimini yöneten sınıf.
 *
 * Sorumluluğu tek: verilen "cmd" komutunu ve parametreleri XML'e çevirip
 * multipart/form-data ile API'ye POST etmek, gelen XML cevabı standart bir
 * PHP dizisine (array) çevirip döndürmek. İş mantığı (oyun/ürün/sipariş
 * kuralları) burada YOK, bunlar src/Services/ altındaki sınıflarda.
 *
 * Not: API bazı komutlarda hata alanlarını <error>/<error_desc>, bazılarında
 * <HATA_NO>/<HATA_ACIKLAMA> olarak dönüyor (tutarsız). Bu sınıf ikisini de
 * kontrol ederek çağıran kodun bu detaydan habersiz kalmasını sağlıyor.
 */
class TurkpinApiClient
{
    private string $apiUrl;
    private string $username;
    private string $password;

    /**
     * @param string $apiUrl   Örn: https://www.turkpin.net/api.php (.env'den gelir)
     * @param string $username Turkpin bayi kullanıcı adı (.env'den gelir)
     * @param string $password Turkpin bayi şifresi (.env'den gelir)
     */
    public function __construct(string $apiUrl, string $username, string $password)
    {
        $this->apiUrl = $apiUrl;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * .env dosyasındaki TURKPIN_API_URL / TURKPIN_API_USERNAME / TURKPIN_API_PASSWORD
     * değerlerinden otomatik olarak bir client oluşturur.
     *
     * Bu metod olmadan her route/sınıfta ($_ENV['TURKPIN_API_URL'] gibi) aynı
     * satırlar tekrar tekrar yazılıyordu (DRY ihlali). Artık credential okuma
     * mantığı tek yerde: TurkpinApiClient::fromEnv() çağırmak yeterli.
     */
    public static function fromEnv(): self
    {
        return new self(
            $_ENV['TURKPIN_API_URL'],
            $_ENV['TURKPIN_API_USERNAME'],
            $_ENV['TURKPIN_API_PASSWORD']
        );
    }

    /**
     * API'ye tek bir istek atar ve standartlaştırılmış bir sonuç döner.
     *
     * @param string $cmd    Turkpin API komutu, örn: epinOyunListesi, epinUrunleri, epinSiparisYarat
     * @param array  $params Komuta özel ek parametreler, örn: ['oyunKodu' => '1', 'adet' => 2]
     *
     * @return array{
     *     success: bool,
     *     error_code: string,
     *     error_message: string,
     *     data: \SimpleXMLElement|null
     * }
     */
    public function request(string $cmd, array $params = []): array
    {
        $xml = $this->buildRequestXml($cmd, $params);

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['DATA' => $xml]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);

        // API'ye hiç ulaşılamadıysa (timeout, DNS hatası, bağlantı reddi vb.)
        if ($response === false) {
            Logger::error('Turkpin API network error', ['cmd' => $cmd, 'curl_error' => $curlError]);

            return [
                'success' => false,
                'error_code' => 'NETWORK_ERROR',
                'error_message' => $curlError,
                'data' => null,
            ];
        }

        $result = $this->parseResponse($response);

        if (!$result['success']) {
            Logger::error('Turkpin API error', [
                'cmd' => $cmd,
                'error_code' => $result['error_code'],
                'error_message' => $result['error_message'],
            ]);
        }

        return $result;
    }

    /**
     * cmd + username/password + ek parametrelerden Turkpin'in beklediği
     * <APIRequest><params>...</params></APIRequest> formatında XML üretir.
     */
    private function buildRequestXml(string $cmd, array $params): string
    {
        $allParams = array_merge(
            [
                'cmd' => $cmd,
                'username' => $this->username,
                'password' => $this->password,
            ],
            $params
        );

        $xml = new \SimpleXMLElement('<APIRequest><params></params></APIRequest>');
        $paramsNode = $xml->params;

        foreach ($allParams as $key => $value) {
            // htmlspecialchars: parametre değerlerinde &, <, > gibi karakterler
            // XML'i bozmasın diye (örn. şifrede özel karakter olabilir).
            $paramsNode->addChild($key, htmlspecialchars((string) $value));
        }

        return $xml->asXML();
    }

    /**
     * API'den dönen ham XML cevabını okunabilir bir array'e çevirir.
     * Hem <error>/<error_desc> hem <HATA_NO>/<HATA_ACIKLAMA> formatlarını destekler.
     *
     * Public: gerçek bir HTTP isteği atmadan bu parse mantığını unit test edebilmek için.
     */
    public function parseResponse(string $rawXml): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawXml);

        // API beklenmedik/bozuk bir cevap döndürdüyse (örn. HTML hata sayfası)
        if ($xml === false) {
            return [
                'success' => false,
                'error_code' => 'INVALID_RESPONSE',
                'error_message' => 'API cevabı geçerli bir XML değil.',
                'data' => null,
            ];
        }

        $p = $xml->params;

        $errorCode = (string) ($p->error ?? $p->HATA_NO ?? '');
        $errorMessage = (string) ($p->error_desc ?? $p->HATA_ACIKLAMA ?? '');

        return [
            'success' => $errorCode === '000',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'data' => $p,
        ];
    }
}