<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaunchProject extends Model
{
    use HasFactory;
  protected $fillable = [
        'idea_id',
        'status',
        'launch_date',
        'launch_version',
        'followup_status',
        'profit_allowed',
        'stabilized_at',
    ];

    
    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }
public function followUps()
{
    return $this->hasMany(PostLaunchFollowUp::class, 'launch_project_id');
}


}
