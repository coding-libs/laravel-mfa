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

    public function model()
    {
        $morph = config('mfa.morph', []);
        $name = $morph['name'] ?? 'model';
        return $this->morphTo(__FUNCTION__, $name . '_type', $name . '_id');
    }
}

