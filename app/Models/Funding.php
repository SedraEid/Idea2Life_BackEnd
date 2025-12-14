<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    use HasFactory;

         protected $fillable = [
        'idea_id',
        'investor_id',
        'requested_amount',
        'justification',
        'committee_notes',
        'is_approved',
        'approved_amount',
        'payment_method',
        'transfer_date',
        'transaction_reference',
        'status',
        'gantt_id',
        'task_id'
    ];

      public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

      public function investor()
{
    return $this->belongsTo(CommitteeMember::class, 'investor_id');
}

      public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

public function gantt()
{
    return $this->belongsTo(GanttChart::class, 'gantt_id');
}

public function task()
{
    return $this->belongsTo(Task::class, 'task_id');
}



}
