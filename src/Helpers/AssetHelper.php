<?php

namespace Turkpin\InterviewTest\Helpers;

/**
 * CSS/JS dosyalarına otomatik versiyon (cache-busting) ekler ve
 * .env'deki APP_URL'i başa ekleyerek tam URL üretir.
 */
class AssetHelper
{
    public static function url(string $relativePath): string
    {
        $fullPath = __DIR__ . '/../../' . ltrim($relativePath, '/');
        $version = file_exists($fullPath) ? filemtime($fullPath) : time();

        $baseUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

        return $baseUrl . '/' . ltrim($relativePath, '/') . '?v=' . $version;
    }
}