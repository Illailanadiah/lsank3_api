<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LsankDeviceToken extends Model
{
    protected $table = 'lsank_device_tokens';

    protected $primaryKey = 'device_token_id';

    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'device_id',
        'device_name',
        'platform',
        'app_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            LsankUser::class,
            'user_id',
            'user_id'
        );
    }
}