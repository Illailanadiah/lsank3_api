<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNotificationDelivery extends Model
{
    protected $table = 'lsank_notification_deliveries';
    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'notification_id',
        'channel',
        'recipient',
        'status',
        'provider',
        'provider_message_id',
        'attempt_count',
        'payload',
        'last_error',
        'queued_at',
        'processing_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'delivery_key',
    ];

    protected $casts = [
        'notification_id' => 'integer',
        'attempt_count' => 'integer',
        'payload' => 'array',
        'queued_at' => 'datetime',
        'processing_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}