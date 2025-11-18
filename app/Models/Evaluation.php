<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;
      protected $fillable = [
        'idea_id',
        'gantt_id', 
         'funding_id',
        'committee_id',
        'business_plan_id',
        'evaluation_type',
        'score',
        'recommendation',
        'comments',
        'strengths',
        'weaknesses',
        'financial_analysis',
        'risks',
        'status',
    ];

        public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

      public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

       public function businessPlan()
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    public function gantt()
{
    return $this->belongsTo(GanttChart::class, 'gantt_id');
}

public function funding()
{
    return $this->belongsTo(Funding::class);
}
}
