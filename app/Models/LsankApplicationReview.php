<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LsankApplicationReview extends Model
{
    protected $table = 'lsank_application_reviews';
    protected $primaryKey = 'review_id';

    protected $fillable = [
        'application_id',
        'reviewer_id',
        'review_role_id',
        'review_status',
        'remarks',
        'reviewed_at',
    ];
}