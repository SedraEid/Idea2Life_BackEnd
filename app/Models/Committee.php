<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
     protected $fillable = [
        'committee_name',
        'description',
        'status',
    ];


     public function committeeMember()
    {
        return $this->hasMany(CommitteeMember::class);
    }

       public function ideas()
    {
        return $this->hasMany(Idea::class, 'committee_id');
    }

      public function roadmaps()
    {
        return $this->hasMany(Roadmap::class);
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

     public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

     public function fundings()
    {
        return $this->hasMany(Funding::class);
    }
}
