<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Content;
use App\Models\User;
use App\Models\Committee;
use App\Models\Idea;
use App\Models\WalletTransaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\WithdrawalRequest;
use App\Models\Meeting;



class ContentController extends Controller
{
 
public function store(Request $request)
{
    $validated = $request->validate([
        'type'  => 'required|string|in:testimonial,feature,slide',
        'title' => 'nullable|string|max:255',
        'text'  => 'nullable|string|max:2000',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
    ]);

    $content = new Content();
    $content->type  = $validated['type'];
    $content->title = $validated['title'] ?? null;
    $content->text  = $validated['text'] ?? null;

    if ($request->hasFile('image')) {
        $image = $request->file('image');
        $filename = uniqid('content_') . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs(
            'contents',
            $filename,
            'public'
        );

        $content->image = 'storage/' . $path;
    }

    $content->save();

    return response()->json([
        'message' => 'تم إنشاء المحتوى بنجاح',
        'data' => $content
    ], 201);
}



//تابع العرض
     public function index(Request $request)
    {
        $type = $request->query('type'); 
        if ($type) {
            $contents = Content::where('type', $type)->get();
        } else {
            $contents = Content::all();
        }

        return response()->json($contents, 200);
    }
//تابع الحذف
     public function destroy($id)
    {
        $content = Content::findOrFail($id);
        if ($content->image && file_exists(storage_path('app/public/' . $content->image))) {
            unlink(storage_path('app/public/' . $content->image));
        }
        $content->delete();
        return response()->json(['message' => 'Content deleted'], 200);
    }

 //////////////////////Admin_dashboard
/////تابع يعطي اجمالي عدد الافكار والمستخدمين للادمن
public function getStats()
{
    $totalIdeas = Idea::count();

    $approvedIdeas = Idea::where('status', 'approved')->count();

    $totalUsers = User::count();

    $recentIdeas = Idea::with('owner')
        ->latest()
        ->take(5)
        ->get()
        ->map(function ($idea) {
            return [
                'title' => $idea->title,
                'owner_name' => $idea->owner?->name ?? '—',
                'status' => $idea->status,
                'created_at' => $idea->created_at?->format('Y-m-d H:i'),
            ];
        });

    $recentUsers = User::latest()
        ->take(5)
        ->get(['name', 'email', 'created_at'])
        ->map(function ($user) {
            return [
                'name' => $user->name,
                'email' => $user->email,
                'joined_at' => $user->created_at?->format('Y-m-d H:i'),
            ];
        });

    return response()->json([
        'total_ideas'     => $totalIdeas,
        'approved_ideas' => $approvedIdeas,
        'total_users'    => $totalUsers,
        'recent_ideas'   => $recentIdeas,
        'recent_users'   => $recentUsers,
    ]);
}

//تابع يعرض الافكار للادمن 
public function ideasWithProfitsForAdmin()
{
    $ideas = Idea::with([
        'owner:id,name',
        'committee',
        'fundings.investor.user:id,name',
        'profitDistributions.user:id,name'
    ])->get();

    $ideas = $ideas->map(function($idea) {
        $isDistributed = $idea->postLaunchFollowups()
            ->where('profit_distributed', true)
            ->exists();
        $distributions = [];
        if ($isDistributed) {
            $distributions = $idea->profitDistributions
                ->map(function ($distribution) {
                    return [
                        'user_name' => $distribution->user?->name ?? 'غير معروف',
                        'role'      => $distribution->user_role,
                        'percentage'=> $distribution->percentage . '%',
                        'amount'    => $distribution->amount,
                    ];
                });
        }
        return [
            'idea_id'           => $idea->id,
            'title'             => $idea->title,
            'owner_name'        => $idea->owner?->name,
            'committee_name'    => $idea->committee?->committee_name,
            'profit_distributed'=> $isDistributed,
            'distributions'     => $distributions,
            'fundings'          => $idea->fundings->map(function($f) {
                return [
                    'investor_name' => $f->investor->user?->name,
                    'amount'        => $f->amount,
                ];
            }),
        ];
    });

    return response()->json($ideas);
}

//تابع يعرض كل المستخدمين 

public function allUsers()
{
    $users = User::with(['ideas', 'committeeMember'])
        ->where('role', '!=', 'admin')
        ->get();

    $usersWithRoles = $users->map(function ($user) {
        $roles = [];

        if ($user->ideas->isNotEmpty()) {
            $roles[] = 'Idea Owner';
        }

        if ($user->committeeMember) {
            $roles[] = 'Committee Member';
        }

        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $roles,
        ];
    });

    return response()->json($usersWithRoles);
}



// جلب جميع اللجان مع الأفكار التابعة لها

public function allCommittees()
{
    $committees = Committee::with([
        'ideas:id,committee_id,title',
        'committeeMember.user:id,name,email,role'
    ])
    ->select('id', 'committee_name', 'description', 'status')
    ->get();

    return response()->json($committees);
}

//تابع يعرض اصحاب الافكار مع معلوماتهن
public function allIdeaOwners()
{
    $owners = User::where('role', 'idea_owner')
        ->with([
            'ideas:id,owner_id,title',
            'wallet'
        ])
        ->get();

    $owners = $owners->map(function ($user) {
        return [
            'id' => $user->id,
            'user_id' => $user->id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'wallet' => $user->wallet ? [
                'id' => $user->wallet->id,
                'user_id' => $user->wallet->user_id,
                'user_type' => $user->wallet->user_type,
                'balance' => $user->wallet->balance,
                'status' => $user->wallet->status,
                'created_at' => $user->wallet->created_at,
                'updated_at' => $user->wallet->updated_at,
            ] : null,
            'ideas' => $user->ideas->map(function ($idea) {
                return [
                    'id' => $idea->id,
                    'owner_id' => $idea->owner_id,
                    'title' => $idea->title,
                ];
            }),
        ];
    });

    return response()->json([
        'data' => $owners
    ]);
}

  
public function getAllTransactions()
{
    $transactions = WalletTransaction::with([
        'funding.idea.owner:id,name',
        'funding.investor.user:id,name',
    ])
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($tx) {

        $senderName = null;
        if ($tx->sender_id) {
            $senderWallet = Wallet::find($tx->sender_id);
            $senderUser = $senderWallet
                ? User::find($senderWallet->user_id)
                : null;
            $senderName = $senderUser?->name;
        } else {
            $senderName = 'Admin';
        }

        $receiverName = null;
        if ($tx->receiver_id) {
            $receiverWallet = Wallet::find($tx->receiver_id);
            $receiverUser = $receiverWallet
                ? User::find($receiverWallet->user_id)
                : null;
            $receiverName = $receiverUser?->name;
        }

        return [
            'transaction_id' => $tx->id,
            'from' => $senderName,
            'to'   => $receiverName ?? 'غير معروف',
            'amount' => $tx->amount,
            'transaction_type' => $tx->transaction_type ?? 'N/A',
            'status' => $tx->status ?? 'N/A',
            'payment_method' => $tx->payment_method ?? 'N/A',
            'notes' => $tx->notes ?? '-',
            'date' => $tx->created_at?->format('Y-m-d H:i:s'),
            'funding_id' => $tx->funding_id,
            'idea_title' => $tx->funding?->idea?->title,
            'investor' => $tx->funding?->investor?->user?->name,
            'idea_owner' => $tx->funding?->idea?->owner?->name,
        ];
    });

    return response()->json([
        'transactions' => $transactions,
    ]);
}

//شحن المحافظ من خلال الادمن 
public function chargeUserWallet(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'amount'  => 'required|numeric|min:1',
    ]);

    DB::beginTransaction();

    try {
        $user = User::findOrFail($request->user_id);
        $wallet = $user->wallet;
        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id'   => $user->id,
                'user_type' => $user->role, 
                'balance'   => 0,
                'status'    => 'active',
            ]);
        }
        $wallet->balance += $request->amount;
        $wallet->save();
        WalletTransaction::create([
            'wallet_id'        => $wallet->id,
            'receiver_id'      => $wallet->id,
            'sender_id'        => null, 
            'transaction_type' => 'transfer',
            'amount'           => $request->amount,
            'status'           => 'completed',
            'payment_method'   => 'manual',
            'notes'            => 'شحن من الأدمن',
        ]);

        DB::commit();

        return response()->json([
            'message' => 'تم شحن المحفظة بنجاح',
            'user_id' => $user->id,
            'role'    => $user->role,
            'balance' => $wallet->balance,
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'فشل شحن المحفظة',
            'error'   => $e->getMessage(),
        ], 500);
    }
}



//عرض الاشعارات للادمن 
public function getAdminNotifications()
{
     $notifications = Notification::where('user_id', 1)
        ->orderByDesc('created_at')
        ->get();
    Notification::where('user_id', 1)
        ->where('is_read', false)
        ->update(['is_read' => true]);
    return response()->json([
        'notifications' => $notifications
    ]);
}

//تابع يعرض المشاريع المنسحبة للادمن 
public function adminWithdrawnIdeas()
{
    $withdrawals = WithdrawalRequest::with([
        'idea:id,title,owner_id',
        'idea.owner:id,name',
        'requester:id,name',
        'reviewer:id,name'
    ])
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($w) {
        return [
            'withdrawal_id'   => $w->id,
            'idea_id'         => $w->idea_id,
            'idea_title'      => $w->idea?->title,
            'owner_name'      => $w->idea?->owner?->name,
            'requested_by'    => $w->requester?->name,
            'reason'          => $w->reason,
            'status'          => $w->status,
            'penalty_amount'  => $w->penalty_amount,
            'penalty_paid'    => $w->penalty_paid,
            'reviewed_by'     => $w->reviewer?->name,
            'reviewed_at'     => $w->reviewed_at?->format('Y-m-d H:i:s'),
            'committee_notes' => $w->committee_notes,
            'created_at'      => $w->created_at?->format('Y-m-d H:i:s'),
        ];
    });

    return response()->json([
        'withdrawals' => $withdrawals
    ]);
}


//عمل اجتماع بين اللجنة من اجل تحديد من الذي سو يتولى عملية الادخال 
public function createCommitteeMeeting(Request $request, Idea $idea)
{
    if (!$idea->committee) {
        return response()->json([
            'message' => 'لا توجد لجنة مرتبطة بهذه الفكرة.'
        ], 422);
    }
    $data = $request->validate([
        'meeting_date' => 'required|date',
        'meeting_link' => 'nullable|string',
    ]);

    $meeting = Meeting::create([
        'idea_id'      => $idea->id,
        'meeting_date' => $data['meeting_date'],
        'meeting_link' => $data['meeting_link'] ?? null,
        'requested_by' => 'committee',
        'type'         => 'assign_data_entry',
        'notes'        => 'اجتماع لجنة لتحديد مسؤول إدخال البيانات (منظم من الإدارة)',
    ]);

    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => 'اجتماع لجنة من الإدارة',
            'message' => "قامت الإدارة بتحديد اجتماع لجنة للفكرة '{$idea->title}' لتحديد مسؤول إدخال البيانات.",
            'type'    => 'assign_data_entry',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => 'تم إنشاء اجتماع اللجنة بنجاح.',
        'meeting' => $meeting
    ], 201);
}



}



