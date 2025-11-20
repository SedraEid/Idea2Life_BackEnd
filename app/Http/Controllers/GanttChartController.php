<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Funding;
use App\Models\GanttChart;
use App\Models\Idea;
use App\Models\ImprovementPlan;
use App\Models\Notification;
use App\Models\Roadmap;
use App\Models\Task;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GanttChartController extends Controller
{

public function index(Request $request)
{
    $user = $request->user();
    $ideaId = $request->query('idea_id');

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

        $hasPendingImprovementPlan = $idea->improvementPlans()
        ->where('status', 'pending')
        ->exists();

    if ($hasPendingImprovementPlan) {
        return response()->json([
            'message' => 'لا يمكنك إضافة مرحلة جديدة حاليًا. يجب أولاً إعداد ورفع خطة تحسين لإقناع اللجنة بجدّيتك في تطوير الفكرة.'
        ], 403);
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

    $this->createPhaseMeeting($idea, $gantt); // التابع الخاص لإنشاء الاجتماع
    $this->updateRoadmapStage($idea);         // تحديث خارطة الطريق

    // إشعارات اللجنة
    if ($idea->committee && $idea->committee->committeeMember) {
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'idea_id' => $idea->id,
                'title' => "مرحلة جديدة ضمن فكرة '{$idea->title}'",
                'message' => "تم إضافة مرحلة '{$validated['phase_name']}' بانتظار موافقة اللجنة.",
                'type' => 'gantt_phase_committee',
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

        $gantt->delete();
        return response()->json(['message' => 'تم حذف المرحلة بنجاح']);
    }

  





// إنشاء اجتماع عند نهاية المرحلة تلقائيًا وإنشاء تقرير فارغ مرتبط به
private function createPhaseMeeting(Idea $idea, GanttChart $gantt)
{
    $committeeId = $idea->committee?->id;
    if (!$committeeId) {
        return null; 
    }

    $meetingDate = Carbon::parse($gantt->end_date)->addDay();

    $meeting = Meeting::create([
        'idea_id'      => $idea->id,
        'gantt_chart_id' => $gantt->id,
        'owner_id'     => $idea->ideaowner?->id,
        'committee_id' => $committeeId,
        'meeting_date' => $meetingDate,
        'notes'        => "اجتماع تقييم نهاية المرحلة: {$gantt->phase_name}. تقييم مدى الالتزام بالمواعيد والتقدم في المهام.",
        'requested_by' => 'committee', 
        'type'         => 'phase_evaluation',
        'meeting_link' => null,
    ]);

    Report::create([
        'idea_id'      => $idea->id,
        'meeting_id'   => $meeting->id,
        'committee_id' => $committeeId,
        'report_type'  => 'phase_evaluation',
        'status'       => 'pending',
        'description'  => null,
        'evaluation_score' => null,
        'strengths'    => null,
        'weaknesses'   => null,
        'recommendations' => null,
        'roadmap_id'   => $idea->roadmap?->id,
        'improvement_plan_id' => null,
        'delay_count'  => 0,
    ]);

    return $meeting;
}






 //تحديث المرحلة الحالية في خارطة الطريق للفكرة
private function updateRoadmapStage(Idea $idea)
{
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




public function approveOrRejectAllPhases(Request $request, $idea_id)
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
        ? 'تمت الموافقة على جميع المراحل.' 
        : 'تم رفض جميع المراحل.';

    if ($idea->ideaowner) {
        Notification::create([
            'user_id' => $idea->ideaowner->user_id,
            'idea_id' => $idea->id,
            'title' => "تم تحديث حالة الموافقة على مراحل فكرة '{$idea->title}'",
            'message' => "اللجنة قامت بتحديث حالة جميع المراحل: $statusMessage",
            'type' => 'gantt_all_phases_approval_updated',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => $statusMessage,
        'data' => $idea->ganttCharts()->get()
    ]);
}




public function updatePhaseReport(Request $request, Idea $idea, $gantt_id)
{
    $user = $request->user();

    if (!$user->committeeMember || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تعديل هذا التقرير.'], 403);
    }

    $meeting = $idea->meetings()
        ->where('type', 'phase_evaluation')
        ->where('committee_id', $idea->committee_id)
        ->where('gantt_chart_id', $gantt_id)
        ->first();

    if (!$meeting) {
        return response()->json(['message' => 'لا يوجد اجتماع مرتبط بهذه المرحلة.'], 404);
    }

    if ($meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن تعديل التقرير قبل انتهاء الاجتماع.',
            'meeting_date' => $meeting->meeting_date->toDateTimeString()
        ], 400);
    }

    $report = $idea->reports()
        ->where('report_type', 'phase_evaluation')
        ->where('meeting_id', $meeting->id)
        ->first();

    if (!$report) {
        return response()->json(['message' => 'التقرير غير موجود.'], 404);
    }

    $validated = $request->validate([
        'description' => 'nullable|string',
        'evaluation_score' => 'nullable|numeric|min:0|max:100',
        'strengths' => 'nullable|string',
        'weaknesses' => 'nullable|string',
        'recommendations' => 'nullable|string',
        'status' => 'sometimes|in:pending,completed',
    ]);

    $report->update([
        'description' => $validated['description'] ?? $report->description,
        'evaluation_score' => $validated['evaluation_score'] ?? $report->evaluation_score,
        'strengths' => $validated['strengths'] ?? $report->strengths,
        'weaknesses' => $validated['weaknesses'] ?? $report->weaknesses,
        'recommendations' => $validated['recommendations'] ?? $report->recommendations,
        'status' => $validated['status'] ?? 'completed',
        'delay_count' => ($validated['evaluation_score'] ?? $report->evaluation_score) <= 50 ? 1 : 0,
    ]);


    $lowScoreReports = Report::where('idea_id', $idea->id)
        ->where('delay_count', 1)
        ->whereNull('improvement_plan_id')
        ->get();

    if ($lowScoreReports->count() >= 3) {
        $latestPlan = $idea->improvementPlans()->latest()->first();

        if (!$latestPlan || $latestPlan->status !== 'pending') {
            $deadline = now()->addWeeks(2);
            $plan = ImprovementPlan::create([
                'idea_id' => $idea->id,
                'gantt_chart_id' => $gantt_id,
                'status' => 'pending',
                'deadline' => $deadline,
            ]);

            foreach ($lowScoreReports as $lowReport) {
                $lowReport->update(['improvement_plan_id' => $plan->id]);
            }

            Notification::create([
                'user_id' => $idea->ideaowner?->user_id,
                'idea_id' => $idea->id,
                'title' => "خطة تحسين مطلوبة للفكرة '{$idea->title}'",
                'message' => "تم إصدار 3 تقارير سلبية على الأقل، يرجى تقديم خطة تحسين خلال أسبوعين.",
                'type' => 'improvement_plan_required',
                'is_read' => false,
            ]);
        }
    }

    Notification::create([
        'user_id' => $idea->ideaowner?->user_id,
        'idea_id' => $idea->id,
        'meeting_id' => $meeting->id,
        'report_id' => $report->id,
        'title' => 'تقرير تقييم المرحلة تم تحديثه',
        'message' => 'تم تحديث تقرير تقييم المرحلة لفكرتك "' . $idea->title . '". يرجى الاطلاع عليه.',
        'type' => 'phase_evaluation_report_owner',
        'is_read' => false,
    ]);

    return response()->json([
        'message' => 'تم تحديث التقرير بنجاح.',
        'report' => $report,
        'meeting' => $meeting,
    ]);
}



public function getImprovementPlan(Request $request, $idea_id)//جلب خطة التحسين لكي يملأها صاحب الفكرة لاحقا 
{
    $user = $request->user();
    $idea = Idea::with('ideaowner')->findOrFail($idea_id);
    if (!$idea->ideaowner || $idea->ideaowner->user_id != $user->id) {
        return response()->json(['message' => 'لا يمكنك الوصول إلى خطط التحسين لهذه الفكرة.'], 403);
    }

    $plan = ImprovementPlan::where('idea_id', $idea_id)
        ->where('status', 'pending')
        ->latest()
        ->first();

    if (!$plan) {
        return response()->json(['message' => 'لا توجد خطة تحسين جاهزة للملء حالياً.'], 404);
    }

    return response()->json([
        'message' => 'تم جلب خطة التحسين بنجاح.',
        'plan' => $plan
    ]);
}





public function updateImprov(Request $request, $plan_id)//ملء خطة التحسين من قبل صاحب الفكرة 
{
    $user = $request->user();

    $plan = ImprovementPlan::with('idea.ideaowner')->findOrFail($plan_id);

    if (!$plan->idea || !$plan->idea->ideaowner || $plan->idea->ideaowner->user_id != $user->id) {
        return response()->json(['message' => 'لا يمكنك تعديل هذه الخطة لأنها لا تخصك.'], 403);
    }

 if (!in_array($plan->status, ['pending', 'rejected'])) {
    return response()->json([
        'message' => 'لا يمكن تعديل هذه الخطة لأنها تم اعتمادها أو مراجعتها من قبل اللجنة.'
    ], 403);
}


    if (now()->greaterThan($plan->deadline)) {
        return response()->json([
            'message' => 'لا يمكنك تعبئة خطة التحسين بعد انتهاء الموعد النهائي المحدد لها.'
        ], 403);
    }

    $validated = $request->validate([
        'root_cause' => 'nullable|string|max:1000',
        'corrective_actions' => 'nullable|string|max:1000',
        'revised_goals' => 'nullable|string|max:1000',
        'support_needed' => 'nullable|string|max:1000',
    ]);

    $plan->update($validated);

    $committeeId = $plan->idea->committee?->id;
    if ($committeeId) {
        $meetingDate = now()->addDay(2); 
        $meeting = Meeting::create([
            'idea_id' => $plan->idea->id,
            'gantt_chart_id' => $plan->gantt_chart_id,
            'owner_id' => $plan->idea->ideaowner->id,
            'committee_id' => $committeeId,
            'meeting_date' => $meetingDate,
            'notes' => "اجتماع لمراجعة خطة التحسين لفكرة: {$plan->idea->title}",
            'requested_by' => 'owner',
            'type' => 'improvement_plan_review',
            'meeting_link' => null,
        ]);

        foreach ($plan->idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'idea_id' => $plan->idea->id,
                'meeting_id' => $meeting->id,
                'title' => "خطة تحسين جديدة للمراجعة",
                'message' => "قام صاحب الفكرة بملء خطة التحسين. يرجى مراجعتها في الاجتماع المقرر بتاريخ {$meetingDate->toDateTimeString()}",
                'type' => 'improvement_plan_review',
                'is_read' => false,
            ]);
        }
    }

    return response()->json([
        'message' => 'تم تحديث خطة التحسين بنجاح وإنشاء اجتماع للمراجعة.',
        'plan' => $plan
    ]);
}



public function getIdeaImprovementPlanForCommittee(Request $request, $idea_id)//جلب خطة التحسين التي كتبها صاحب الفكرة للجنة المشرفة 
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لعرض خطة التحسين.'
        ], 403);
    }
    $idea = Idea::with(['committee', 'improvementPlans'])
                ->findOrFail($idea_id);

    if (!$idea->committee || $idea->committee->id != $user->committeeMember->committee_id) {
        return response()->json([
            'message' => 'لا يمكنك عرض خطة التحسين لأنك لست من اللجنة المشرفة على هذه الفكرة.'
        ], 403);
    }
    $plan = $idea->improvementPlans()->latest()->first();

    if (!$plan) {
        return response()->json([
            'message' => 'لا توجد خطة تحسين لهذه الفكرة حتى الآن.',
            'plan' => null
        ]);
    }

    return response()->json([
        'message' => 'تم جلب خطة التحسين بنجاح.',
        'plan' => $plan
    ]);
}






public function respondToImprovementPlan(Request $request, $plan_id)//راي اللجنة بخطة التحسين الخاصة بصاحب الفكرة
{
    $user = $request->user();
    $plan = ImprovementPlan::with('idea.ideaowner')->findOrFail($plan_id);

    if (!$plan->idea || !$plan->idea->ideaowner) {
        return response()->json(['message' => 'خطة التحسين غير مرتبطة بفكرة أو صاحب فكرة صالح.'], 404);
    }
    if (!$user->committeeMember) {
        return response()->json(['message' => 'ليس لديك صلاحية الرد على خطة التحسين.'], 403);
    }

    if ($plan->status !== 'pending') {
        return response()->json(['message' => 'هذه الخطة تم الرد عليها مسبقًا ولا يمكن تعديلها.'], 403);
    }

    $validated = $request->validate([
        'committee_score' => 'required|integer|min:0|max:100',
        'committee_feedback' => 'nullable|string|max:2000',
        'status' => 'required|in:approved,rejected',
    ]);

    $plan->update($validated);
    Notification::create([
        'user_id' => $plan->idea->ideaowner->user_id,
        'idea_id' => $plan->idea->id,
        'title' => "تم الرد على خطة التحسين لفكرتك '{$plan->idea->title}'",
        'message' => "اللجنة قامت بتقييم خطة التحسين الخاصة بك. النتيجة: {$validated['status']}. تحقق من الملاحظات والتعليقات.",
        'type' => 'improvement_plan_feedback',
        'is_read' => false,
    ]);

    $evaluation = Evaluation::updateOrCreate(
        [
            'idea_id' => $plan->idea->id,
            'evaluation_type' => 'improvement_plan',
            'committee_id' => $user->committeeMember->committee_id 
        ],
        [
            'business_plan_id' => $plan->idea->businessPlan?->id,
            'score' => $validated['committee_score'],
            'recommendation' => $validated['status'],
            'comments' => $validated['committee_feedback'] ?? 'لا توجد ملاحظات',
            'status' => $validated['status'],
        ]
    );
    return response()->json([
        'message' => 'تم الرد على خطة التحسين بنجاح.',
        'plan' => $plan,
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
    $businessPlan = $idea->businessPlan;
    if (!$businessPlan) {
        return response()->json(['message' => 'لا يمكن تقديم طلب تمويل قبل إعداد خطة العمل.'], 400);
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

    $report = $idea->reports()->create([
        'committee_id' => $idea->committee_id,
        'roadmap_id' => $idea->roadmap?->id,
        'meeting_id' => $meeting->id,
        'description' => 'تقرير أولي حول طلب التمويل للمرحلة: ' . $gantt->phase_name,
        'report_type' => 'funding',
        'status' => 'pending',
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
        'report_id' => $report->id,
        'gantt_id' => $gantt->id,
        'task_id' => null,
    ]);


    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'idea_id' => $idea->id,
            'meeting_id' => $meeting->id,
            'report_id' => $report->id,
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
        'report' => $report,
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

    $businessPlan = $idea->businessPlan;
    if (!$businessPlan) {
        return response()->json(['message' => 'لا يمكن تقديم طلب تمويل قبل إعداد خطة العمل.'], 400);
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

    $report = $idea->reports()->create([
        'committee_id' => $idea->committee_id,
        'roadmap_id' => $idea->roadmap?->id,
        'meeting_id' => $meeting->id,
        'description' => 'تقرير أولي حول طلب التمويل للمهمة: ' . $task->task_name,
        'report_type' => 'funding',
        'status' => 'pending',
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
        'report_id' => $report->id,
        'gantt_id' => null,
        'task_id' => $task->id,
    ]);

       foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'idea_id' => $idea->id,
            'meeting_id' => $meeting->id,
            'report_id' => $report->id,
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
        'report' => $report,
    ], 201);
}














public function evaluateFunding(Request $request, Funding $funding)
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
        'stage_description' => 'nullable|string|max:500',
        'progress_percentage' => 'nullable|integer|min:0|max:100',
        'next_step' => 'nullable|string|max:255',
    ]);

    $evaluation = Evaluation::where('idea_id', $idea->id)
        ->where('evaluation_type', 'funding')
        ->first();

    if (!$evaluation) {
        return response()->json(['message' => 'سجل التقييم غير موجود.'], 404);
    }

    $evaluation->update([
        'committee_id' => $committeeMember->committee_id,
        'business_plan_id' => $idea->businessPlan?->id,
        'funding_id' => $funding->id,
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

    $roadmap = null;

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
            'status' => 'funded',
        ]);

    }

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
        'message' => 'تم تقييم طلب التمويل وتحديث جميع السجلات بنجاح.',
        'evaluation' => $evaluation,
        'report' => $report,
        'funding' => $funding,
    ]);
}









}
