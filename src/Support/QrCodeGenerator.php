<?php

namespace CodingLibs\MFA\Support;

class QrCodeGenerator
{
    public static function generateBase64Png(string $text, int $size = 200): string
    {
        // Lightweight inline QR encoder using endroid/qr-code if available, else fallback to Google Charts
        if (class_exists('Endroid\\QrCode\\QrCode')) {
            return self::generateWithEndroid($text, $size);
        }
        return self::generateWithGoogleCharts($text, $size);
    }

    protected static function generateWithEndroid(string $text, int $size): string
    {
        $qrCode = new \Endroid\QrCode\QrCode($text);
        $qrCode->setSize($size);
        $pngData = $qrCode->writeString();
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    protected static function generateWithGoogleCharts(string $text, int $size): string
    {
        $url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . rawurlencode($text);
        $pngData = @file_get_contents($url) ?: '';
        return 'data:image/png;base64,' . base64_encode($pngData);
    }
}

