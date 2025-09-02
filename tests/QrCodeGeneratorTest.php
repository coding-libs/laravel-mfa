<?php

use CodingLibs\MFA\Support\QrCodeGenerator;

it('generates base64 data url when backend available', function () {
    $skip = ! (class_exists(Imagick::class) || extension_loaded('gd'));
    if ($skip) {
        $this->markTestSkipped('No image backend available in runtime.');
    }

    $dataUrl = QrCodeGenerator::generateBase64Png('otpauth://totp/example?secret=ABC');
    expect($dataUrl)->toStartWith('data:image/png;base64,');
});

