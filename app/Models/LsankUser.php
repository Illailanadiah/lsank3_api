<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class LsankUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'lsank_users';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'ic_no',
        'name',
        'email',
        'phone',
        'password',
        'user_type',
        'status',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];
}