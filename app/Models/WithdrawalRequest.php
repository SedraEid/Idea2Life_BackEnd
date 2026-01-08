<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'idea_id',
        'requested_by',
        'reason',
        'status',
        'penalty_amount',
        'penalty_paid',
        'reviewed_by',
        'reviewed_at',
        'committee_notes',
    ];

    protected $casts = [
        'penalty_paid' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

  

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
