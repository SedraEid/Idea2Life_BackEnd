<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImprovementPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'idea_id',
        'gantt_chart_id',
        'root_cause',
        'corrective_actions',
        'revised_goals',
        'support_needed',
        'deadline',
        'status',
        'committee_score',
        'committee_feedback',
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

  public function reports()
{
    return $this->hasMany(Report::class, 'improvement_plan_id');
}


    public function ganttChart()
    {
        return $this->belongsTo(GanttChart::class);
    }
}
