<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\Notification;
use App\Models\Roadmap;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalRequestController extends Controller
{
    //عرض طلبات الانسحاب للجنة 
    public function committeeWithdrawalRequests(Request $request)
{
    $user = $request->user();
    if ($user->role !== 'committee_member') {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }
    $ideaIds = $user->committeeMember->committee->ideas()->pluck('id');
    $requests = WithdrawalRequest::whereIn('idea_id', $ideaIds)
        ->with(['idea:id,title,owner_id',   'requester:id,name,email' ])
        ->orderBy('created_at', 'desc')
        ->get();
    return response()->json([
        'total_requests' => $requests->count(),
        'requests' => $requests
    ]);
}

//طلب الانسحاب من قبل صاحب الفكرة 
public function ownerRequestWithdrawal(Request $request, Idea $idea)
{
    $user = $request->user();
    if ($idea->owner_id !== $user->id) {
        return response()->json([
            'message' => 'غير مصرح لك بتقديم طلب انسحاب لهذه الفكرة.'
        ], 403);
    }
    $existingRequest = WithdrawalRequest::where('idea_id', $idea->id)
        ->where('status', 'pending')
        ->first();

    if ($existingRequest) {
        return response()->json([
            'message' => 'يوجد طلب انسحاب قيد الانتظار بالفعل لهذه الفكرة.'
        ], 422);
    }
    $withdrawalRequest = WithdrawalRequest::create([
        'idea_id' => $idea->id,
        'user_id' => $user->id,
        'requested_by' => $user->id,
        'status'  => 'pending',
        'reason'  => $request->input('reason', null),
    ]);
    if ($idea->committee) {
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => "طلب انسحاب من صاحب الفكرة",
                'message' => "قام صاحب الفكرة '{$user->name}' بتقديم طلب انسحاب للفكرة '{$idea->title}'. يرجى مراجعة الطلب.",
                'type' => 'withdrawal_request',
                'is_read' => false,
            ]);
        }
    }

    return response()->json([
        'message' => 'تم تقديم طلب الانسحاب بنجاح، في انتظار موافقة اللجنة.',
        'withdrawal_request' => $withdrawalRequest
    ], 201);
}


//قرار اللجنة بشان طلب الانسحاب 
public function reviewWithdrawal(Request $request, $withdrawalId)
{
    $user = $request->user();
    if ($user->role !== 'committee_member') {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    $withdrawal = WithdrawalRequest::with(['idea.owner'])->findOrFail($withdrawalId);
    if ($withdrawal->status !== 'pending') {
        return response()->json(['message' => 'تم الرد على هذا الطلب مسبقاً.'], 422);
    }
    $committeeId = $user->committeeMember->committee_id;
    if ($withdrawal->idea->committee_id !== $committeeId) {
        return response()->json(['message' => 'ليس لديك صلاحية الرد على هذا الطلب.'], 403);
    }
    $data = $request->validate([
        'status' => 'required|in:approved,rejected',
        'penalty_amount' => 'nullable|numeric|min:0',
        'committee_notes' => 'nullable|string',
    ]);

    $withdrawal->update([
        'status' => $data['status'],
        'penalty_amount' => $data['penalty_amount'] ?? 0,
        'committee_notes' => $data['committee_notes'] ?? null,
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
    ]);
    Notification::create([
        'user_id' => $withdrawal->idea->owner->id,
        'title' => 'نتيجة طلب الانسحاب',
        'message' => "تمت مراجعة طلب الانسحاب الخاص بفكرتك '{$withdrawal->idea->title}'. " .
                     "الحالة: {$withdrawal->status}. " .
                     "الغرامة المالية: {$withdrawal->penalty_amount}",
        'type' => 'withdrawal_review',
        'is_read' => false,
    ]);

    return response()->json([
        'message' => 'تم تحديث حالة طلب الانسحاب بنجاح.',
        'withdrawal' => $withdrawal,
    ]);
}


//عرض قرار اللجنة بشان الانسحاب لصاحب الفكرة 
public function ownerAllWithdrawals(Request $request)
{
    $user = $request->user();

    if ($user->role !== 'idea_owner') {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }
    $withdrawals = WithdrawalRequest::with(['idea', 'reviewer'])
        ->where('requested_by', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

    if ($withdrawals->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد طلبات انسحاب سابقة.',
            'total_requests' => 0,
            'data' => [],
        ], 200);
    }
    $data = $withdrawals->map(function ($withdrawal) {
        return [
            'request' => [
                'id'         => $withdrawal->id,
                'idea_id'    => $withdrawal->idea_id,
                'idea_title' => $withdrawal->idea->title,
                'reason'     => $withdrawal->reason,
                'created_at' => $withdrawal->created_at->format('Y-m-d H:i'),
            ],
            'committee_response' => [
                'status'          => $withdrawal->status,
                'penalty_amount'  => $withdrawal->penalty_amount,
                'penalty_paid'    => $withdrawal->penalty_paid,
                'committee_notes' => $withdrawal->committee_notes,
                'reviewed_by'     => $withdrawal->reviewer?->name ?? null,
                'reviewed_at'     => $withdrawal->reviewed_at?->format('Y-m-d H:i') ?? null,
            ]
        ];
    });

    return response()->json([
        'total_requests' => $withdrawals->count(),
        'withdrawals'    => $data,
    ]);
}




//دفع الغرامة المالية من اجل الانسحاب من قبل صاحب الفكرة 


public function executeWithdrawal(Request $request, WithdrawalRequest $withdrawal)
{
    $user = $request->user();

    if ($withdrawal->requested_by !== $user->id) {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    if ($withdrawal->status !== 'approved') {
        return response()->json(['message' => 'طلب الانسحاب غير موافق عليه.'], 422);
    }
    if ($withdrawal->penalty_paid) {
        return response()->json(['message' => 'تم تنفيذ الانسحاب مسبقاً.'], 422);
    }

    try {
        DB::transaction(function () use ($withdrawal) {

            $idea = $withdrawal->idea;
            $committee = $idea->committee;

            $penalty = $withdrawal->penalty_amount;

            $investorRate  = 0.50;
            $committeeRate = 0.30;
            $platformRate  = 0.20;

            $ownerWallet = Wallet::where('user_id', $withdrawal->requested_by)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ownerWallet->balance < $penalty) {
                throw new \Exception('رصيد غير كافٍ لدفع الغرامة');
            }

            $investorMember = $committee->committeeMember()
                ->where('role_in_committee', 'investor')
                ->firstOrFail();

            $investorWallet = Wallet::where('user_id', $investorMember->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $platformWallet = Wallet::where('user_type', 'admin')
                ->lockForUpdate()
                ->firstOrFail();

            $ownerWallet->decrement('balance', $penalty);

            $investorAmount = $penalty * $investorRate;
            $investorWallet->increment('balance', $investorAmount);

            WalletTransaction::create([
                'wallet_id'        => $ownerWallet->id,
                'sender_id'        => $ownerWallet->id,
                'receiver_id'      => $investorWallet->id,
                'transaction_type' => 'withdrawal',
                'amount'           => $investorAmount,
                'beneficiary_role' => 'investor',
                'payment_method'   => 'wallet',
                'status'           => 'completed',
                'notes'            => 'حصة المستثمر من غرامة انسحاب الفكرة',
            ]);

            $committeeMembers = $committee->committeeMember()
                ->where('role_in_committee', '!=', 'investor')
                ->get();

            if ($committeeMembers->count() > 0) {
                $committeeTotal = $penalty * $committeeRate;
                $perMember = $committeeTotal / $committeeMembers->count();

                foreach ($committeeMembers as $member) {
                    $memberWallet = Wallet::where('user_id', $member->user_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $memberWallet->increment('balance', $perMember);

                    WalletTransaction::create([
                        'wallet_id'        => $ownerWallet->id,
                        'sender_id'        => $ownerWallet->id,
                        'receiver_id'      => $memberWallet->id,
                        'transaction_type' => 'withdrawal',
                        'amount'           => $perMember,
                        'beneficiary_role' => $member->role_in_committee,
                        'payment_method'   => 'wallet',
                        'status'           => 'completed',
                        'notes'            => 'حصة عضو لجنة من غرامة انسحاب الفكرة',
                    ]);
                }
            }

            $platformAmount = $penalty * $platformRate;
            $platformWallet->increment('balance', $platformAmount);

            WalletTransaction::create([
                'wallet_id'        => $ownerWallet->id,
                'sender_id'        => $ownerWallet->id,
                'receiver_id'      => $platformWallet->id,
                'transaction_type' => 'withdrawal',
                'amount'           => $platformAmount,
                'beneficiary_role' => 'admin',
                'payment_method'   => 'wallet',
                'status'           => 'completed',
                'notes'            => 'حصة المنصة من غرامة انسحاب الفكرة',
            ]);

            $withdrawal->update([
                'penalty_paid' => true,
                'status'       => 'approved',
                'executed_at'  => now(),
            ]);

            $idea->update([
                'status'        => 'withdrawn',
                'roadmap_stage' => 'withdrawn',
                'withdrawn'     => true,
            ]);

            Roadmap::where('idea_id', $idea->id)->update([
                'current_stage'     => 'withdrawn',
                'stage_description' => 'تم الانسحاب بعد دفع الغرامة وتوزيعها',
                'last_update'       => now(),
            ]);
        });

        return response()->json([
            'message' => 'تم تنفيذ الانسحاب وتوزيع الغرامة بنجاح'
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'فشل تنفيذ الانسحاب',
            'error'   => $e->getMessage()
        ], 500);
    }
}



}
