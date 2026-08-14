<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LsankCivilCase extends Model
{
    use HasFactory;

    protected $table = 'lsank_civil_cases';

    protected $primaryKey = 'civil_case_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'case_no',
        'license_id',
        'file_no',
        'offence',
        'party_name',
        'section_regulation',
        'court_location',
        'judge_name',
        'punishment',
        'outstanding_amount',
        'case_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'outstanding_amount' => 'decimal:2',
    ];

    public function license()
    {
        return $this->belongsTo(
            LsankLicense::class,
            'license_id',
            'license_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            LsankUser::class,
            'created_by',
            'user_id'
        );
    }

    public function updater()
    {
        return $this->belongsTo(
            LsankUser::class,
            'updated_by',
            'user_id'
        );
    }
}
