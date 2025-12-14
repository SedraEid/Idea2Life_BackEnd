<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;
     protected $fillable = [
        'idea_id',
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

     public function report()
{
    return $this->hasOne(Report::class);
}




}
