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

     public function fundings()
    {
        return $this->hasMany(Funding::class);
    }
}
