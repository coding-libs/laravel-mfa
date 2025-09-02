<?php

namespace CodingLibs\MFA\Facades;

use Illuminate\Support\Facades\Facade;

class MFA extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mfa';
    }
}

