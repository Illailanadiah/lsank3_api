<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankNoticeRestriction extends Model
{
    protected $table = 'lsank_notice_restrictions';

    protected $primaryKey = 'restriction_id';

    protected $fillable = [
        'notice_id',
        'user_id',
        'license_id',
        'application_id',
        'notice_no',
        'notice_type',
        'category',
        'restriction_status',
        'response_required',
        'restriction_reason',
        'response_data',
        'responded_at',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'response_data' => 'array',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            LsankUser::class,
            'user_id',
            'user_id'
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

    public function application()
    {
        return $this->belongsTo(
            LsankApplication::class,
            'application_id',
            'application_id'
        );
    }
}
