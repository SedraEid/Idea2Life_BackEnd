<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaunchRequest extends Model
{
    use HasFactory;
      protected $fillable = [
        'idea_id',
        'execution_steps',
        'marketing_strategy',
        'risk_mitigation',
        'version',
        'founder_commitment',
        'status',
        'committee_notes',
        'approved_by',
        'approved_at',
        'launch_date',
    ];
        protected $casts = [
        'founder_commitment' => 'boolean',
        'approved_at'        => 'datetime',
        'launch_date'        => 'datetime',
    ];
       public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
        public function postLaunchFollowUps()
    {
        return $this->hasMany(PostLaunchFollowUp::class, 'launch_request_id');
    }
}
