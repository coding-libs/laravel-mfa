<?php

namespace CodingLibs\MFA\Models;

use Illuminate\Database\Eloquent\Model;

class MfaRememberedDevice extends Model
{
    protected $table = 'mfa_remembered_devices';

    protected $guarded = [];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function model()
    {
        $morph = config('mfa.morph', []);
        $name = $morph['name'] ?? 'model';
        return $this->morphTo(__FUNCTION__, $name . '_type', $name . '_id');
    }
}

