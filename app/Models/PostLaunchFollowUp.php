<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostLaunchFollowUp extends Model
{
    protected $table = 'post_launch_followups';
    use HasFactory;
    
    protected $fillable = [
        'launch_request_id',
        'followup_phase',
        'scheduled_date',
        'status',
        'performance_status',
        'committee_decision',
        'marketing_support_given',
        'product_issue_detected',
        'actions_taken',
        'committee_notes',
        'reviewed_by',
    ];

        public function launchRequest()
    {
        return $this->belongsTo(LaunchRequest::class);
    }

        public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
   
}
