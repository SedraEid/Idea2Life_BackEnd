<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessPlan extends Model
{
    use HasFactory;
     protected $fillable = [
        'idea_id',
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

}
