<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    use HasFactory;

         protected $fillable = [
        'idea_id',
        'idea_owner_id',
        'committee_id',
        'investor_id',
        'meeting_id',
        'requested_amount',
        'justification',
        'report_id',
        'requirements_verified',
        'committee_notes',
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
     
      public function ideaOwner()
    {
        return $this->belongsTo(IdeaOwner::class);
    }  


     public function committee()
    {
        return $this->belongsTo(Committee::class);
    } 

      public function investor()
{
    return $this->belongsTo(CommitteeMember::class, 'investor_id');
}

       public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }  

      public function report()
    {
        return $this->belongsTo(Report::class);
    }

      public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

        public function tasks()
    {
        return $this->hasMany(Task::class);
    }

     public function gantt()
    {
        return $this->belongsTo(GanttChart::class, 'gantt_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function evaluation()
    {
        return $this->hasOne(Evaluation::class, 'funding_id');
    }


}
