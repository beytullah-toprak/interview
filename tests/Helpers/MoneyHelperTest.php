<?php

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase;
use Turkpin\InterviewTest\Helpers\MoneyHelper;

class MoneyHelperTest extends TestCase
{
    public function testTurkishUsesCommaDecimalAndDotThousands(): void
    {
        $this->assertSame('1.234,50 ₺', MoneyHelper::format(1234.5, 'tr'));
    }

    public function testEnglishUsesDotDecimalAndCommaThousands(): void
    {
        $this->assertSame('1,234.50 ₺', MoneyHelper::format(1234.5, 'en'));
    }

    public function testAlwaysShowsAtLeastTwoDecimals(): void
    {
        $this->assertSame('19,90 ₺', MoneyHelper::format(19.9, 'tr'));
        $this->assertSame('0,00 ₺', MoneyHelper::format(0, 'tr'));
    }

    /**
     * Test API'si 0.001 gibi birim fiyatlar dönüyor; 2 basamağa yuvarlansa
     * "0,00" görünür ve kullanıcıyı yanıltırdı.
     */
    public function testKeepsSmallAmountsVisibleUpToFourDecimals(): void
    {
        $this->assertSame('0,001 ₺', MoneyHelper::format(0.001, 'tr'));
        $this->assertSame('0,025 ₺', MoneyHelper::format(0.025, 'tr'));
        $this->assertSame('0,0125 ₺', MoneyHelper::format(0.0125, 'tr'));
    }

    public function testDefaultsToTurkishFormatting(): void
    {
        $this->assertSame('19,90 ₺', MoneyHelper::format(19.9));
    }
}
