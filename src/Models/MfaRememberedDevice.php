<?php

namespace CodingLibs\MFA\Models;

use Illuminate\Database\Eloquent\Model;

class MfaRememberedDevice extends Model
{
    protected $table = 'mfa_remembered_devices';

    protected $guarded = [];
}

