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

    $idea = Idea::with(['ganttCharts.tasks', 'committee.committeeMember'])
        ->where('id', $ideaId)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة.'
        ], 404);
    }
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
    if ($idea->ideaowner && $idea->ideaowner->user_id === $user->id) {
        $canAccess = true;
    }
    elseif ($idea->committee && $idea->committee->committeeMember->contains('user_id', $user->id)) {
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


public function store(Request $request, $idea_id)//ادخال المراحل من قبل صاحب الفكرة
{
    $user = $request->user();
    $idea = Idea::with(['businessPlan', 'committee.committeeMember', 'ideaowner'])
                ->where('id', $idea_id)
                ->whereHas('ideaowner', fn($q) => $q->where('user_id', $user->id))
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

    $this->updateRoadmapStage($idea);// تحديث خارطة الطريق

    return response()->json([
        'message' => 'تم إنشاء المرحلة بنجاح',
        'data' => $gantt
    ], 201);
}


//تعديل المراحل من قبل صاحب الفكرة
public function update(Request $request, $id)
{
    $user = $request->user();
    $gantt = GanttChart::with(['idea.ideaowner', 'tasks', 'idea.meetings'])->find($id);

    if (!$gantt) return response()->json(['message' => 'المرحلة غير موجودة.'], 404);

    if (!$gantt->idea || $gantt->idea->ideaowner->user_id != $user->id) {
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
        $gantt = GanttChart::with('idea.ideaowner')->findOrFail($id);
        $idea = $gantt->idea;

        if (!$idea || !$idea->ideaowner || $idea->ideaowner->user_id != $user->id) {
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

    $idea->roadmap_stage = $currentStage;
    $idea->save();

    $roadmap = $idea->roadmap;
    if ($roadmap) {
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
    $idea = Idea::with(['ideaowner', 'ganttCharts.tasks', 'committee.committeeMember'])
                ->find($idea_id);

    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
    }

    if ($idea->ideaowner->user_id != $user->id) {
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

    if ($idea->committee && $idea->committee->committeeMember) {
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => "الجدول الزمني جاهز للتقييم",
                'message' => "قام صاحب الفكرة '{$idea->title}' بإرسال الجدول الزمني كاملاً للتقييم.",
                'type' => 'gantt_full_review',
                'is_read' => false,
            ]);
        }
    }
    return response()->json([
        'message' => 'تم إرسال الجدول الزمني كاملاً للتقييم بنجاح.',
        'data' => $idea->ganttCharts
    ]);
}


public function approveOrRejectAllPhases(Request $request, $idea_id)//الموافقة على المراحل من قبل اللجنة
{
    $user = $request->user();
    $idea = Idea::with(['ganttCharts', 'committee.committeeMember', 'ideaowner'])
                ->findOrFail($idea_id);

    if (!$idea->committee || $idea->committee->committeeMember->isEmpty()) {
        return response()->json(['message' => 'هذه الفكرة لا تملك لجنة أو أعضاء لجنة.'], 404);
    }

    if (!$idea->committee->committeeMember->contains('user_id', $user->id)) {
        return response()->json(['message' => 'غير مسموح لك بالموافقة أو رفض مراحل هذه الفكرة.'], 403);
    }

    $validated = $request->validate([
        'approval_status' => 'required|in:approved,rejected',
    ]);

    $idea->ganttCharts()->update(['approval_status' => $validated['approval_status']]);

     $statusMessage = $validated['approval_status'] === 'approved' 
        ? 'اللجنة قامت بمراجعة جميع مراحل جدولك الزمني ووافقت عليها بعد التأكد من منطقية التواريخ والمهام.' 
        : 'اللجنة قامت بمراجعة جميع مراحل جدولك الزمني ورفضتها لوجود ملاحظات أو عدم توافق في التواريخ أو المهام.';

    if ($idea->ideaowner) {
        Notification::create([
            'user_id' => $idea->ideaowner->user_id,
            'title' => "تم تحديث حالة الموافقة على مراحل فكرة '{$idea->title}'",
            'message' => "$statusMessage",
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
        ->with('idea.ideaowner', 'idea.committee.committeeMember')
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
    $validated = $request->validate([
        'score' => 'required|integer|min:0|max:100',
        'comments' => 'nullable|string|max:500',
    ]);

    $score = $validated['score'];
    $gantt->evaluation_score = $score;
    $gantt->evaluation_comments = $validated['comments'] ?? null;
    if ($score >= 71) {
        $gantt->save();
    }
    elseif ($score >= 41 && $score <= 70) {
        Notification::create([
            'user_id' => $gantt->idea->ideaowner->user_id,
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
    }
    elseif ($score <= 40) {
        $gantt->failure_count += 1;
        Notification::create([
            'user_id' => $gantt->idea->ideaowner->user_id,
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
            'user_id' => $idea->ideaowner->user_id,
            'title' => ' تم إيقاف المشروع',
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
        ->with('idea.ideaowner.user')
        ->first();

    if (!$gantt) {
        return response()->json(['message' => 'المرحلة أو الفكرة غير موجودة.'], 404);
    }
    $ownerUser = $gantt->idea->ideaowner->user;

    if ($user->id !== $ownerUser->id) {
        return response()->json(['message' => 'ليس لديك صلاحية رؤية تقييم هذه المرحلة.'], 403);
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
        $idea = Idea::with('ideaowner', 'ganttCharts')->find($idea_id);
        if (!$idea) {
            return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
        }
        $currentUser = $request->user();
        $ownerUser = $idea->ideaowner->user;
        if ($currentUser->id !== $ownerUser->id) {
            return response()->json(['message' => 'ليس لديك صلاحية الاطلاع على حالة الغرامة لهذه الفكرة.'], 403);
        }
        $badPhases = $idea->ganttCharts->where('failure_count', 1);
        $badPhasesCount = $badPhases->count();
        $penaltyAmount = 10000; 
        if ($badPhasesCount < 3) {
            return response()->json([
                'message' => "لا يوجد غرامة حالياً، عدد المراحل السيئة: {$badPhasesCount}."
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
    $idea = Idea::with('ideaowner.user', 'ganttCharts', 'committee.committeeMember')
        ->find($idea_id);

    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
    }

    $currentUser = $request->user();
    $ownerUser = $idea->ideaowner->user;
    if ($currentUser->id !== $ownerUser->id) {
        return response()->json(['message' => 'ليس لديك صلاحية دفع الغرامة لهذه الفكرة.'], 403);
    }
    if ($idea->roadmap_stage !== 'paused_for_payment') {
        return response()->json(['message' => 'لا يمكن دفع الغرامة الآن، المشروع ليس موقوفًا للدفع.'], 422);
    }
    $badPhases = $idea->ganttCharts->where('failure_count', 1);
    $badPhasesCount = $badPhases->count();

    if ($badPhasesCount < 3) {
        return response()->json([
            'message' => 'عدد المراحل السيئة أقل من 3 — لا يوجد غرامة مطلوبة.'
        ], 422);
    }
    $investor = $idea->committee->committeeMember
        ->where('role_in_committee', 'investor')
        ->first();

    if (!$investor) {
        return response()->json(['message' => 'لا يوجد مستثمر مرتبط بهذه الفكرة.'], 404);
    }

    $ownerWallet = Wallet::where('user_id', $ownerUser->id)->lockForUpdate()->first();
    $memberWallet = Wallet::where('user_id', $investor->user_id)->lockForUpdate()->first();

    if (!$ownerWallet || !$memberWallet) {
        return response()->json(['message' => 'محافظ المستخدم أو المستثمر غير موجودة.'], 404);
    }

    $amount = 10000;
        if ($ownerWallet->balance < $amount) {
        return response()->json(['message' => 'رصيدك غير كافٍ لدفع الغرامة.'], 400);
    }

    try {
        DB::beginTransaction();
        $ownerWallet->balance -= $amount;
        $ownerWallet->save();
        $memberWallet->balance += $amount;
        $memberWallet->save();
        WalletTransaction::create([
            'wallet_id' => $memberWallet->id,
            'sender_id' => $ownerUser->id,
            'receiver_id' => $investor->user_id,
            'transaction_type' => 'transfer',
            'amount' => $amount,
            'status' => 'completed',
            'percentage' => 0,
            'beneficiary_role' => 'investor',
            'payment_method' => 'wallet',
            'notes' => 'تم دفع المبلغ الجزائي بعد الوصول لثلاث مراحل سيئة.',
        ]);
        $badPhases->each(function($gantt) {
            $gantt->failure_count = 0;
            $gantt->save();
        });
        $idea->roadmap_stage = 'التنفيذ و التطوير';
        $idea->status='in progress';
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
        'user_id' => $ownerUser->id,
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

    $gantt = GanttChart::with('idea.ideaOwner')->find($gantt_id);
    if (!$gantt) {
        return response()->json(['message' => "المرحلة بالمعرف {$gantt_id} غير موجودة."], 404);
    }

    $idea = $gantt->idea;
    $ideaOwner = $idea->ideaOwner;

    if (!$ideaOwner || $ideaOwner->user_id !== $user->id) {
        return response()->json(['message' => 'ليس لديك صلاحية طلب التمويل لهذه الفكرة.'], 403);
    }
   // التحقق من عدد المراحل السيئة
    $badPhasesCount = $idea->ganttCharts->where('failure_count', 1)->count();
    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل لأن هناك 3 مراحل أو أكثر ذات أداء ضعيف. يجب اتخاذ الإجراءات المطلوبة أولاً.'
        ], 403);
    }

        $existingFunding = Funding::where('idea_id', $idea->id)
        ->where('idea_owner_id', $ideaOwner->id)
        ->whereIn('status', ['requested', 'under_review'])
        ->first();
        
if ($existingFunding && $existingFunding->status !== 'rejected') {
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
        'owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'meeting_date' => now()->addDays(2),
        'notes' => 'مناقشة طلب التمويل للمرحلة: ' . $gantt->phase_name,
        'requested_by' => 'owner',
        'type' => 'funding_request',
    ]);

    $investor = $idea->committee->committeeMember()->where('role_in_committee', 'investor')->first();

    $funding = Funding::create([
        'idea_id' => $idea->id,
        'idea_owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'investor_id' => $investor->id ?? null,
        'meeting_id' => $meeting->id,
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

    $task = Task::with('gantt.idea.ideaOwner')->find($task_id);
    if (!$task) {
        return response()->json(['message' => "المهمة بالمعرف {$task_id} غير موجودة."], 404);
    }

    $idea = $task->gantt->idea;
    $gantt = $task->gantt;
    $ideaOwner = $idea->ideaOwner;

    if (!$ideaOwner || $ideaOwner->user_id !== $user->id) {
        return response()->json(['message' => 'ليس لديك صلاحية طلب التمويل لهذه الفكرة.'], 403);
    }
  // التحقق من عدد المراحل السيئة
    $badPhasesCount = $idea->ganttCharts->where('failure_count', 1)->count();
    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => 'لا يمكنك طلب تمويل لأن هناك 3 مراحل أو أكثر ذات أداء ضعيف. يجب اتخاذ الإجراءات المطلوبة أولاً.'
        ], 403);
    }

            $existingFunding = Funding::where('idea_id', $idea->id)
        ->where('idea_owner_id', $ideaOwner->id)
        ->whereIn('status', ['requested', 'under_review'])
        ->first();
        
if ($existingFunding && $existingFunding->status !== 'rejected') {
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
        'owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'meeting_date' => now()->addDays(2),
        'notes' => 'مناقشة طلب التمويل للمهمة: ' . $task->task_name,
        'requested_by' => 'owner',
        'type' => 'funding_request',
    ]);

    $investor = $idea->committee->committeeMember()->where('role_in_committee', 'investor')->first();

    $funding = Funding::create([
        'idea_id' => $idea->id,
        'idea_owner_id' => $ideaOwner->id,
        'committee_id' => $idea->committee_id,
        'investor_id' => $investor->id ?? null,
        'meeting_id' => $meeting->id,
        'requested_amount' => $validated['requested_amount'],
        'justification' => $validated['justification'],
        'status' => 'requested',
        'gantt_id' => null,
        'task_id' => $task->id,
    ]);

       foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => "طلب تمويل جديد للمهمة '{$task->task_name}'",
            'message' => "تم تقديم طلب تمويل بمبلغ {$validated['requested_amount']} من قبل صاحب الفكرة.",
            'type' => 'funding_request',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => 'تم تقديم طلب التمويل للمهمة بنجاح.',
        'funding' => $funding,
        'meeting' => $meeting,
    ], 201);
}


public function approveFunding(Funding $funding, Request $request)//قبول او رفض التمويل 
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;
    if (!$committeeMember || $committeeMember->committee_id != $funding->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية لإجراء هذا الطلب.'], 403);
    }
        $validated = $request->validate([
        'is_approved' => 'required|boolean',
        'approved_amount' => 'nullable|numeric|min:0',
        'committee_notes' => 'nullable|string',
    ]);
    $meeting = $funding->meeting;
    if (!$meeting || $meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن الموافقة او رفض التمويل قبل إجراء الاجتماع أو قبل تاريخ عقده.',
            'meeting_date' => $meeting?->meeting_date?->toDateTimeString()
        ], 400);
    }
    if (!$validated['is_approved']) {
        $funding->update([
            'status' => 'rejected',
            'is_approved' => false,
            'approved_amount' => 0,
            'committee_notes' => $validated['committee_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم رفض طلب التمويل.',
            'funding' => $funding
        ]);
    }
    $investorUser = $funding->investor?->user;
    $ownerUser = $funding->ideaOwner?->user;
    $investorWallet = Wallet::where('user_id', $investorUser?->id)->first();
    $ownerWallet = Wallet::where('user_id', $ownerUser?->id)->first();

    if (!$investorWallet || !$ownerWallet) {
        return response()->json(['message' => 'محفظة المستثمر أو صاحب الفكرة غير موجودة.'], 404);
    }

    $amount = $funding->requested_amount;

    if ($investorWallet->balance < $amount) {
        return response()->json(['message' => 'رصيد المستثمر غير كافٍ.'], 400);
    }
    DB::beginTransaction();

    try {
        $investorWallet->decrement('balance', $amount);
        $ownerWallet->increment('balance', $amount);
        WalletTransaction::create([
            'wallet_id' => $ownerWallet->id,
            'funding_id' => $funding->id,
            'sender_id' => $investorUser->id,
            'receiver_id' => $ownerUser->id,
            'transaction_type' => 'transfer',
            'amount' => $amount,
            'percentage' => 0,
            'beneficiary_role' => 'creator',
            'status' => 'completed',
            'payment_method' => 'wallet',
            'notes' => 'تم تحويل مبلغ التمويل من المستثمر إلى صاحب الفكرة بعد موافقة اللجنة.',
        ]);
        $funding->update([
            'status' => 'funded',
            'is_approved' => true,
            'approved_amount' => $amount,
            'committee_notes' => $validated['committee_notes'] ?? null,
            'payment_method' => 'wallet',
            'transfer_date' => now(),
            'transaction_reference' => 'TX-' . uniqid(),
        ]);

        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'فشل تحويل التمويل.', 'error' => $e->getMessage()], 500);
    }
    Notification::create([
        'user_id' => $ownerUser->id,
        'title' => 'تم تحويل التمويل',
        'message' => "تم تحويل مبلغ التمويل {$amount} إلى محفظتك بعد موافقة اللجنة.",
        'type' => 'funding_approved',
        'is_read' => false,
    ]);

    return response()->json([
        'message' => 'تم تحويل مبلغ التمويل بنجاح بعد الموافقة.',
        'funding' => $funding,
    ]);
}








}
