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
        'task_name',
        'description',
        'start_date',
        'end_date',
        'progress_percentage',
        'status',
        'priority',
        'attachments'
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function gantt()
    {
        return $this->belongsTo(GanttChart::class, 'gantt_id');
    }

  public function funding()
{
    return $this->belongsTo(Funding::class);
}

public function fundings()
{
    return $this->hasMany(Funding::class, 'task_id');
}


}
