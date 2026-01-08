<?php

namespace App\Jobs;

use App\Models\Idea;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DistributeProfitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ideaId) {}

    public function handle(): void
    {
        DB::transaction(function () {

            $idea = Idea::with('profitDistributions')->findOrFail($this->ideaId);

            $ownerWallet = Wallet::where('user_id', $idea->owner_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($idea->profitDistributions as $distribution) {
                if ($distribution->user_id === $idea->owner_id) {
                    continue;
                }

                $receiverWallet = Wallet::where('user_id', $distribution->user_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($ownerWallet->balance < $distribution->amount) {
                    throw new \Exception('رصيد صاحب الفكرة غير كافٍ لتحويل الأرباح');
                }

                $ownerWallet->decrement('balance', $distribution->amount);

                $receiverWallet->increment('balance', $distribution->amount);
                $beneficiaryRole = match ($distribution->user_role) {
    'idea_owner' => 'creator',
    'investor'   => 'investor',
    'admin'      => 'admin',
    'committee_member' => str_replace(
        'دوره باللجنة: ',
        '',
        $distribution->notes
    ),
    default => null,
};


                WalletTransaction::create([
                    'wallet_id'        => $ownerWallet->id,
                    'sender_id'        => $ownerWallet->id,
                    'receiver_id'      => $receiverWallet->id,
                    'transaction_type' => 'distribution',
                    'amount'           => $distribution->amount,
                    'percentage'       => $distribution->percentage,
'beneficiary_role' => $beneficiaryRole,
        'payment_method'   => 'wallet',

                    'status'           => 'completed',
                    'notes'            => $distribution->notes 
                        ?? 'تحويل أرباح مشروع رقم ' . $idea->id,
                ]);
            }
        });
    }
}
