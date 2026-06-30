<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicationDocument extends Model
{
    protected $table = 'lsank_application_documents';
    protected $primaryKey = 'document_id';

    protected $fillable = [
        'application_id',
        'document_type_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'status',
        'remarks',
    ];
}