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

    public function model()
    {
        $morph = config('mfa.morph', []);
        $name = $morph['name'] ?? 'model';
        return $this->morphTo(__FUNCTION__, $name . '_type', $name . '_id');
    }
}

