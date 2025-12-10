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
        'recorded_by',
        'kpi_active_users',
        'kpi_sales',
        'kpi_user_growth',
        'kpi_engagement',
        'ready_to_separate',
        'separation_date',
        'profit_distribution_notes',
    ];

    protected $casts = [
        'challenge_detected' => 'boolean',
        'ready_to_separate' => 'boolean',
        'separation_date' => 'datetime',
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
