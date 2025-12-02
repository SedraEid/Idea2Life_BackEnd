<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

      protected $fillable = [
        'idea_id',
        'meeting_id',
        'committee_id',
        'description',
        'report_type',
        'evaluation_score',
        'strengths',
        'weaknesses',
        'recommendations',
        'status',
    ];


    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

      public function businessPlans()
    {
        return $this->hasOne(BusinessPlan::class);
    }

}
