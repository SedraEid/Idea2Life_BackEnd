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
        'followup_phase',
        'scheduled_date',
        'status',
        'notes',
        'marketing_support_given',
        'product_issue_detected',
        'actions_taken',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'marketing_support_given' => 'boolean',
        'product_issue_detected' => 'boolean',
    ];

        public function launchProject()
    {
        return $this->belongsTo(LaunchRequest::class);
    }
   
}
