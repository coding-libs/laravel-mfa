<?php

namespace CodingLibs\MFA\Models;

use Illuminate\Database\Eloquent\Model;

class MfaMethod extends Model
{
    protected $table = 'mfa_methods';

    protected $guarded = [];

    protected $casts = [
        'enabled_at' => 'datetime',
        'last_used_at' => 'datetime',
        'secret' => 'encrypted',
    ];
}

