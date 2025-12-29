<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
     use HasApiTokens, /* HasFactory, */ Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
     protected $fillable = [
        'name',
        'email',
        'password',
        'role',             
        'phone',            
        'profile_image',    
        'bio',           
        'committee_role',  
    ];

    
    protected $hidden = [
        'password',
    ];


public function ideas()
{
    return $this->hasMany(Idea::class, 'owner_id');
}
       public function committeeMember()
    {
        return $this->hasOne(CommitteeMember::class);
    }
    
     public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

        public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    
        public function followUps()
    {
        return $this->hasMany(PostLaunchFollowUp::class, 'recorded_by');
    }

       public function reviewedFollowUps()
    {
        return $this->hasMany(PostLaunchFollowUp::class, 'reviewed_by');
    }

}
