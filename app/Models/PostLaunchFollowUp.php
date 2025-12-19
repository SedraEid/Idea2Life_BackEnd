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
        'idea_id',
        'checkpoint',
        'issue_type',
        'issue_description',
        'platform_action',
        'status',
        'requires_reexecution',
        'committee_recommendation',
        'reviewed_by',
    ];


    public function launchProject()
    {
        return $this->belongsTo(LaunchProject::class, 'launch_project_id');
    }
     public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
