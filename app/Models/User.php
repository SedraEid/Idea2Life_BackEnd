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
        'user_type',
    ];

    
    protected $hidden = [
        'password',
    ];


      public function ideaowner()
    {
return $this->hasOne(IdeaOwner::class);
    }


       public function committeeMember()
    {
        return $this->hasOne(CommitteeMember::class);
    }

      public function profile()
    {
        return $this->hasOne(Profile::class);
    }


}
