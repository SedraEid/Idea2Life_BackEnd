<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
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
}
