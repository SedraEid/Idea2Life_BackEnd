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
}
