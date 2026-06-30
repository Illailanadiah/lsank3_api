<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplication extends Model
{
    protected $table = 'lsank_applications';

    protected $primaryKey = 'application_id';

    protected $fillable = [
        'application_ref_no',
        'user_id',
        'applicant_id',
        'application_type_id',
        'application_status_id',
        'application_category',
        'submitted_at',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(LsankUser::class, 'user_id', 'user_id');
    }

    public function applicant()
    {
        return $this->belongsTo(LsankApplicant::class, 'applicant_id', 'applicant_id');
    }

    public function type()
    {
        return $this->belongsTo(LsankApplicationType::class, 'application_type_id', 'application_type_id');
    }

    public function status()
    {
        return $this->belongsTo(LsankApplicationStatus::class, 'application_status_id', 'application_status_id');
    }

    public function waterBody()
    {
        return $this->hasOne(LsankWaterBodyApplication::class, 'application_id', 'application_id');
    }

    public function effluent()
    {
        return $this->hasOne(LsankEffluentApplication::class, 'application_id', 'application_id');
    }

    public function documents()
    {
        return $this->hasMany(LsankApplicationDocument::class, 'application_id', 'application_id');
    }

    public function reviews()
    {
        return $this->hasMany(LsankApplicationReview::class, 'application_id', 'application_id');
    }
}