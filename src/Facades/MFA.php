<?php

namespace CodingLibs\MFA\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel Facade for the MFA service.
 *
 * Adding explicit method annotations here allows IDEs (PHPStorm, Intelephense)
 * to provide static autocompletion when calling methods like MFA::setupTotp().
 *
 * @method static array setupTotp(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $issuer = null, ?string $label = null)
 * @method static bool verifyTotp(\Illuminate\Contracts\Auth\Authenticatable $user, string $code)
 * @method static ?\CodingLibs\MFA\Models\MfaChallenge issueChallenge(\Illuminate\Contracts\Auth\Authenticatable $user, string $method)
 * @method static void registerChannel(\CodingLibs\MFA\Contracts\MfaChannel $channel)
 * @method static ?string generateTotpQrCodeBase64(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $issuer = null, ?string $label = null, int $size = 200)
 * @method static bool verifyChallenge(\Illuminate\Contracts\Auth\Authenticatable $user, string $method, string $code)
 * @method static bool isRememberEnabled()
 * @method static string getRememberCookieName()
 * @method static ?string getRememberTokenFromRequest(\Illuminate\Http\Request $request)
 * @method static bool shouldSkipVerification(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $token)
 * @method static array rememberDevice(\Illuminate\Contracts\Auth\Authenticatable $user, ?int $lifetimeDays = null, ?string $deviceName = null)
 * @method static \Symfony\Component\HttpFoundation\Cookie makeRememberCookie(string $token, ?int $lifetimeDays = null)
 * @method static int forgetRememberedDevice(\Illuminate\Contracts\Auth\Authenticatable $user, string $token)
 * @method static \CodingLibs\MFA\Models\MfaMethod enableMethod(\Illuminate\Contracts\Auth\Authenticatable $user, string $method, array $attributes = [])
 * @method static bool disableMethod(\Illuminate\Contracts\Auth\Authenticatable $user, string $method)
 * @method static bool isEnabled(\Illuminate\Contracts\Auth\Authenticatable $user, string $method)
 * @method static ?\CodingLibs\MFA\Models\MfaMethod getMethod(\Illuminate\Contracts\Auth\Authenticatable $user, string $method)
 * @method static array generateRecoveryCodes(\Illuminate\Contracts\Auth\Authenticatable $user, ?int $count = null, ?int $length = null, bool $replaceExisting = true)
 * @method static bool verifyRecoveryCode(\Illuminate\Contracts\Auth\Authenticatable $user, string $code)
 * @method static int getRemainingRecoveryCodesCount(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static int clearRecoveryCodes(\Illuminate\Contracts\Auth\Authenticatable $user)
 *
 * @mixin \CodingLibs\MFA\MFA
 * @see \CodingLibs\MFA\MFA
 */
class MFA extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mfa';
    }
}

