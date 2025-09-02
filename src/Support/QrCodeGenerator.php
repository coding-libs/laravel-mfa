<?php

namespace CodingLibs\MFA\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\GdImageBackEnd;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    public static function generateBase64Png(string $text, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                $size,
                0,
                null,
                null,
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0)),
                SquareModule::instance()
            ),
            self::selectImageBackEnd()
        );

        $writer = new Writer($renderer);
        $pngData = $writer->writeString($text);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    private static function selectImageBackEnd()
    {
        if (class_exists(\Imagick::class)) {
            return new ImagickImageBackEnd();
        }

        if (extension_loaded('gd')) {
            return new GdImageBackEnd();
        }

        throw new \RuntimeException('No image backend available: install Imagick or enable GD.');
    }
}

