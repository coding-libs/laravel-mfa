<?php

namespace CodingLibs\MFA\Models;

use Illuminate\Database\Eloquent\Model;

class MfaChallenge extends Model
{
    protected $table = 'mfa_challenges';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function model()
    {
        $morph = config('mfa.morph', []);
        $name = $morph['name'] ?? 'model';
        return $this->morphTo(__FUNCTION__, $name . '_type', $name . '_id');
    }
}

