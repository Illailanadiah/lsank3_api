<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankChecklist extends Model
{
    protected $table = 'lsank_checklists';

    protected $primaryKey = 'checklist_id';

    protected $fillable = [
        'application_type_id',
        'checklist_name',
        'description',
        'pdf_path',
        'status',
    ];

    public function applicationType()
    {
        return $this->belongsTo(
            LsankApplicationType::class,
            'application_type_id',
            'application_type_id'
        );
    }
}
