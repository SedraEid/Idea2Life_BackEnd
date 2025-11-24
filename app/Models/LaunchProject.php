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
    ];
    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }
}
