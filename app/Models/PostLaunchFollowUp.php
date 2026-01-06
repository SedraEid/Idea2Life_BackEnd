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

        'active_users',
        'revenue',
        'growth_rate',

        'performance_status',
        'risk_level',
        'risk_description',

        'committee_decision',

        'owner_response',
        'owner_acknowledged',

        'marketing_support_given',
        'product_issue_detected',

        'actions_taken',
        'committee_notes',

        'is_stable',
        'profit_distributed',
        'graduation_date',

        'reviewed_by',
    ];
     protected $casts = [
        'owner_acknowledged'      => 'boolean',
        'marketing_support_given' => 'boolean',
        'product_issue_detected'  => 'boolean',
        'is_stable'               => 'boolean',
        'profit_distributed'      => 'boolean',
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
