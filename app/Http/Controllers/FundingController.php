<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Funding;
use App\Models\Idea;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FundingController extends Controller
{

public function requestFunding(Request $request, Idea $idea)//طلب تمويل من قبل صاحب الفكرة
{
    $user = $request->user();
    $ideaOwner = $idea->ideaOwner;

    if (!$ideaOwner || $ideaOwner->user_id !== $user->id) {
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
        ->where('idea_owner_id', $ideaOwner->id)
        ->whereIn('status', ['requested', 'under_review'])
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
        ->committeeMember()
        ->where('role_in_committee', 'investor')
        ->first();

    if (!$investor) {
        return response()->json([
            'message' => 'لا يوجد مستثمر متاح ضمن اللجنة الحالية، سيتم مراجعة الطلب لاحقاً.',
        ], 400);
    }

    $meeting = $idea->meetings()->create([
        'owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'meeting_date' => now()->addDays(2),
        'meeting_link' => null,
        'notes' => 'مناقشة طلب التمويل للفكرة: ' . $idea->title,
        'requested_by' => 'owner',
        'type' => 'funding_request',
    ]);

    $report = $idea->reports()->create([
        'committee_id' => $idea->committee_id,
        'roadmap_id' => $idea->roadmap?->id,
        'meeting_id' => $meeting->id,
        'description' => 'تقرير أولي حول طلب التمويل للفكرة.',
        'report_type' => 'funding',
        'status' => 'pending',
    ]);

    $funding = Funding::create([
        'idea_id' => $idea->id,
        'idea_owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'investor_id' => $investor->id,
        'meeting_id' => $meeting->id,
        'requested_amount' => $request->requested_amount,
        'justification' => $request->justification,
        'status' => 'requested',
        'report_id' => $report->id,
    ]);

    Evaluation::create([
        'idea_id' => $idea->id,
        'funding_id' => $funding->id,
        'committee_id' => $idea->committee_id,
        'evaluation_type' => 'funding',
        'business_plan_id' => $businessPlan->id,
        'score' => 0,
        'recommendation' => 'قيد الدراسة',
        'comments' => 'لم تتم إضافة ملاحظات بعد.',
        'strengths' => 'سيتم تحديد نقاط القوة بعد التقييم.',
        'weaknesses' => 'سيتم تحديد نقاط الضعف بعد التقييم.',
        'financial_analysis' => 'التحليل المالي قيد الإعداد.',
        'risks' => 'سيتم تحديد المخاطر المحتملة بعد التقييم.',
        'status' => 'pending',
    ]);

    if ($idea->roadmap) {
        $stages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "الاجتماع التوجيهي",
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
        'message' => 'تم تقديم طلب التمويل بنجاح، وتم إنشاء الاجتماع والتقرير وسجل التقييم وتحديث خارطة الطريق.',
        'funding' => $funding,
        'meeting' => $meeting,
        'report' => $report,
    ], 201);
}




public function cancelFundingRequest(Request $request, Idea $idea) // إلغاء طلب التمويل من قبل صاحب الفكرة
{
    $user = $request->user();
    $ideaOwner = $idea->ideaOwner;

    if (!$ideaOwner || $ideaOwner->user_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لإلغاء طلب التمويل لهذه الفكرة.'
        ], 403);
    }

    $funding = Funding::where('idea_id', $idea->id)
        ->where('idea_owner_id', $ideaOwner->id)
        ->whereIn('status', ['requested', 'under_review'])
        ->first();

    if (!$funding) {
        return response()->json([
            'message' => 'لا يوجد طلب تمويل نشط يمكن إلغاؤه.'
        ], 400);
    }

    $request->validate([
        'cancellation_reason' => 'nullable|string|max:500',
    ]);

    $funding->update([
        'status' => 'cancelled',
        'committee_notes' => $request->cancellation_reason ?? 'تم الإلغاء من قبل صاحب الفكرة',
    ]);

    if ($funding->report) {
        $funding->report->update([
            'status' => 'cancelled',
            'description' => 'تم إلغاء طلب التمويل من قبل صاحب الفكرة. ' . ($request->cancellation_reason ?? ''),
        ]);
    }

    if ($funding->meeting) {
        $funding->meeting->update([
            'status' => 'cancelled',
           'meeting_date' => now(), 
            'notes' => 'تم إلغاء طلب التمويل من قبل صاحب الفكرة.',
        ]);
    }

    if ($idea->roadmap) {
        $stages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "الاجتماع التوجيهي",
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
            'next_step' => 'يمكن إعادة تقديم طلب التمويل بعد التعديل',
        ]);
    }

        $idea->update([
        'roadmap_stage' => $idea->roadmap?->current_stage ?? null,
    ]);

    return response()->json([
        'message' => 'تم إلغاء طلب التمويل بنجاح، وتم تحديث الاجتماع والتقرير وخارطة الطريق.',
        'funding' => $funding,
    ]);
}





public function getCommitteeFundRequests(Request $request)//عرض طلبات التمويل للجنة 
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
            'ideaOwner.user',
            'gantt:id,phase_name',
            'task:id,task_name'
        ])
        ->where('committee_id', $committeeMember->committee_id)
        ->orderByDesc('created_at')
        ->get();

    $fundings->transform(function ($funding) {
        $funding->gantt_name = $funding->gantt?->phase_name;
        $funding->task_name = $funding->task?->task_name;
        return $funding;
    });

    if ($fundings->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد طلبات تمويل حالياً لهذه اللجنة.'
        ], 200);
    }

    return response()->json([
        'committee_id' => $committeeMember->committee_id,
        'funding_requests' => $fundings
    ], 200);
}



public function getUserFundings(Request $request)//عرض طلب التمويل الذي ارسله صاحب الفكرة
{
    $user = $request->user();
    $ideaOwner = $user->ideaOwner;
    if (!$ideaOwner) {
        return response()->json([
            'message' => 'أنت لا تمتلك أي أفكار مسجلة.'
        ], 404);
    }
    $fundings = Funding::with([
        'idea:id,title',                    
        'committee:id,committee_name',      
    'investor.user:id,name', 
        'meeting:id,meeting_date,notes,meeting_link',
        'report:id,description,status'       
    ])
    ->where('idea_owner_id', $ideaOwner->id)
    ->orderByDesc('created_at')
    ->get();

    if ($fundings->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد طلبات تمويل مسجلة.'
        ], 200);
    }

    $data = $fundings->map(function($funding) {
        return [
            'funding_id' => $funding->id,
            'requested_amount' => $funding->requested_amount,
            'justification' => $funding->justification,
            'status' => $funding->status,
            'requirements_verified' => $funding->requirements_verified,

            'idea' => $funding->idea->title ?? null,
            'committee' => $funding->committee->committee_name ?? null,
            'investor' => $funding->investor->user->name ?? 'لم يحدد بعد',
            'meeting' => [
                'meeting_date' => $funding->meeting->meeting_date ?? null,
                'notes' => $funding->meeting->notes ?? null,
                   'meeting_link' => $funding->meeting->meeting_link 
                          ?? 'سيتم تحديد رابط الاجتماع لاحقاً من قبل اللجنة',
            ],
            'report' => [
                'description' => $funding->report->description ?? null,
                'status' => $funding->report->status ?? null,
            ],
            'created_at' => $funding->created_at,
            'updated_at' => $funding->updated_at,
        ];
    });

    return response()->json([
        'message' => 'قائمة طلبات التمويل الخاصة بك.',
        'fundings' => $data,
    ], 200);
}



public function evaluateFunding(Request $request, Funding $funding)//تقييم التمويل 
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember || $committeeMember->committee_id != $funding->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذا الطلب.'], 403);
    }

    $idea = $funding->idea;

    $validated = $request->validate([
        'score' => 'required|integer|min:0|max:100',
        'strengths' => 'nullable|string',
        'weaknesses' => 'nullable|string',
        'financial_analysis' => 'nullable|string',
        'risks' => 'nullable|string',
        'recommendation' => 'nullable|string',
        'comments' => 'nullable|string',
        'status' => 'required|in:approved,rejected,under_review',
        'approved_amount' => 'nullable|numeric|min:0',
        'requirements_verified' => 'nullable|boolean',
    ]);

  $evaluation = Evaluation::where('idea_id', $idea->id)
    ->where('funding_id', $funding->id)
    ->where('evaluation_type', 'funding')
    ->first();

if (!$evaluation) {
    $evaluation = Evaluation::create([
        'idea_id'          => $idea->id,
        'funding_id'       => $funding->id,
        'committee_id'     => $committeeMember->committee_id,
        'business_plan_id' => $idea->businessPlan?->id,
        'evaluation_type'  => 'funding',
    ]);
}

    $evaluation->update([
        'committee_id' => $committeeMember->committee_id,
        'business_plan_id' => $idea->businessPlan?->id,
        'score' => $validated['score'],
        'strengths' => $validated['strengths'] ?? 'غير محدد',
        'weaknesses' => $validated['weaknesses'] ?? 'غير محدد',
        'financial_analysis' => $validated['financial_analysis'] ?? 'غير محدد',
        'risks' => $validated['risks'] ?? 'غير محدد',
        'recommendation' => $validated['recommendation'] ?? 'غير محدد',
        'comments' => $validated['comments'] ?? 'لا توجد ملاحظات',
        'status' => $validated['status'],
    ]);

    $report = $funding->report;
    if ($report) {
        $report->update([
            'committee_id' => $committeeMember->committee_id,
            'description' => "تقرير تقييم طلب التمويل رقم {$funding->id}",
            'evaluation_score' => $validated['score'],
            'strengths' => $validated['strengths'],
            'weaknesses' => $validated['weaknesses'],
            'recommendations' => $validated['recommendation'],
            'status' => $validated['status'],
        ]);
    } else {
        $report = Report::create([
            'idea_id' => $idea->id,
            'committee_id' => $committeeMember->committee_id,
            'roadmap_id' => $idea->roadmap?->id,
            'meeting_id' => $funding->meeting_id,
            'description' => "تقرير تقييم طلب التمويل رقم {$funding->id}",
            'report_type' => 'funding_evaluation',
            'evaluation_score' => $validated['score'],
            'strengths' => $validated['strengths'],
            'weaknesses' => $validated['weaknesses'],
            'recommendations' => $validated['recommendation'],
            'status' => $validated['status'],
        ]);

        $funding->update(['report_id' => $report->id]);
    }

    $funding->update([
        'status' => $validated['status'],
        'approved_amount' => $validated['approved_amount'] ?? $funding->requested_amount,
        'committee_notes' => $validated['comments'] ?? $funding->committee_notes,
        'requirements_verified' => $validated['requirements_verified'] ?? false,
    ]);

    if ($validated['status'] === 'approved') {

        $investorUser = $funding->investor?->user;
        $ownerUser = $funding->ideaOwner?->user;

        $investorWallet = Wallet::where('user_id', $investorUser?->id)->first();
        $ownerWallet = Wallet::where('user_id', $ownerUser?->id)->first();

        if (!$investorWallet || !$ownerWallet) {
            return response()->json(['message' => 'محفظة المستثمر أو صاحب الفكرة غير موجودة.'], 404);
        }

        $amount = $validated['approved_amount'] ?? $funding->requested_amount;

        if ($investorWallet->balance < $amount) {
            return response()->json(['message' => 'رصيد المستثمر غير كافٍ لإجراء التحويل.'], 400);
        }

        $investorWallet->decrement('balance', $amount);
        $ownerWallet->increment('balance', $amount);

        WalletTransaction::create([
            'wallet_id' => $investorWallet->id,
            'funding_id' => $funding->id,
            'sender_id' => $investorUser->id,
            'receiver_id' => $ownerUser->id,
            'transaction_type' => 'transfer',
            'amount' => $amount,
            'percentage' => 0,
            'beneficiary_role' => 'creator',
            'status' => 'completed',
            'payment_method' => 'wallet',
            'notes' => 'تم تحويل مبلغ التمويل من المستثمر إلى صاحب الفكرة.',
        ]);

        $funding->update([
            'transfer_date' => now(),
            'transaction_reference' => 'TX-' . uniqid(),
            'payment_method' => 'wallet',
        ]);
    }

    $roadmapStages = [
        "تقديم الفكرة",
        "التقييم الأولي",
        "الاجتماع التوجيهي",
        "التخطيط المنهجي",
        "التقييم المتقدم قبل التمويل",
        "التمويل",
        "التنفيذ والتطوير",
        "الإطلاق",
        "المتابعة بعد الإطلاق",
        "استقرار المشروع وانفصاله عن المنصة",
    ];

    $currentStageIndex = array_search("التمويل", $roadmapStages);
    if ($validated['status'] === 'approved') {
        $stageDescription = "تمت الموافقة على التمويل وتحويل المبلغ إلى صاحب الفكرة.";
        $nextStep = $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة';
        $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
    } elseif ($validated['status'] === 'under_review') {
        $stageDescription = "طلب التمويل قيد المراجعة من اللجنة.";
        $nextStep = "انتظار قرار اللجنة";
        $progressPercentage = (($currentStageIndex + 0.5) / count($roadmapStages)) * 100;
    } else {
        $stageDescription = "تم رفض طلب التمويل؛ يمكن إعادة التقديم بعد تعديل الخطة.";
        $nextStep = "إعادة تقديم طلب التمويل";
        $progressPercentage = (($currentStageIndex + 0.2) / count($roadmapStages)) * 100;
    }

    $roadmap = $idea->roadmap;
    if ($roadmap) {
        $roadmap->update([
            'committee_id' => $committeeMember->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => "التمويل",
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStep,
        ]);
    } else {
        $roadmap = Roadmap::create([
            'idea_id' => $idea->id,
            'committee_id' => $committeeMember->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => "التمويل",
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStep,
        ]);
    }

    $idea->update(['roadmap_stage' => "التمويل"]);



    Notification::create([
        'user_id' => $idea->ideaowner?->user_id,
        'idea_id' => $idea->id,
        'meeting_id' => $funding->meeting_id,
        'report_id' => $report->id,
        'title' => 'تقرير تمويل جديد',
        'message' => 'تم إصدار تقرير تقييم التمويل لفكرتك "' . $idea->title . '" والحالة: ' . $validated['status'] . '.',
        'type' => 'funding_report_owner',
        'is_read' => false,
    ]);

    $committeeMembers = CommitteeMember::where('committee_id', $idea->committee_id)
        ->where('user_id', '!=', $user->id)
        ->get();

    foreach ($committeeMembers as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'idea_id' => $idea->id,
            'meeting_id' => $funding->meeting_id,
            'report_id' => $report->id,
            'title' => 'تقرير تمويل جديد',
            'message' => 'تم إصدار تقرير تمويل جديد للفكرة "' . $idea->title . '". الحالة: ' . $validated['status'] . '.',
            'type' => 'funding_report_committee',
            'is_read' => false,
        ]);
    }

    if ($validated['status'] === 'approved') {
        Notification::create([
            'user_id' => $idea->ideaowner?->user_id,
            'idea_id' => $idea->id,
            'meeting_id' => $funding->meeting_id,
            'report_id' => $report->id,
            'title' => 'تمت الموافقة على التمويل',
            'message' => 'مبروك! تمت الموافقة على تمويل فكرتك "' . $idea->title . '" وتم تحويل المبلغ إلى محفظتك.',
            'type' => 'funding_approved',
            'is_read' => false,
        ]);
    }


    return response()->json([
        'message' => 'تم تقييم طلب التمويل وتحويل المبلغ وتحديث جميع السجلات بنجاح.',
        'evaluation' => $evaluation,
        'report' => $report,
        'funding' => $funding,
        'roadmap' => $roadmap,
    ]);
}


public function showFundingForIdea(Request $request, $idea_id)//عرض طلبات التمويل التي كتبها صاحب الفكرة لصاحب الفكرة
{
    $user = $request->user(); 
    $idea = Idea::where('id', $idea_id)
        ->whereHas('ideaOwner', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'هذه الفكرة غير موجودة أو لا تنتمي لك.',
        ], 404);
    }

    $fundings = Funding::with([
        'idea:id,title,description,initial_evaluation_score',
        'ideaOwner.user:id,name,email',
        'committee:id,committee_name',
        'investor.user:id,name,email',
        'meeting:id,meeting_date,notes',
        'report:id,description,status',
        'walletTransactions.sender:id,name,email',
        'walletTransactions.receiver:id,name,email',
    ])
    ->where('idea_id', $idea_id)
    ->get();

    if ($fundings->isEmpty()) {
        return response()->json([
            'message' => 'لا يوجد طلبات تمويل لهذه الفكرة.',
        ], 404);
    }

    $response = $fundings->map(function ($funding) {
        return [
            'funding_id' => $funding->id,
            'status' => $funding->status,
            'requested_amount' => $funding->requested_amount,
            'approved_amount' => $funding->approved_amount,
            'payment_method' => $funding->payment_method,
            'transfer_date' => $funding->transfer_date,
            'transaction_reference' => $funding->transaction_reference,
            'committee_notes' => $funding->committee_notes,
            'requirements_verified' => $funding->requirements_verified,

            'idea' => [
                'id' => $funding->idea->id ?? null,
                'title' => $funding->idea->title ?? null,
                'description' => $funding->idea->description ?? null,
                'initial_evaluation_score' => $funding->idea->initial_evaluation_score ?? null,
            ],

            'committee' => [
                'id' => $funding->committee->id ?? null,
                'name' => $funding->committee->committee_name ?? null,
            ],

            'investor' => [
                'id' => $funding->investor->id ?? null,
                'name' => $funding->investor->user->name ?? null,
                'email' => $funding->investor->user->email ?? null,
            ],

            'meeting' => [
                'id' => $funding->meeting->id ?? null,
                'meeting_date' => $funding->meeting->meeting_date ?? null,
                'notes' => $funding->meeting->notes ?? 'رابط الاجتماع سيُحدد لاحقًا من قبل اللجنة',
            ],

            'report' => [
                'id' => $funding->report->id ?? null,
                'description' => $funding->report->description ?? null,
                'status' => $funding->report->status ?? null,
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
                        'id' => $tx->sender->id ?? null,
                        'name' => $tx->sender->name ?? null,
                        'email' => $tx->sender->email ?? null,
                    ],
                    'receiver' => [
                        'id' => $tx->receiver->id ?? null,
                        'name' => $tx->receiver->name ?? null,
                        'email' => $tx->receiver->email ?? null,
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



    public function showCommitteeFundingChecks(Request $request)//عرض الشيك للجنة
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember) {
        return response()->json(['message' => 'أنت غير مرتبط بأي لجنة.'], 403);
    }

    $fundings = Funding::with([
        'idea:id,title,description',
        'ideaOwner.user:id,name,email',
        'committee:id,committee_name',
        'investor.user:id,name,email',
        'report:id,description,status',
        'walletTransactions.sender:id,name,email',
        'walletTransactions.receiver:id,name,email',
    ])
    ->where('committee_id', $committeeMember->committee_id)
    ->get();

    $checks = [];

    foreach ($fundings as $funding) {
        foreach ($funding->walletTransactions as $tx) {
            $checks[] = [
                'check_id' => $tx->id,
                'from' => $tx->sender->name ?? 'غير معروف',
                'to' => $tx->receiver->name ?? 'غير معروف',
                'amount' => $tx->amount,
                'date' => $tx->created_at->format('Y-m-d'),
                'payment_method' => $tx->payment_method ?? 'غير محدد',
                'notes' => $tx->notes ?? '',
                'funding_id' => $funding->id,
                'idea_title' => $funding->idea->title ?? '',
                'investor' => $funding->investor->user->name ?? '',
                'idea_owner' => $funding->ideaOwner->user->name ?? '',
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
