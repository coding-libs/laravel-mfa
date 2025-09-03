<?php

namespace CodingLibs\MFA\Models;

use Illuminate\Database\Eloquent\Model;

class MfaRecoveryCode extends Model
{
    protected $table = 'mfa_recovery_codes';

    protected $guarded = [];

    protected $casts = [
        'used_at' => 'datetime',
    ];
}

