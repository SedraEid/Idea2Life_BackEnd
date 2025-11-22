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
        'roadmap_id',
        'description',
        'report_type',
        'evaluation_score',
        'strengths',
        'weaknesses',
        'recommendations',
         'delay_count',
        'status',
        'improvement_plan_id',
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

    public function roadmap()
    {
        return $this->belongsTo(Roadmap::class);
    }

      public function businessPlans()
    {
        return $this->hasOne(BusinessPlan::class);
    }

    public function funding()
{
    return $this->hasOne(Funding::class);
}

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }



   public function improvementPlan()
{
    return $this->belongsTo(ImprovementPlan::class, 'improvement_plan_id');
}
}
