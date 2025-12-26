<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Funding;
use App\Models\GanttChart;
use App\Models\Idea;
use App\Models\Notification;
use App\Models\Roadmap;
use App\Models\Task;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GanttChartController extends Controller
{

public function index(Request $request, $ideaId)
{
    $user = $request->user();
    if (!$ideaId) {
        return response()->json([
            'message' => 'يجب تحديد الفكرة.'
        ], 400);
    }
    $idea = Idea::with(['ganttCharts.tasks', 'committee.committeeMember'])
        ->where('id', $ideaId)
        ->first();
    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة.'
        ], 404);
    }
    $canAccess = false;
    if ($idea->owner_id === $user->id) {
        $canAccess = true;
    } elseif ($idea->committee && $idea->committee->committeeMember->contains('user_id', $user->id)) {
        $canAccess = true;
    }
    if (!$canAccess) {
        return response()->json([
            'message' => 'ليس لديك صلاحية الوصول إلى هذه الفكرة.'
        ], 403);
    }
    return response()->json([
        'message' => 'تم جلب المراحل بنجاح',
        'data' => $idea->ganttCharts
    ]);
}



public function getCommitteeIdeaGanttCharts(Request $request, $ideaId)//عرض المراحل و التاسكات لاعضاء اللجنة المشرفة
{
    $user = $request->user();

    $idea = Idea::with(['ganttCharts.tasks', 'committee.committeeMember'])
        ->where('id', $ideaId)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة.'
        ], 404);
    }

    if (!$idea->committee || !$idea->committee->committeeMember->contains('user_id', $user->id)) {
        return response()->json([
            'message' => 'ليس لديك صلاحية الوصول إلى هذه الفكرة.'
        ], 403);
    }

    return response()->json([
        'message' => 'تم جلب المراحل والمهام بنجاح',
        'data' => $idea->ganttCharts
    ]);
}


public function store(Request $request, $idea_id) // ادخال المراحل من قبل صاحب الفكرة
{
    $user = $request->user();
    $idea = Idea::with(['businessPlan', 'committee.committeeMember'])
                ->where('id', $idea_id)
                ->where('owner_id', $user->id)
                ->first();

    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة أو لا تنتمي إليك.'], 404);
    }
    if (!$idea->businessPlan || $idea->businessPlan->latest_score < 80) {
        return response()->json(['message' => 'لا يمكن إضافة مرحلة قبل أن يكون تقييم خطة العمل أعلى من 80.'], 403);
    }
    $validated = $request->validate([
        'phase_name' => 'required|string|max:255',
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
        'priority'   => 'nullable|integer|min:1',
    ]);
    $gantt = GanttChart::create([
        'idea_id' => $idea_id,
        'phase_name' => $validated['phase_name'],
        'start_date' => $validated['start_date'],
        'end_date' => $validated['end_date'],
        'priority' => $validated['priority'] ?? 1,
        'status' => 'pending',
        'progress' => 0,
        'approval_status' => 'pending',
    ]);
    $this->updateRoadmapStage($idea);
    $committeeMembers = $idea->committee?->committeeMember ?? collect();
    foreach ($committeeMembers as $member) {
        if ($member->user) {
            Notification::create([
                'user_id' => $member->user->id,
                'title' => 'تمت إضافة مرحلة جديدة على Gantt Chart',
                'message' => "قام صاحب الفكرة '{$idea->title}' بإضافة مرحلة جديدة: '{$validated['phase_name']}'. يرجى مراجعتها واعتمادها.",
                'type' => 'info',
                'is_read' => false,
            ]);
        }
    }
    return response()->json([
        'message' => 'تم إنشاء المرحلة بنجاح',
        'data' => $gantt
    ], 201);
}


//تعديل المراحل من قبل صاحب الفكرة
public function update(Request $request, $id)
{
    $user = $request->user();
    $gantt = GanttChart::with(['idea.owner', 'tasks', 'idea.meetings'])->find($id);
    if (!$gantt) {
        return response()->json(['message' => 'المرحلة غير موجودة.'], 404);
    }
    if (!$gantt->idea || $gantt->idea->owner->id != $user->id) {
        return response()->json(['message' => 'لا يمكنك تعديل هذه المرحلة.'], 403);
    }
    if ($gantt->approval_status === 'approved') {
        return response()->json(['message' => 'لا يمكن تعديل المرحلة بعد موافقة اللجنة.'], 403);
    }
    $validated = $request->validate([
        'phase_name' => 'sometimes|string|max:255',
        'start_date' => 'sometimes|date',
        'end_date'   => 'sometimes|date|after_or_equal:start_date',
        'status'     => 'sometimes|in:pending,in_progress,completed',
        'priority'   => 'sometimes|integer|min:1',
    ]);
    $gantt->update($validated);
    return response()->json([
        'message' => 'تم تحديث المرحلة بنجاح',
        'data' => $gantt
    ]);
}



    /**
     * حذف مرحلة
     */
    public function destroy(Request $request, $id)
{
    $user = $request->user();
    $gantt = GanttChart::with('idea.owner')->findOrFail($id);
    $idea = $gantt->idea;
    if (!$idea || !$idea->owner || $idea->owner->id != $user->id) {
        return response()->json(['message' => 'لا يمكنك حذف هذه المرحلة لأنها لا تخصك.'], 403);
    }
    if ($gantt->approval_status === 'approved') {
        return response()->json(['message' => 'لا يمكن حذف المرحلة بعد موافقة اللجنة.'], 403);
    }
    $gantt->delete();
    return response()->json(['message' => 'تم حذف المرحلة بنجاح']);
}



 //تحديث المرحلة الحالية في خارطة الطريق للفكرة
private function updateRoadmapStage(Idea $idea)
{
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
    $currentStage = "التنفيذ والتطوير";
    $currentStageIndex = array_search($currentStage, $roadmapStages);
    $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
        $idea->update([
        'roadmap_stage' => $currentStage,
    ]);
    if ($roadmap = $idea->roadmap) {
        $roadmap->update([
            'current_stage' => $currentStage,
            'stage_description' => "المرحلة الحالية: {$currentStage}",
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة',
        ]);
    }
}



//عند الانتهاء من المراحل و التساكات ارسال للجنة بانتهاء مخطط الغانت 
public function submitFullTimeline(Request $request, $idea_id)
{
    $user = $request->user();
    $idea = Idea::with(['owner', 'ganttCharts.tasks', 'committee.committeeMember'])
                ->find($idea_id);
    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
    }
    if ($idea->owner->id != $user->id) {
        return response()->json(['message' => 'لا يمكنك إرسال هذا الجدول الزمني لأنه لا يخصك.'], 403);
    }
    if ($idea->committee_approval_status === 'approved') {
        return response()->json(['message' => 'اللجنة قد وافقت بالفعل على الجدول الزمني.'], 200);
    }
    if ($idea->ganttCharts->count() == 0) {
        return response()->json(['message' => 'لا يمكنك الإرسال بدون مراحل.'], 422);
    }
    foreach ($idea->ganttCharts as $gantt) {
        if ($gantt->tasks->count() == 0) {
            return response()->json([
                'message' => "المرحلة '{$gantt->phase_name}' لا تحتوي على مهام. يجب ملء كل المراحل قبل الإرسال."
            ], 422);
        }
    }
    if ($idea->committee?->committeeMember) {
        foreach ($idea->committee->committeeMember as $member) {
            if ($member->user) {
                Notification::create([
                    'user_id' => $member->user->id,
                    'title' => "الجدول الزمني جاهز للتقييم",
                    'message' => "قام صاحب الفكرة '{$idea->title}' بإرسال الجدول الزمني كاملاً للتقييم.",
                    'type' => 'gantt_full_review',
                    'is_read' => false,
                ]);
            }
        }
    }
    return response()->json([
        'message' => 'تم إرسال الجدول الزمني كاملاً للتقييم بنجاح.',
        'data' => $idea->ganttCharts
    ]);
}


public function approveOrRejectAllPhases(Request $request, $idea_id)
{
    $user = $request->user();
    $idea = Idea::with(['ganttCharts', 'committee.committeeMember', 'owner'])
                ->findOrFail($idea_id);
    if (!$idea->committee || $idea->committee->committeeMember->isEmpty()) {
        return response()->json(['message' => 'هذه الفكرة لا تملك لجنة أو أعضاء لجنة.'], 404);
    }
    if (!$idea->committee->committeeMember->contains('user_id', $user->id)) {
        return response()->json(['message' => 'غير مسموح لك بالموافقة أو رفض مراحل هذه الفكرة.'], 403);
    }
    $validated = $request->validate([
        'approval_status' => 'required|in:approved,rejected',
        'reason' => 'nullable|string|max:1000', 
    ]);
    $idea->ganttCharts()->update(['approval_status' => $validated['approval_status']]);
    $statusMessage = $validated['approval_status'] === 'approved' 
        ? "اللجنة قامت بمراجعة جميع مراحل جدولك الزمني ووافقت عليها بعد التأكد من منطقية التواريخ والمهام. السبب: " . ($validated['reason'] ?? 'لا يوجد سبب محدد')
        : "اللجنة قامت بمراجعة جميع مراحل جدولك الزمني ورفضتها لوجود ملاحظات أو عدم توافق في التواريخ أو المهام. السبب: " . ($validated['reason'] ?? 'لا يوجد سبب محدد');
    if ($idea->owner) {
        Notification::create([
            'user_id' => $idea->owner->id,
            'title' => "تم تحديث حالة الموافقة على مراحل فكرة '{$idea->title}'",
            'message' => $statusMessage, 
            'type' => 'gantt_all_phases_approval_updated',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => $statusMessage,
        'data' => $idea->ganttCharts()->get()
    ]);
}


    //تقييم المرحلة من قبل اللجنة 
  public function evaluatePhase(Request $request, $idea_id, $gantt_id)
{
    $user = $request->user();
    $gantt = GanttChart::where('id', $gantt_id)
        ->where('idea_id', $idea_id)
        ->with('idea.owner', 'idea.committee.committeeMember')
        ->first();

    if (!$gantt) {
        return response()->json(['message' => 'المرحلة أو الفكرة غير موجودة.'], 404);
    }
    $committeeMember = $user->committeeMember;
    if (!$committeeMember || $committeeMember->committee_id != $gantt->idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذه المرحلة.'], 403);
    }
    if (now()->lt($gantt->end_date)) {
        return response()->json([
            'message' => "لا يمكن تقييم المرحلة قبل تاريخ الانتهاء ({$gantt->end_date->format('Y-m-d')})."
        ], 422);
    }
        $requiredCompletion = 80; 
    if ($gantt->progress < $requiredCompletion) {
        $gantt->end_date = now()->addDays(3);
        $gantt->save();
        Notification::create([
            'user_id' => $gantt->idea->owner->id,
            'title' => 'مهلة إضافية لإكمال المرحلة',
            'message' => "مرحلة '{$gantt->phase_name}' لم تصل نسبة الإنجاز إلى {$requiredCompletion}%. لديك مهلة إضافية حتى {$gantt->end_date->format('Y-m-d')} لإكمال المهام.",
            'type' => 'warning',
            'is_read' => false,
        ]);
        return response()->json([
            'message' => "نسبة الإنجاز أقل من {$requiredCompletion}%. تم منحك مهلة إضافية لإكمال المهام.",
            'new_end_date' => $gantt->end_date->format('Y-m-d')
        ], 422);
    }
    $validated = $request->validate([
        'score' => 'required|integer|min:0|max:100',
        'comments' => 'nullable|string|max:500',
    ]);
    $score = $validated['score'];
    $gantt->evaluation_score = $score;
    $gantt->evaluation_comments = $validated['comments'] ?? null;
    if ($score >= 71) {
        $gantt->save();
    } elseif ($score >= 41 && $score <= 70) {
        Notification::create([
            'user_id' => $gantt->idea->owner->id,
            'title' => 'تنبيه: الأداء متوسط',
            'message' => "مرحلة '{$gantt->phase_name}' بحاجة لتحسين. حافظ على جودة التنفيذ.",
            'type' => 'warning',
            'is_read' => false,
        ]);

        foreach ($gantt->idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'تنبيه: تقييم متوسط',
                'message' => "مرحلة '{$gantt->phase_name}' لمشروع '{$gantt->idea->title}' حصلت على تقييم متوسط.",
                'type' => 'info',
                'is_read' => false,
            ]);
        }

        $gantt->save();
    } elseif ($score <= 40) {
        $gantt->failure_count += 1;
        Notification::create([
            'user_id' => $gantt->idea->owner->id,
            'title' => 'فشل في المرحلة',
            'message' => "تم تقييم مرحلة '{$gantt->phase_name}' بنتيجة ضعيفة للغاية.",
            'type' => 'danger',
            'is_read' => false,
        ]);

        foreach ($gantt->idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'تنبيه: مرحلة فاشلة',
                'message' => "مرحلة '{$gantt->phase_name}' لمشروع '{$gantt->idea->title}' فشلت في التقييم.",
                'type' => 'warning',
                'is_read' => false,
            ]);
        }
        $gantt->save();
    }
    $idea = $gantt->idea;
    $failedPhases = $idea->ganttCharts()->where('failure_count', '>=', 1)->count();

    if ($failedPhases >= 3) {
        $idea->roadmap_stage = 'paused_for_payment';
        $idea->save();
        Notification::create([
            'user_id' => $idea->owner->id,
            'title' => 'تم إيقاف المشروع',
            'message' => "تم إيقاف المشروع بعد 3 تقييمات فاشلة. يجب دفع المبلغ الجزائي لمتابعة العمل.",
            'type' => 'critical',
            'is_read' => false,
        ]);
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'مشروع متوقف',
                'message' => "مشروع '{$idea->title}' تم إيقافه بعد فشل 3 مراحل.",
                'type' => 'info',
                'is_read' => false,
            ]);
        }
    }
    return response()->json([
        'message' => 'تم تقييم المرحلة بنجاح.',
        'gantt' => $gantt
    ]);
}

    //عرض تقييم اللجنة لمرحلة معينة لصاحب الفكرة 
public function getPhaseEvaluation(Request $request, $idea_id, $gantt_id)
{
    $user = $request->user();
    $gantt = GanttChart::where('id', $gantt_id)
        ->where('idea_id', $idea_id)
        ->with('idea.owner')
        ->first();
    if (!$gantt || !$gantt->idea) {
        return response()->json([
            'message' => 'المرحلة أو الفكرة غير موجودة.'
        ], 404);
    }
    if ($gantt->idea->owner->id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية رؤية تقييم هذه المرحلة.'
        ], 403);
    }
    return response()->json([
        'message' => 'تم جلب تقييم المرحلة بنجاح.',
        'score' => $gantt->evaluation_score,
        'comments' => $gantt->evaluation_comments,
    ]);
}
    //عرض انه يجب ان يتم دفع مبلغ جزائي من قبل صاحب افكرة بسبب التقييم السيء للتنفيذ
    public function showPenaltyStatus(Request $request, $idea_id)
{
    $idea = Idea::with(['owner', 'ganttCharts'])->find($idea_id);
    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة.'
        ], 404);
    }
    $currentUser = $request->user();
    if ($idea->owner->id !== $currentUser->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية الاطلاع على حالة الغرامة لهذه الفكرة.'
        ], 403);
    }
    $badPhases = $idea->ganttCharts->where('failure_count', '>=', 1);
    $badPhasesCount = $badPhases->count();
    $penaltyAmount = 10000; 
    if ($badPhasesCount < 3) {
        return response()->json([
            'message' => "لا يوجد غرامة حالياً، عدد المراحل السيئة: {$badPhasesCount}.",
            'bad_phases_count' => $badPhasesCount,
        ], 200);
    }
    return response()->json([
        'message' => "تم الوصول إلى حد الغرامة: لديك {$badPhasesCount} مراحل سيئة، ويجب دفع مبلغ جزائي قدره {$penaltyAmount} ليتم متابعة المشروع.",
        'bad_phases' => $badPhases->pluck('phase_name'),
        'penalty_amount' => $penaltyAmount,
    ]);
}

public function payPenaltyForPhase(Request $request, $idea_id)
{
    $idea = Idea::with(['owner', 'ganttCharts', 'committee.committeeMember'])
        ->find($idea_id);

    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
    }
    $currentUser = $request->user();
    if ($idea->owner->id !== $currentUser->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية دفع الغرامة لهذه الفكرة.'
        ], 403);
    }
    if ($idea->roadmap_stage !== 'paused_for_payment') {
        return response()->json([
            'message' => 'لا يمكن دفع الغرامة الآن، المشروع ليس موقوفًا للدفع.'
        ], 422);
    }
    $badPhases = $idea->ganttCharts->where('failure_count', '>=', 1);
    $badPhasesCount = $badPhases->count();
    if ($badPhasesCount < 3) {
        return response()->json([
            'message' => 'عدد المراحل السيئة أقل من 3 — لا يوجد غرامة مطلوبة.'
        ], 422);
    }
    $investorMember = $idea->committee->committeeMember
        ->where('role_in_committee', 'investor')
        ->first();

    if (!$investorMember) {
        return response()->json([
            'message' => 'لا يوجد مستثمر مرتبط بهذه الفكرة.'
        ], 404);
    }

    $ownerWallet = Wallet::where('user_id', $idea->owner->id)
        ->lockForUpdate()
        ->first();

    $investorWallet = Wallet::where('user_id', $investorMember->user_id)
        ->lockForUpdate()
        ->first();

    if (!$ownerWallet || !$investorWallet) {
        return response()->json([
            'message' => 'محافظ المستخدم أو المستثمر غير موجودة.'
        ], 404);
    }
    $amount = 10000;
    if ($ownerWallet->balance < $amount) {
        return response()->json([
            'message' => 'رصيدك غير كافٍ لدفع الغرامة.'
        ], 400);
    }

    try {
        DB::beginTransaction();
        $ownerWallet->balance -= $amount;
        $ownerWallet->save();
        $investorWallet->balance += $amount;
        $investorWallet->save();
        WalletTransaction::create([
            'wallet_id' => $investorWallet->id,
            'sender_id' => $idea->owner->id,
            'receiver_id' => $investorMember->user_id,
            'transaction_type' => 'transfer',
            'amount' => $amount,
            'status' => 'completed',
            'percentage' => 0,
            'beneficiary_role' => 'investor',
            'payment_method' => 'wallet',
            'notes' => 'تم دفع المبلغ الجزائي بعد الوصول لثلاث مراحل سيئة.',
        ]);
        $badPhases->each(function ($gantt) {
            $gantt->failure_count = 0;
            $gantt->save();
        });
        $idea->roadmap_stage = 'التنفيذ و التطوير';
        $idea->status = 'approved';
        $idea->save();

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'حدث خطأ أثناء عملية الدفع.',
            'error' => $e->getMessage()
        ], 500);
    }
    Notification::create([
        'user_id' => $idea->owner->id,
        'title' => 'تم دفع المبلغ الجزائي',
        'message' => "لقد قمت بدفع مبلغ {$amount} وأصبح بإمكانك متابعة التنفيذ.",
        'type' => 'success',
        'is_read' => false,
    ]);
    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => 'تنبيه: استئناف المشروع',
            'message' => "تم دفع المبلغ الجزائي من قبل صاحب فكرة '{$idea->title}' وتم استئناف العمل.",
            'type' => 'info',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => "تم دفع الغرامة ({$amount}) بنجاح وتم استئناف المشروع.",
    ]);
}
//طلب تمويل من قبل صاحب الفكرة ضمن اي مرحلة   
public function requestFundingGantt(Request $request, $gantt_id)
{
    $user = $request->user();
    $gantt = GanttChart::with(['idea.owner', 'idea.ganttCharts', 'idea.committee.committeeMember'])
        ->find($gantt_id);
    if (!$gantt) {
        return response()->json([
            'message' => "المرحلة بالمعرف {$gantt_id} غير موجودة."
        ], 404);
    }
    $idea = $gantt->idea;
    if ($idea->owner->id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية طلب التمويل لهذه الفكرة.'
        ], 403);
    }
    $badPhasesCount = $idea->ganttCharts
        ->where('failure_count', '>=', 1)
        ->count();

    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل لأن هناك 3 مراحل أو أكثر ذات أداء ضعيف. يجب اتخاذ الإجراءات المطلوبة أولاً.'
        ], 403);
    }
    $existingFunding = Funding::where('idea_id', $idea->id)
        ->whereIn('status', ['requested', 'under_review'])
        ->first();

    if ($existingFunding) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل جديد قبل مراجعة الطلب الحالي.',
            'existing_funding' => $existingFunding
        ], 400);
    }

    $validated = $request->validate([
        'requested_amount' => 'required|numeric|min:1',
        'justification' => 'required|string|max:1000',
    ]);
    $meeting = $idea->meetings()->create([
        'meeting_date' => now()->addDays(2),
        'notes' => 'مناقشة طلب التمويل للمرحلة: ' . $gantt->phase_name,
        'requested_by' => 'owner',
        'type' => 'funding_request',
    ]);
    $investorMember = $idea->committee->committeeMember
        ->where('role_in_committee', 'investor')
        ->first();
    $funding = Funding::create([
        'idea_id' => $idea->id,
        'investor_id' => $investorMember?->user_id,
        'requested_amount' => $validated['requested_amount'],
        'justification' => $validated['justification'],
        'status' => 'requested',
        'gantt_id' => $gantt->id,
        'task_id' => null,
    ]);
    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => "طلب تمويل جديد للمرحلة '{$gantt->phase_name}'",
            'message' => "تم تقديم طلب تمويل بمبلغ {$validated['requested_amount']} من قبل صاحب الفكرة.",
            'type' => 'funding_request',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => 'تم تقديم طلب التمويل للمرحلة بنجاح.',
        'funding' => $funding,
        'meeting' => $meeting,
    ], 201);
}



//طلب تمويل من قبل صاحب الفكرة ضمن اي تاسك   
public function requestFundingTask(Request $request, $task_id)
{
    $user = $request->user();

    $task = Task::with('gantt.idea.owner', 'gantt.idea.committee.committeeMember')
        ->find($task_id);

    if (!$task) {
        return response()->json([
            'message' => "المهمة بالمعرف {$task_id} غير موجودة."
        ], 404);
    }
    $gantt = $task->gantt;
    $idea  = $gantt->idea;
    if ($idea->owner_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية طلب التمويل لهذه الفكرة.'
        ], 403);
    }
    $badPhasesCount = $idea->ganttCharts()
        ->where('failure_count', '>=', 1)
        ->count();

    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل لأن هناك 3 مراحل أو أكثر ذات أداء ضعيف.'
        ], 403);
    }
    $existingFunding = Funding::where('idea_id', $idea->id)
        ->whereIn('status', ['requested', 'under_review'])
        ->first();

    if ($existingFunding) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل جديد قبل مراجعة الطلب الحالي.',
            'existing_funding' => $existingFunding
        ], 400);
    }

    $validated = $request->validate([
        'requested_amount' => 'required|numeric|min:1',
        'justification'    => 'required|string|max:1000',
    ]);
    $meeting = $idea->meetings()->create([
        'meeting_date' => now()->addDays(2),
        'notes'        => 'مناقشة طلب التمويل للمهمة: ' . $task->task_name,
        'requested_by' => 'owner',
        'type'         => 'funding_request',
    ]);
    $investor = $idea->committee
        ->committeeMember()
        ->where('role_in_committee', 'investor')
        ->first();
    $funding = Funding::create([
        'idea_id'          => $idea->id,
        'committee_id'     => $idea->committee_id,
        'investor_id'      => $investor?->user_id,
        'meeting_id'       => $meeting->id,
        'requested_amount' => $validated['requested_amount'],
        'justification'    => $validated['justification'],
        'status'           => 'requested',
        'gantt_id'         => null,
        'task_id'          => $task->id,
    ]);
    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => "طلب تمويل جديد للمهمة '{$task->task_name}'",
            'message' => "تم تقديم طلب تمويل بمبلغ {$validated['requested_amount']} من قبل صاحب الفكرة.",
            'type'    => 'funding_request',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => 'تم تقديم طلب التمويل للمهمة بنجاح.',
        'funding' => $funding,
        'meeting' => $meeting,
    ], 201);
}


public function approveFunding(Request $request, Funding $funding)
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember || $committeeMember->committee_id !== $funding->idea->committee_id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لإجراء هذا الطلب.'
        ], 403);
    }

    $validated = $request->validate([
        'is_approved'     => 'required|boolean',
        'approved_amount' => 'nullable|numeric|min:0',
        'committee_notes' => 'nullable|string',
    ]);

    if ($validated['is_approved'] === false) {
        $funding->update([
            'status'          => 'rejected',
            'is_approved'     => false,
            'approved_amount' => 0,
            'committee_notes' => $validated['committee_notes'] ?? null,
        ]);

        Notification::create([
            'user_id' => $funding->idea->owner_id,
            'title'   => 'تم رفض طلب التمويل',
            'message' => 'قامت اللجنة برفض طلب التمويل الخاص بمشروعك.',
            'type'    => 'funding_rejected',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'تم رفض طلب التمويل.',
            'funding' => $funding,
        ]);
    }

    $investorUserId = $funding->investor_id;
    $ownerUserId    = $funding->idea->owner_id;
    $investorWallet = Wallet::where('user_id', $investorUserId)->first();
    $ownerWallet    = Wallet::where('user_id', $ownerUserId)->first();

    if (!$investorWallet || !$ownerWallet) {
        return response()->json([
            'message' => 'محفظة المستثمر أو صاحب الفكرة غير موجودة.'
        ], 404);
    }
    $amount = $validated['approved_amount'] ?? $funding->requested_amount;
    if ($investorWallet->balance < $amount) {
        return response()->json([
            'message' => 'رصيد المستثمر غير كافٍ.'
        ], 400);
    }

    DB::beginTransaction();
    try {
        $investorWallet->decrement('balance', $amount);
        $ownerWallet->increment('balance', $amount);

        WalletTransaction::create([
            'wallet_id'        => $ownerWallet->id,
            'funding_id'       => $funding->id,
            'sender_id'        => $investorUserId,
            'receiver_id'      => $ownerUserId,
            'transaction_type' => 'transfer',
            'amount'           => $amount,
            'percentage'       => 0,
            'beneficiary_role' => 'creator',
            'status'           => 'completed',
            'payment_method'   => 'wallet',
            'notes'            => 'تم تحويل مبلغ التمويل بعد موافقة اللجنة.',
        ]);

        $funding->update([
            'status'                => 'funded',
            'is_approved'           => true,
            'approved_amount'       => $amount,
            'committee_notes'       => $validated['committee_notes'] ?? null,
            'payment_method'        => 'wallet',
            'transfer_date'         => now(),
            'transaction_reference' => 'TX-' . uniqid(),
        ]);

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'فشل تحويل التمويل.',
            'error'   => $e->getMessage(),
        ], 500);
    }

    Notification::create([
        'user_id' => $ownerUserId,
        'title'   => 'تم تحويل التمويل',
        'message' => "تم تحويل مبلغ {$amount} إلى محفظتك بعد موافقة اللجنة.",
        'type'    => 'funding_approved',
        'is_read' => false,
    ]);

    return response()->json([
        'message' => 'تم تحويل مبلغ التمويل بنجاح.',
        'funding' => $funding,
    ]);
}





}
