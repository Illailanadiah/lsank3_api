<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNotificationLog extends Model
{
    protected $table = 'lsank_notification_logs';

    protected $primaryKey = 'notification_log_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'event_code',
        'channel',
        'recipient_user_id',
        'recipient_email',
        'application_id',
        'license_id',
        'reference_id',
        'reference_type',
        'subject',
        'payload',
        'status',
        'idempotency_key',
        'attempts',
        'error_message',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function recipient()
    {
        return $this->belongsTo(
            LsankUser::class,
            'recipient_user_id',
            'user_id'
        );
    }

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }

    public function license()
    {
        return $this->belongsTo(
            LsankLicense::class,
            'license_id',
            'license_id'
        );
    }
}