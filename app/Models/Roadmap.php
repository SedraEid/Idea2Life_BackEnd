<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Roadmap extends Model
{
     use HasFactory;
    protected $fillable = [
        'idea_id',
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


}
