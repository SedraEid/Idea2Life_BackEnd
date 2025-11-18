<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roadmap extends Model
{
     use HasFactory;
    protected $fillable = [
        'idea_id',
        'committee_id',
        'owner_id',
        'current_stage',
        'stage_description',
        'progress_percentage',
        'last_update',
        'next_step',
    ];

     public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

      public function ideaowner()
    {
        return $this->belongsTo(IdeaOwner::class, 'owner_id');
    }
}
