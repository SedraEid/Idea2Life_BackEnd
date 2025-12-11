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
        'challenge_detected',
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
        'committee_decision',
        'decision_notes',
        'recorded_by',
    ];
    protected $casts = [
        'challenge_detected' => 'boolean',
        'ready_to_separate' => 'boolean',
        'recommended_separation_date' => 'date',
        'actual_separation_date' => 'date',
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
