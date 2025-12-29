<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

     protected $fillable = [
        'wallet_id',
        'funding_id',
        'sender_id',
        'receiver_id',
        'transaction_type',
        'amount',
        'percentage',
        'beneficiary_role',
        'status',
        'payment_method',
        'notes',
    ];

      public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

     public function funding()
    {
        return $this->belongsTo(Funding::class);
    }

  public function sender()
{
    return $this->belongsTo(Wallet::class, 'sender_id');
}

public function receiver()
{
    return $this->belongsTo(Wallet::class, 'receiver_id');
}



    


}
