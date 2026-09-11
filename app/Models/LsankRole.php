<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LsankRole extends Model
{
    protected $table = 'lsank_roles';

    protected $primaryKey = 'role_id';

    protected $fillable = [
        'role_name',
        'role_code',
        'description',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            LsankUser::class,
            'lsank_user_roles',
            'role_id',
            'user_id',
            'role_id',
            'user_id'
        )->withPivot([
            'user_role_id',
            'assigned_by',
            'assigned_at',
        ])->withTimestamps();
    }
}