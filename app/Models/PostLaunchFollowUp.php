<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostLaunchFollowUp extends Model
{
    protected $table = 'post_launch_followups';
    use HasFactory;

    protected $fillable = [
        'launch_project_id',
        'recorded_by',
        'challenge_detected',
        'challenge_level',
        'challenge_description',
        'action_taken',
        'kpi_active_users',
        'kpi_sales',
        'kpi_user_growth',
        'kpi_engagement',
        'overall_status',
        'ready_to_separate',
        'recommended_separation_date',
        'actual_separation_date',
        'review_status',
        'decision_notes',
    ];


    public function launchProject()
    {
        return $this->belongsTo(LaunchProject::class, 'launch_project_id');
    }
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
