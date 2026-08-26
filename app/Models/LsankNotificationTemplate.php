<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNotificationTemplate extends Model
{
    protected $table = 'lsank_notification_templates';

    protected $primaryKey = 'template_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'event_type',
        'audience',
        'channel',
        'language',
        'subject_template',
        'title_template',
        'body_template',
        'provider_template_name',
        'provider_parameter_keys',
        'severity',
        'priority',
        'action_required',
        'action_label',
        'show_as_ribbon',
        'ribbon_duration_seconds',
        'mandatory',
        'is_enabled',
    ];

    protected $casts = [
        'provider_parameter_keys' => 'array',
        'priority' => 'integer',
        'action_required' => 'boolean',
        'show_as_ribbon' => 'boolean',
        'ribbon_duration_seconds' => 'integer',
        'mandatory' => 'boolean',
        'is_enabled' => 'boolean',
    ];
}