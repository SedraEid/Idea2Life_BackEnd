<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

     protected $table = 'tasks';

   protected $fillable = [
        'idea_id',
        'gantt_id',
        'owner_id',
        'funding_id',
        'meeting_id',
        'report_id',
        'task_name',
        'description',
        'start_date',
        'end_date',
        'progress_percentage',
        'status',
        'priority',
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function gantt()
    {
        return $this->belongsTo(GanttChart::class, 'gantt_id');
    }

    public function ideaowner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

  public function funding()
{
    return $this->belongsTo(Funding::class);
}

public function meeting()
{
    return $this->belongsTo(Meeting::class);
}

    public function report()
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

       public function fundings()
    {
        return $this->hasMany(Funding::class, 'gantt_id');
    }

}
