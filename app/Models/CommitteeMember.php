<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeMember extends Model
{
      protected $fillable = [
        'committee_id',
        'user_id',
        'role_in_committee',
    ];


      public function committee()
    {
        return $this->belongsTo(Committee::class);
    }

       public function user()
    {
        return $this->belongsTo(User::class);
    }

     public function profile()
    {
        return $this->hasOne(Profile::class);
    }

       public function fundings()
    {
        return $this->hasMany(Funding::class, 'investor_id');
    }
}
