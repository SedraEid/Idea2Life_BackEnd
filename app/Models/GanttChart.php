<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GanttChart extends Model
{
    use HasFactory;
      protected $table = 'gantt_charts';

    protected $fillable = [
        'idea_id',
        'phase_name',
        'start_date',
        'end_date',
        'progress',
        'status',
        'priority',
        'approval_status',
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'gantt_id');
    }

      public function improvementPlans()
    {
        return $this->hasMany(ImprovementPlan::class, 'gantt_chart_id');
    }

       public function fundings()
    {
        return $this->hasMany(Funding::class, 'gantt_id');
    }

        public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'gantt_id');
    }

    public function meetings()
{
    return $this->hasMany(Meeting::class, 'gantt_chart_id');
}

    


}
