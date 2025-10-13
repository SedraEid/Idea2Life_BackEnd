<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model

{
    protected $primaryKey = 'profile_id'; 
     protected $fillable = [
        'idea_owner_id',
        'user_id',
        'phone',
        'profile_image',
        'bio',
        'user_type',
        'committee_role',
        'roadmap_stage',
        'committee_member_id',
    ];

      public function user()
    {
        return $this->belongsTo(User::class);
    }

  
     
    public function ideaOwner()
    {
        return $this->belongsTo(IdeaOwner::class);
    }

   
    public function committeeMember()
    {
        return $this->belongsTo(CommitteeMember::class);
    }



}
