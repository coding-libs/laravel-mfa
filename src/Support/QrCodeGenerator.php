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
    private const DEFAULT_SIZE = 200;
    private const DEFAULT_MARGIN = 4;
    private const DEFAULT_COMPRESSION_QUALITY = 9;
    private const DEFAULT_IMAGE_FORMAT = 'png';
    private const BACKGROUND_COLOR_R = 255;
    private const BACKGROUND_COLOR_G = 255;
    private const BACKGROUND_COLOR_B = 255;
    private const FOREGROUND_COLOR_R = 0;
    private const FOREGROUND_COLOR_G = 0;
    private const FOREGROUND_COLOR_B = 0;

    public static function generateBase64Png(string $text, int $size = self::DEFAULT_SIZE): string
    {
        $renderer = self::selectRenderer($size);
        $writer = new Writer($renderer);
        $pngData = $writer->writeString($text);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    public static function generateSvg(string $text, int $size = self::DEFAULT_SIZE): string
    {
        $renderer = self::createImageRenderer($size, new SvgImageBackEnd());
        $writer = new Writer($renderer);
        return $writer->writeString($text);
    }

    public static function generateBase64Svg(string $text, int $size = self::DEFAULT_SIZE): string
    {
        $svgData = self::generateSvg($text, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svgData);
    }

    private static function selectRenderer(int $size)
    {
        if (class_exists(\Imagick::class)) {
            return self::createImageRenderer($size, new ImagickImageBackEnd());
        }

        if (extension_loaded('gd')) {
            return new GDLibRenderer(
                $size,
                self::DEFAULT_MARGIN,
                self::DEFAULT_IMAGE_FORMAT,
                self::DEFAULT_COMPRESSION_QUALITY,
                self::createDefaultFill()
            );
        }

        throw new \RuntimeException('No image backend available: install Imagick or enable GD.');
    }

    private static function createImageRenderer(int $size, \BaconQrCode\Renderer\Image\ImageBackEndInterface $imageBackEnd)
    {
        return new ImageRenderer(
            new RendererStyle(
                $size,
                0,
                null,
                null,
                self::createDefaultFill(),
                SquareModule::instance()
            ),
            $imageBackEnd
        );
    }

    private static function createDefaultFill()
    {
        return Fill::uniformColor(
            new Rgb(self::BACKGROUND_COLOR_R, self::BACKGROUND_COLOR_G, self::BACKGROUND_COLOR_B),
            new Rgb(self::FOREGROUND_COLOR_R, self::FOREGROUND_COLOR_G, self::FOREGROUND_COLOR_B)
        );
    }
}

