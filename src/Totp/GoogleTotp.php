<?php

namespace CodingLibs\MFA\Totp;

class GoogleTotp
{
    public static function generateSecret(int $length = 20): string
    {
        $randomBytes = random_bytes($length);
        return rtrim(strtr(base64_encode($randomBytes), '+/', 'AB'), '=');
    }

    public static function buildOtpAuthUrl(string $secret, string $label, string $issuer, int $digits = 6, int $period = 30): string
    {
        $labelEnc = rawurlencode($label);
        $issuerEnc = rawurlencode($issuer);
        return sprintf('otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d', $labelEnc, $secret, $issuerEnc, $digits, $period);
    }

    public static function verifyCode(string $secret, string $code, int $digits = 6, int $period = 30, int $window = 1, ?int $timestamp = null): bool
    {
        $timeSlice = floor(($timestamp ?? time()) / $period);
        for ($i = -$window; $i <= $window; $i++) {
            $hash = self::hotp(self::base32Decode($secret), $timeSlice + $i);
            $otp = self::truncateToDigits($hash, $digits);
            if (hash_equals($otp, str_pad($code, $digits, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    protected static function hotp(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        return hash_hmac('sha1', $binCounter, $key, true);
    }

    protected static function truncateToDigits(string $hmac, int $digits): string
    {
        $offset = ord($hmac[19]) & 0xf;
        $binary = ((ord($hmac[$offset]) & 0x7f) << 24) |
            (ord($hmac[$offset + 1]) << 16) |
            (ord($hmac[$offset + 2]) << 8) |
            (ord($hmac[$offset + 3]));
        $otp = $binary % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    protected static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $char = $secret[$i];
            if ($char === '=') {
                break;
            }
            $val = strpos($alphabet, $char);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }
        return $result;
    }
}

