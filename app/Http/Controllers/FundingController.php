<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Funding;
use App\Models\Idea;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Roadmap;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FundingController extends Controller
{
public function requestFunding(Request $request, Idea $idea) // طلب تمويل من قبل صاحب الفكرة
{
    $user = $request->user();
    $ideaOwner = $idea->owner; 
    if (!$ideaOwner || $ideaOwner->id !== $user->id) {
        return response()->json(['message' => 'ليس لديك صلاحية طلب التمويل لهذه الفكرة.'], 403);
    }
    $businessPlan = $idea->businessPlan;
    if (!$businessPlan) {
        return response()->json(['message' => 'لا يمكن تقديم طلب تمويل قبل إعداد خطة العمل.'], 400);
    }
    if ($businessPlan->latest_score < 80) {
        return response()->json(['message' => 'خطة العمل لم تحقق الحد الأدنى من التقييم (80) لطلب التمويل.'], 400);
    }
    $existingFunding = Funding::where('idea_id', $idea->id)
        ->whereIn('status', ['requested', 'under_review', 'approved'])
        ->first();
    if ($existingFunding) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل جديد قبل مراجعة الطلب الحالي.',
            'existing_funding' => $existingFunding
        ], 400);
    }
    $request->validate([
        'requested_amount' => 'required|numeric|min:1',
        'justification' => 'required|string|max:1000',
    ]);
    if (!$idea->committee) {
        return response()->json(['message' => 'لا يمكن العثور على لجنة مرتبطة بهذه الفكرة.'], 400);
    }
  $investor = $idea->committee
    ->committeeMember
    ->where('role_in_committee', 'investor')
    ->first();
    if (!$investor) {
        return response()->json([
            'message' => 'لا يوجد مستثمر متاح ضمن اللجنة الحالية، سيتم مراجعة الطلب لاحقاً.',
        ], 400);
    }
    $meeting = $idea->meetings()->create([
        'meeting_date' => now()->addDays(2),
        'meeting_link' => null,
        'notes' => 'مناقشة طلب التمويل للفكرة: ' . $idea->title,
        'requested_by' => 'owner',
        'type' => 'funding_request',
    ]);
    $funding = Funding::create([
        'idea_id' => $idea->id,
        'investor_id' => $investor->id,
        'requested_amount' => $request->requested_amount,
        'justification' => $request->justification,
        'status' => 'requested',
        'meeting_id' => $meeting->id,
    ]);
    if ($idea->roadmap) {
        $stages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "التخطيط المنهجي",
            "التقييم المتقدم قبل التمويل",
            "التمويل",
            "التنفيذ والتطوير",
            "الإطلاق",
            "المتابعة بعد الإطلاق",
            "استقرار المشروع وانفصاله عن المنصة",
        ];
        $currentStageIndex = array_search("التمويل", $stages);
        $progressPercentage = (($currentStageIndex + 1) / count($stages)) * 100;
        $idea->roadmap->update([
            'current_stage' => 'التمويل',
            'stage_description' => 'تم إرسال طلب التمويل وهو الآن قيد المراجعة من قبل اللجنة.',
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => 'انتظار قرار اللجنة بخصوص التمويل',
        ]);
    }
    $idea->update(['roadmap_stage' => 'طلب التمويل قيد المراجعة']);
    return response()->json([
        'message' => 'تم تقديم طلب التمويل بنجاح، وتم إنشاء الاجتماع وسجل التمويل وتحديث خارطة الطريق.',
        'funding' => $funding,
        'meeting' => $meeting,
    ], 201);
}



public function cancelFundingRequest(Request $request, $fundingId)//الغاء طلب التمويل من قبل صاحب الفكرة 
{
    $user = $request->user();
    $funding = Funding::with('idea', 'idea.owner')->find($fundingId); 
    if (!$funding) {
        return response()->json(['message' => 'طلب التمويل غير موجود.'], 404);
    }
    $idea = $funding->idea;
    $ideaOwner = $idea->owner;
    if (!$ideaOwner || $ideaOwner->id !== $user->id) {
        return response()->json(['message' => 'ليس لديك صلاحية لإلغاء طلب التمويل لهذه الفكرة.'], 403);
    }
    if (!in_array($funding->status, ['requested', 'under_review'])) {
        return response()->json(['message' => 'لا يمكن إلغاء هذا الطلب لأنه قيد المعالجة أو تم الانتهاء منه.'], 400);
    }
    $request->validate([
        'cancellation_reason' => 'nullable|string|max:500',
    ]);
    $funding->update([
        'status' => 'cancelled',
        'committee_notes' => $request->cancellation_reason ?? 'تم الإلغاء من قبل صاحب الفكرة',
    ]);

    $meeting = $idea->meetings()->where('type', 'funding_request')->latest()->first();
    if ($meeting) {
        $meeting->update([
            'status' => 'cancelled',
            'meeting_date' => now(),
            'notes' => 'تم إلغاء طلب التمويل من قبل صاحب الفكرة.',
        ]);
    }
    if ($idea->roadmap) {
        $stages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "التخطيط المنهجي",
            "التقييم المتقدم قبل التمويل",
            "التمويل",
            "التنفيذ والتطوير",
            "الإطلاق",
            "المتابعة بعد الإطلاق",
            "استقرار المشروع وانفصاله عن المنصة",
        ];
        $currentStageIndex = array_search("التمويل", $stages);
        $progressPercentage = (($currentStageIndex + 0.8) / count($stages)) * 100;
        $currentStage = $stages[$currentStageIndex];

        $idea->roadmap->update([
            'current_stage' => $currentStage,
            'stage_description' => 'تم إلغاء طلب التمويل من قبل صاحب الفكرة.',
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => 'يمكن إعادة تقديم طلب التمويل أو الانتقال لمرحلة التطوير',
        ]);
    }

    $idea->update(['roadmap_stage' => $idea->roadmap?->current_stage ?? null]);
    $committeeMembers = CommitteeMember::where('committee_id', $idea->committee_id)
        ->where('user_id', '!=', $user->id)
        ->get();

    foreach ($committeeMembers as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => 'تم إلغاء طلب التمويل',
            'message' => 'قام صاحب الفكرة "' . $idea->title . '" بإلغاء طلب التمويل.',
            'type' => 'funding_cancelled',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => 'تم إلغاء طلب التمويل بنجاح، وتم تحديث الاجتماع وخارطة الطريق وإشعار أعضاء اللجنة.',
        'funding' => $funding,
    ]);
}




public function getCommitteeFundRequests(Request $request) // عرض طلبات التمويل للجنة
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember) {
        return response()->json([
            'message' => 'ليس لديك صلاحية الوصول إلى طلبات التمويل (أنت لست عضو لجنة).'
        ], 403);
    }
    $fundings = Funding::with([
            'idea',
            'idea.owner', 
            'gantt:id,phase_name',
            'task:id,task_name'
        ])
        ->whereHas('idea', function ($query) use ($committeeMember) {
            $query->where('committee_id', $committeeMember->committee_id);
        })
        ->orderByDesc('created_at')
        ->get();
    if ($fundings->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد طلبات تمويل حالياً لهذه اللجنة.'
        ], 200);
    }
    $fundings->transform(function ($funding) {
        return [
            'funding_id' => $funding->id,
            'status' => $funding->status,
            'requested_amount' => $funding->requested_amount,
            'created_at' => $funding->created_at,

            'idea' => [
                'id' => $funding->idea->id,
                'title' => $funding->idea->title,
                'owner' => [
                    'id' => $funding->idea->owner->id,
                    'name' => $funding->idea->owner->name,
                    'email' => $funding->idea->owner->email,
                ],
            ],

            'gantt_name' => $funding->gantt?->phase_name,
            'task_name' => $funding->task?->task_name,
        ];
    });
    return response()->json([
        'committee_id' => $committeeMember->committee_id,
        'funding_requests' => $fundings
    ], 200);
}

public function evaluateFunding(Request $request, Funding $funding)
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;
    if (
        !$committeeMember ||
        $committeeMember->committee_id != $funding->idea->committee_id
    ) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذا الطلب.'], 403);
    }
    $meeting = $funding->idea
        ->meetings()
        ->where('type', 'funding_request')
        ->latest('meeting_date')
        ->first();
    if (!$meeting || $meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن تقييم التمويل قبل وجود الاجتماع أو قبل موعده.',
            'meeting_date' => $meeting?->meeting_date?->toDateTimeString()
        ], 400);
    }
    $validated = $request->validate([
        'is_approved' => 'required|boolean',
        'approved_amount' => 'nullable|numeric|min:0',
        'committee_notes' => 'nullable|string',
    ]);
    DB::beginTransaction();
    try {
        $funding->update([
            'is_approved'     => $validated['is_approved'],
            'approved_amount' => $validated['approved_amount'] ?? $funding->requested_amount,
            'committee_notes' => $validated['committee_notes'] ?? '',
            'status'          => $validated['is_approved'] ? 'approved' : 'rejected',
        ]);

       $idea = $funding->idea;
if ($validated['is_approved']) {
    $investorUser = $funding->investor?->user;
    $ownerUser    = $idea->owner;
    $investorWallet = $investorUser?->wallet;
    $ownerWallet    = $ownerUser?->wallet;

    if (!$investorWallet || !$ownerWallet) {
        DB::rollBack();
        return response()->json([
            'message' => 'محفظة المستثمر أو صاحب الفكرة غير موجودة.'
        ], 404);
    }

    $amount = $funding->approved_amount;
    if ($investorWallet->balance < $amount) {
        DB::rollBack();
        return response()->json([
            'message' => 'رصيد المستثمر غير كافٍ لإجراء التحويل.'
        ], 400);
    }
    $investorWallet->decrement('balance', $amount);
    $ownerWallet->increment('balance', $amount);
    WalletTransaction::create([
        'wallet_id'        => $ownerWallet->id,          
        'funding_id'       => $funding->id,
        'sender_id'        => $investorWallet->id,
        'receiver_id'      => $ownerWallet->id,

        'transaction_type' => 'transfer',
        'amount'           => $amount,
        'percentage'       => null,
        'beneficiary_role' => 'creator',
        'status'           => 'completed',
        'payment_method'   => 'wallet',
        'notes'            => 'تم تحويل مبلغ التمويل من المستثمر إلى صاحب الفكرة.',
    ]);

    $funding->update([
        'transfer_date'         => now(),
        'transaction_reference'=> 'TX-' . uniqid(),
        'payment_method'        => 'wallet',
    ]);
}
 $roadmapStages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "التخطيط المنهجي",
            "التقييم المتقدم قبل التمويل",
            "التمويل",
            "التنفيذ والتطوير",
            "الإطلاق",
            "المتابعة بعد الإطلاق",
            "استقرار المشروع وانفصاله عن المنصة",
        ];

        $currentStageIndex = array_search("التمويل", $roadmapStages);
        if ($validated['is_approved']) {
            $stageDescription = "تمت الموافقة على التمويل والمبلغ المحدد: " . $funding->approved_amount;
            $nextStep = $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة';
            $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
        } else {
            $stageDescription = "تم رفض طلب التمويل؛ يمكن إعادة تقديم الطلب بعد تعديل الخطة.";
            $nextStep = "إعادة تقديم طلب التمويل";
            $progressPercentage = (($currentStageIndex + 0.2) / count($roadmapStages)) * 100;
        }
        if ($idea->roadmap) {
            $idea->roadmap->update([
                'current_stage'       => "التمويل",
                'stage_description'   => $stageDescription,
                'progress_percentage' => $progressPercentage,
                'last_update'         => now(),
                'next_step'           => $nextStep,
            ]);
        }
        $idea->update(['roadmap_stage' => "التمويل"]);
        Notification::create([
            'user_id' => $idea->owner?->id,
            'title'   => 'تقييم طلب التمويل',
            'message' => 'تم تقييم طلب التمويل لفكرتك "' . $idea->title . '". الحالة: ' .
                ($validated['is_approved'] ? 'مقبول' : 'مرفوض'),
            'type'    => 'funding_evaluation',
            'is_read' => false,
        ]);
        DB::commit();
        return response()->json([
            'message' => 'تم تقييم طلب التمويل وتحديث الحالة والخارطة وتحويل المبلغ تلقائيًا إذا تمت الموافقة.',
            'funding' => $funding,
            'roadmap' => $idea->roadmap,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'حدث خطأ أثناء تقييم التمويل.',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

public function showFundingForIdea(Request $request, $idea_id)
{
    $user = $request->user(); 
    $idea = Idea::where('id', $idea_id)
        ->whereHas('owner', function ($q) use ($user) {
            $q->where('id', $user->id);
        })
        ->first();
    if (!$idea) {
        return response()->json([
            'message' => 'هذه الفكرة غير موجودة أو لا تنتمي لك.',
        ], 404);
    }
    $fundings = Funding::with([
        'idea:id,title,description,initial_evaluation_score,committee_id',
        'idea.committee:id,committee_name',
        'investor.user:id,name,email',
          'walletTransactions.sender.user:id,name,email',
    'walletTransactions.receiver.user:id,name,email', 
    ])
    ->where('idea_id', $idea_id)
    ->get();

    if ($fundings->isEmpty()) {
        return response()->json([
            'message' => 'لا يوجد طلبات تمويل لهذه الفكرة.',
        ], 404);
    }

    $response = $fundings->map(function ($funding) {
        $meeting = $funding->idea
            ->meetings()
            ->where('type', 'funding_request')
            ->latest('meeting_date')
            ->first();

        return [
            'funding_id' => $funding->id,
            'status' => $funding->status,
            'requested_amount' => $funding->requested_amount,
            'approved_amount' => $funding->approved_amount,
            'payment_method' => $funding->payment_method,
            'transfer_date' => $funding->transfer_date,
            'transaction_reference' => $funding->transaction_reference,
            'committee_notes' => $funding->committee_notes,

            'idea' => [
                'id' => $funding->idea->id ?? null,
                'title' => $funding->idea->title ?? null,
                'description' => $funding->idea->description ?? null,
                'initial_evaluation_score' => $funding->idea->initial_evaluation_score ?? null,
            ],

            'committee' => [
                'id' => $funding->idea->committee->id ?? null,
                'name' => $funding->idea->committee->committee_name ?? null,
            ],

            'investor' => [
                'id' => $funding->investor->id ?? null,
                'name' => $funding->investor->user->name ?? null,
                'email' => $funding->investor->user->email ?? null,
            ],

            'meeting' => [
                'id' => $meeting?->id,
                'meeting_date' => $meeting?->meeting_date,
                'notes' => $meeting?->notes ?? 'رابط الاجتماع سيُحدد لاحقًا من قبل اللجنة',
            ],

            'wallet_transactions' => $funding->walletTransactions->map(function ($tx) {
                return [
                    'transaction_id' => $tx->id,
                    'transaction_type' => $tx->transaction_type,
                    'amount' => $tx->amount,
                    'status' => $tx->status,
                    'payment_method' => $tx->payment_method,
                    'notes' => $tx->notes,
                  'sender' => [
    'wallet_id' => $tx->sender->id ?? null,
    'user_id' => $tx->sender?->user_id ?? null,
    'name' => $tx->sender?->user?->name ?? null,
    'email' => $tx->sender?->user?->email ?? null,
],
'receiver' => [
    'wallet_id' => $tx->receiver->id ?? null,
    'user_id' => $tx->receiver?->user_id ?? null,
    'name' => $tx->receiver?->user?->name ?? null,
    'email' => $tx->receiver?->user?->email ?? null,
],

                    'created_at' => $tx->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ];
    });

    return response()->json([
        'idea_id' => $idea_id,
        'idea_title' => $idea->title,
        'fundings' => $response,
    ]);
}


  public function showCommitteeFundingChecks(Request $request)
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember) {
        return response()->json(['message' => 'أنت غير مرتبط بأي لجنة.'], 403);
    }

    $fundings = Funding::with([
        'idea:id,title,description,committee_id,owner_id',
        'idea.owner:id,name,email',
        'idea.committee:id,committee_name',
        'investor.user:id,name,email',
        'walletTransactions.sender.user:id,name,email',  
        'walletTransactions.receiver.user:id,name,email',
    ])
    ->whereHas('idea', function ($q) use ($committeeMember) {
        $q->where('committee_id', $committeeMember->committee_id);
    })
    ->get();

    $checks = [];
    foreach ($fundings as $funding) {
        foreach ($funding->walletTransactions as $tx) {
            $checks[] = [
                'check_id' => $tx->id,
                'from' => $tx->sender?->user?->name ?? 'غير معروف',
                'to' => $tx->receiver?->user?->name ?? 'غير معروف',
                'amount' => $tx->amount,
                'date' => $tx->created_at->format('Y-m-d'),
                'payment_method' => $tx->payment_method ?? 'غير محدد',
                'notes' => $tx->notes ?? '',
                'funding_id' => $funding->id,
                'idea_title' => $funding->idea->title ?? '',
                'investor' => $funding->investor?->user?->name ?? '',
                'idea_owner' => $funding->idea->owner->name ?? '',
            ];
        }
    }

    return response()->json([
        'committee_id' => $committeeMember->committee_id,
        'committee_name' => $committeeMember->committee->committee_name ?? '',
        'checks' => $checks,
    ]);
}



}
