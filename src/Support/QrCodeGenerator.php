<?php

namespace CodingLibs\MFA\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeGenerator
{
    public static function generateBase64Png(string $text, int $size = 200): string
    {
        $renderer = new Png(
            new RendererStyle(
                $size,
                0,
                null,
                null,
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0)),
                new SquareModule()
            )
        );

        $writer = new Writer($renderer);
        $pngData = $writer->writeString($text);
        return 'data:image/png;base64,' . base64_encode($pngData);
    }
}

