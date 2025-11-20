<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPlan extends Model
{
    use HasFactory;
     protected $fillable = [
        'idea_id',
        'owner_id',
        'committee_id',
        'report_id',
        'meeting_id',
        'key_partners',
        'key_activities',
        'key_resources',
        'value_proposition',
        'customer_relationships',
        'channels',
        'customer_segments',
        'cost_structure',
        'revenue_streams',
        'status',
        'latest_score',
    ];


      public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

   
    public function ideaowner()
    {
        return $this->belongsTo(User::class);
    }

   
    public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

 
    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

      public function evaluations()
    {
        return $this->hasOne(Evaluation::class);
    }
}
