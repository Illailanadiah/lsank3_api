<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankAnnouncement extends Model
{
    protected $table = 'lsank_announcements';

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'title',
        'message',
        'category',
        'type',
        'status',
        'is_important',
        'is_pinned',
        'start_at',
        'end_at',
        'created_by',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'is_pinned' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
