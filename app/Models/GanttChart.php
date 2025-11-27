<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GanttChart extends Model
{
    use HasFactory;
    protected $fillable = [
        'idea_id',
        'phase_name',
        'start_date',
        'end_date',
        'priority',
        'status',            
        'progress',          
        'approval_status',  
        'evaluation_score',   
        'failure_count',      
        'attachments',       
    ];

        protected $casts = [
        'attachments' => 'array', 
        'start_date' => 'date',
        'end_date' => 'date',
    ];
    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'gantt_id');
    }


public function fundings()
{
    return $this->hasMany(Funding::class, 'gantt_id');
}



    


}
