<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Idea;
use App\Models\ImprovementPlan;
use App\Models\Meeting;
use App\Models\Notification;
use Illuminate\Http\Request;

class ImprovementPlanController extends Controller
{
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


}
