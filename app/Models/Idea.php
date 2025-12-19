<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    use HasFactory;
    protected $fillable = [
        'owner_id',
        'committee_id',
        'title',
        'description',
        'problem',
        'solution',
        'target_audience',
        'additional_notes',
        'status',
        'roadmap_stage',
        'initial_evaluation_score'
    ];

    public function owner()
{
    return $this->belongsTo(User::class, 'owner_id');
}

     public function committee()
    {
        return $this->belongsTo(Committee::class, 'committee_id');
    }

        public function roadmap()
    {
        return $this->hasOne(Roadmap::class);
    }
     public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

     public function reports()
    {
        return $this->hasMany(Report::class);
    }

       public function businessPlan()
    {
        return $this->hasOne(BusinessPlan::class);
    }


    public function fundings()
    {
        return $this->hasMany(Funding::class);
    }

        public function tasks()
    {
        return $this->hasMany(Task::class);
    }
    
    public function ganttCharts()
{
    return $this->hasMany(GanttChart::class);
}

public function launchProjects()
{
    return $this->hasMany(LaunchProject::class, 'idea_id');
}


    public function postLaunchFollowUps()
    {
        return $this->hasMany(PostLaunchFollowUp::class);
    }




}
