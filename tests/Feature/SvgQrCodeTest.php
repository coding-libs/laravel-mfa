<?php

use CodingLibs\MFA\MFA;
use CodingLibs\MFA\Support\QrCodeGenerator;
use Illuminate\Contracts\Auth\Authenticatable;

class SvgQrCodeTestFakeUser implements Authenticatable
{
    public function __construct(public int|string $id, public ?string $email = null) {}
    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword() { return ''; }
    public function getAuthPasswordName() { return 'password'; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName() { return 'remember_token'; }
    public function getEmailForVerification() { return $this->email; }
}

it('generates SVG QR code for TOTP', function () {
    $user = new SvgQrCodeTestFakeUser(4001, 'user@example.com');
    $mfa = app(MFA::class);

    $result = $mfa->setupTotp($user, 'TestApp', 'user@example.com');
    expect($result)->toHaveKeys(['secret', 'otpauth_url']);

    $svg = $mfa->generateTotpQrCodeSvg($user, 'TestApp', 'user@example.com', 200);
    
    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
    expect($svg)->toContain('xmlns="http://www.w3.org/2000/svg"');
    expect($svg)->toContain('width="200"');
    expect($svg)->toContain('height="200"');
    expect($svg)->toContain('</svg>');
});

it('generates base64 encoded SVG QR code for TOTP', function () {
    $user = new SvgQrCodeTestFakeUser(4002, 'user2@example.com');
    $mfa = app(MFA::class);

    $result = $mfa->setupTotp($user, 'TestApp', 'user2@example.com');
    expect($result)->toHaveKeys(['secret', 'otpauth_url']);

    $base64Svg = $mfa->generateTotpQrCodeBase64Svg($user, 'TestApp', 'user2@example.com', 200);
    
    expect($base64Svg)->toBeString();
    expect($base64Svg)->toStartWith('data:image/svg+xml;base64,');
    
    // Decode and verify it's valid SVG
    $decodedSvg = base64_decode(substr($base64Svg, 26)); // Remove data:image/svg+xml;base64,
    expect($decodedSvg)->toContain('<svg');
    expect($decodedSvg)->toContain('</svg>');
});

it('returns null for SVG generation when TOTP not set up', function () {
    $user = new SvgQrCodeTestFakeUser(4003, 'user3@example.com');
    $mfa = app(MFA::class);

    $svg = $mfa->generateTotpQrCodeSvg($user);
    expect($svg)->toBeNull();

    $base64Svg = $mfa->generateTotpQrCodeBase64Svg($user);
    expect($base64Svg)->toBeNull();
});

it('generates SVG with custom size', function () {
    $user = new SvgQrCodeTestFakeUser(4004, 'user4@example.com');
    $mfa = app(MFA::class);

    $result = $mfa->setupTotp($user, 'TestApp', 'user4@example.com');
    expect($result)->toHaveKeys(['secret', 'otpauth_url']);

    $svg = $mfa->generateTotpQrCodeSvg($user, 'TestApp', 'user4@example.com', 300);
    
    expect($svg)->toBeString();
    expect($svg)->toContain('width="300"');
    expect($svg)->toContain('height="300"');
});

it('QrCodeGenerator generates raw SVG', function () {
    $testText = 'otpauth://totp/TestApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=TestApp';
    
    $svg = QrCodeGenerator::generateSvg($testText, 200);
    
    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
    expect($svg)->toContain('xmlns="http://www.w3.org/2000/svg"');
    expect($svg)->toContain('width="200"');
    expect($svg)->toContain('height="200"');
    expect($svg)->toContain('</svg>');
});

it('QrCodeGenerator generates base64 encoded SVG', function () {
    $testText = 'otpauth://totp/TestApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=TestApp';
    
    $base64Svg = QrCodeGenerator::generateBase64Svg($testText, 200);
    
    expect($base64Svg)->toBeString();
    expect($base64Svg)->toStartWith('data:image/svg+xml;base64,');
    
    // Decode and verify it's valid SVG
    $decodedSvg = base64_decode(substr($base64Svg, 26));
    expect($decodedSvg)->toContain('<svg');
    expect($decodedSvg)->toContain('</svg>');
});

it('SVG QR codes are scalable and contain proper viewBox', function () {
    $user = new SvgQrCodeTestFakeUser(4005, 'user5@example.com');
    $mfa = app(MFA::class);

    $result = $mfa->setupTotp($user, 'TestApp', 'user5@example.com');
    expect($result)->toHaveKeys(['secret', 'otpauth_url']);

    $svg = $mfa->generateTotpQrCodeSvg($user, 'TestApp', 'user5@example.com', 200);
    
    expect($svg)->toContain('viewBox="0 0 200 200"');
    
    // Test with different size
    $svg300 = $mfa->generateTotpQrCodeSvg($user, 'TestApp', 'user5@example.com', 300);
    expect($svg300)->toContain('viewBox="0 0 300 300"');
});

it('SVG QR codes work in HTML img tags', function () {
    $user = new SvgQrCodeTestFakeUser(4006, 'user6@example.com');
    $mfa = app(MFA::class);

    $result = $mfa->setupTotp($user, 'TestApp', 'user6@example.com');
    expect($result)->toHaveKeys(['secret', 'otpauth_url']);

    $base64Svg = $mfa->generateTotpQrCodeBase64Svg($user, 'TestApp', 'user6@example.com', 200);
    
    // This should work in an HTML img tag
    $html = '<img src="' . $base64Svg . '" alt="QR Code" />';
    expect($html)->toContain('data:image/svg+xml;base64,');
    expect($html)->toContain('<img');
});
