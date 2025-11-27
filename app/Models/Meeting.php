<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;
     protected $fillable = [
        'idea_id',
        'owner_id',
        'committee_id',
        'meeting_date',
        'meeting_link',
        'notes',
        'requested_by',
        'type',
    ];


    protected $casts = [
    'meeting_date' => 'datetime',
];

      public function idea()
    {
        return $this->belongsTo(Idea::class, 'idea_id');
    }

     public function ideaowner()
    {
        return $this->belongsTo(IdeaOwner::class, 'owner_id');
    }

    public function committee()
    {
        return $this->belongsTo(Committee::class, 'committee_id');
    }

     public function report()
{
    return $this->hasOne(Report::class);
}

      public function businessPlans()
    {
        return $this->hasOne(BusinessPlan::class);
    }

    public function funding()
{
    return $this->hasOne(Funding::class);
}



}
