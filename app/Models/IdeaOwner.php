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

      public function ideas()
    {
        return $this->hasMany(Idea::class, 'owner_id');
    }

      public function roadmaps()
    {
        return $this->hasMany(Roadmap::class, 'owner_id');
    }

      public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

      public function reports()
    {
        return $this->hasMany(Report::class);
    }

       public function businessPlans()
    {
        return $this->hasMany(BusinessPlan::class);
    }

       public function fundings()
    {
        return $this->hasMany(Funding::class, 'idea_owner_id');
    }

     public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id');
    }

          public function tasks()
    {
        return $this->hasMany(Task::class, 'owner_id');
    }


}
