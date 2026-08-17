<?php

namespace Turkpin\InterviewTest\Helpers;

/**
 * Tutarları aktif dile göre biçimlendirir.
 *
 * Not: intl (NumberFormatter) eklentisi Docker imajında bulunmadığı için
 * bilerek kullanılmıyor; ayırıcılar elle veriliyor. assets/js/app.js
 * içindeki formatAmount() ile aynı kuralı uygular, böylece sunucudan basılan
 * birim fiyat ile JS'in hesapladığı satır tutarı aynı görünür.
 */
class MoneyHelper
{
    private const MIN_DECIMALS = 2;
    private const MAX_DECIMALS = 4;

    /**
     * @param float  $amount Biçimlendirilecek tutar
     * @param string $lang   Aktif dil kodu ('tr' | 'en')
     */
    public static function format(float $amount, string $lang = 'tr'): string
    {
        $decimals = self::neededDecimals($amount);

        // tr: 1.234,56 — en: 1,234.56
        $formatted = $lang === 'en'
            ? number_format($amount, $decimals, '.', ',')
            : number_format($amount, $decimals, ',', '.');

        return $formatted . ' ₺';
    }

    /**
     * Test API'si 0.001 gibi çok küçük birim fiyatlar dönüyor; sabit 2 basamak
     * bunları "0,00" gösterip yanıltıcı olurdu. Bu yüzden tutarı bozmadan
     * gösterebilen en az basamak sayısı (2-4 arası) seçiliyor.
     */
    private static function neededDecimals(float $amount): int
    {
        for ($decimals = self::MIN_DECIMALS; $decimals < self::MAX_DECIMALS; $decimals++) {
            if (abs(round($amount, $decimals) - $amount) < 0.000001) {
                return $decimals;
            }
        }

        return self::MAX_DECIMALS;
    }
}
