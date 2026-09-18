<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicationCategory extends Model
{
    protected $table = 'lsank_application_categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'application_type_id',
        'category_code',
        'category_name',
        'reference_prefix',
        'is_one_off',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_one_off' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function applicationType()
    {
        return $this->belongsTo(LsankApplicationType::class, 'application_type_id', 'application_type_id');
    }

    public function applications()
    {
        return $this->hasMany(LsankApplication::class, 'category_id', 'category_id');
    }
}
