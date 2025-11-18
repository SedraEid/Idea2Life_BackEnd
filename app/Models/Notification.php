<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
       protected $fillable = [
        'user_id',
        'idea_id',
        'meeting_id',
        'report_id',
        'title',
        'message',
        'type',
        'is_read',
    ];

      public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }


    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
