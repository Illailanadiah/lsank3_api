<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNotification extends Model
{
    protected $table = 'lsank_notifications';

    protected $primaryKey = 'notification_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'notification_type',
        'event_type',
        'audience',
        'severity',
        'priority',
        'related_module',
        'related_id',
        'action_required',
        'action_label',
        'action_url',
        'show_as_ribbon',
        'ribbon_duration_seconds',
        'first_shown_at',
        'last_shown_at',
        'shown_count',
        'is_read',
        'read_at',
        'dismissed_at',
        'action_completed_at',
        'expires_at',
        'metadata',
        'event_key',
        'sent_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'related_id' => 'integer',
        'priority' => 'integer',
        'action_required' => 'boolean',
        'show_as_ribbon' => 'boolean',
        'ribbon_duration_seconds' => 'integer',
        'shown_count' => 'integer',
        'is_read' => 'boolean',
        'metadata' => 'array',
        'first_shown_at' => 'datetime',
        'last_shown_at' => 'datetime',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'action_completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
