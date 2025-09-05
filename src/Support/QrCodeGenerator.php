<?php

namespace CodingLibs\MFA\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    public static function generateBase64Png(string $text, int $size = 200): string
    {
        $renderer = self::selectRenderer($size);
        $writer = new Writer($renderer);
        $pngData = $writer->writeString($text);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    public static function generateSvg(string $text, int $size = 200): string
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
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        return $writer->writeString($text);
    }

    public static function generateBase64Svg(string $text, int $size = 200): string
    {
        $svgData = self::generateSvg($text, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    private static function selectRenderer(int $size)
    {
        if (class_exists(\Imagick::class)) {
            return new ImageRenderer(
                new RendererStyle(
                    $size,
                    0,
                    null,
                    null,
                    Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0)),
                    SquareModule::instance()
                ),
                new ImagickImageBackEnd()
            );
        }

        if (extension_loaded('gd')) {
            return new GDLibRenderer(
                $size,
                4, // margin
                'png', // image format
                9, // compression quality
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0))
            );
        }

        throw new \RuntimeException('No image backend available: install Imagick or enable GD.');
    }
}

