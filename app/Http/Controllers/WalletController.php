<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
     public function getMyWallet(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'المستخدم غير موجود أو التوكن غير صالح.'
            ], 401);
        }
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json([
                'message' => 'لم يتم العثور على محفظة لهذا المستخدم.',
            ], 404);
        }

        return response()->json([
            'message' => 'تم العثور على المستخدم والمحفظة بنجاح.',
            'user' => $user,
            'wallet' => $wallet
        ], 200);
    }



    public function ideaOwnerTransactions(Request $request)
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'أنت لست صاحب فكرة.'
        ], 403);
    }
    $wallet = $user->wallet;
    if (!$wallet) {
        return response()->json([
            'message' => 'لا يوجد محفظة مرتبطة بهذا المستخدم.'
        ], 404);
    }

    $transactions = WalletTransaction::with([
        'sender.user:id,name,email',
        'receiver.user:id,name,email',
        'funding.idea:id,title',
    ])
    ->where(function ($q) use ($wallet) {
        $q->where('wallet_id', $wallet->id)
          ->orWhere('sender_id', $wallet->id)
          ->orWhere('receiver_id', $wallet->id);
    })
    ->orderBy('created_at', 'desc')
    ->get();

    $data = $transactions->map(function ($tx) use ($wallet) {
        return [
            'transaction_id' => $tx->id,
            'type'           => $tx->transaction_type,
            'amount'         => $tx->amount,
            'status'         => $tx->status,
            'date'           => $tx->created_at->format('Y-m-d H:i'),
            'direction'      => $tx->sender_id == $wallet->id ? 'outgoing' : 'incoming',
            'from'           => $tx->sender?->user?->name ?? '—',
            'to'             => $tx->receiver?->user?->name ?? '—',
            'payment_method' => $tx->payment_method,
            'notes'          => $tx->notes,
        ];
    });

    return response()->json([
        'wallet_id'   => $wallet->id,
        'owner_name' => $user->name,
        'balance'    => $wallet->balance,
        'transactions' => $data
    ]);
}

}


