<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaOwner extends Model
{
     protected $fillable = [
        'user_id',
    ];

      public function user()
    {
        return $this->belongsTo(User::class);
    }


     public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
