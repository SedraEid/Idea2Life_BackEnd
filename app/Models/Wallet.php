<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;
      protected $fillable = [
        'user_id',
        'user_type',
        'balance',
        'status',
    ];

     public function user()
    {
        return $this->belongsTo(User::class);
    }

       public function sentTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'sender_id');
    }
    public function receivedTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'receiver_id');
    }
}
