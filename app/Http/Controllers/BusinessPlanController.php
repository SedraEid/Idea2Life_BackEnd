<?php

namespace App\Http\Controllers;

use App\Models\BusinessPlan;
use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Idea;
use App\Models\IdeaOwner;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BusinessPlanController extends Controller
{
public function store(Request $request, Idea $idea)
{
    $user = $request->user();
    $ideaOwner = $idea->ideaowner;

    if (!$ideaOwner || $ideaOwner->user_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية إنشاء خطة العمل لهذه الفكرة.'
        ], 403);
    }
    if (is_null($idea->initial_evaluation_score)) {
        return response()->json([
            'message' => 'لم يتم تقييم الفكرة بعد، لا يمكنك الانتقال إلى مرحلة خطة العمل.'
        ], 400);
    }

    if ($idea->initial_evaluation_score < 80) {
        return response()->json([
            'message' => 'لم تحقق الفكرة الحد الأدنى من التقييم (80) للانتقال إلى خطة العمل.'
        ], 400);
    }

    $request->validate([
        'key_partners' => 'nullable|string',
        'key_activities' => 'nullable|string',
        'key_resources' => 'nullable|string',
        'value_proposition' => 'nullable|string',
        'customer_relationships' => 'nullable|string',
        'channels' => 'nullable|string',
        'customer_segments' => 'nullable|string',
        'cost_structure' => 'nullable|string',
        'revenue_streams' => 'nullable|string',
    ]);

    $businessPlan = $idea->businessPlan;
    if ($businessPlan) {
        $businessPlan->update([
            'key_partners' => $request->key_partners,
            'key_activities' => $request->key_activities,
            'key_resources' => $request->key_resources,
            'value_proposition' => $request->value_proposition,
            'customer_relationships' => $request->customer_relationships,
            'channels' => $request->channels,
            'customer_segments' => $request->customer_segments,
            'cost_structure' => $request->cost_structure,
            'revenue_streams' => $request->revenue_streams,
            'status' => 'under_review',
            'latest_score' => 0, 
        ]);
    } else {
        $businessPlan = BusinessPlan::create([
            'idea_id' => $idea->id,
            'owner_id' => $ideaOwner->id,
            'committee_id' => $idea->committee_id ?? null,
            'report_id' => null,
            'meeting_id' => null,
            'key_partners' => $request->key_partners,
            'key_activities' => $request->key_activities,
            'key_resources' => $request->key_resources,
            'value_proposition' => $request->value_proposition,
            'customer_relationships' => $request->customer_relationships,
            'channels' => $request->channels,
            'customer_segments' => $request->customer_segments,
            'cost_structure' => $request->cost_structure,
            'revenue_streams' => $request->revenue_streams,
            'status' => 'draft',
            'latest_score' => 0, 
        ]);
    }

    $meeting = $idea->meetings()->where('type', 'business_plan_review')->first();

    if ($meeting) {
        $meeting->update([
            'meeting_date' => now()->addDays(2),
            'requested_by' => 'committee',
        ]);
    } else {
        $meeting = $idea->meetings()->create([
            'owner_id' => $ideaOwner->id,
            'committee_id' => $idea->committee_id,
            'report_id' => null,
            'meeting_date' => now()->addDays(3),
            'type' => 'business_plan_review',
            'requested_by' => 'committee',
            'meeting_link' => null,
            'notes' => null,
        ]);
    }

    $report = $idea->reports()->where('report_type', 'advanced')->first();

    if ($report) {
        $report->update([
            'committee_id' => $idea->committee_id,
            'description' => 'تحديث تقييم خطة العمل بعد الاجتماع.',
            'status' => 'pending',
            'meeting_id' => $meeting->id,
        ]);
    } else {
        $report = $idea->reports()->create([
            'committee_id' => $idea->committee_id,
            'roadmap_id' => $idea->roadmap?->id,
            'description' => 'تقييم خطة العمل بعد الاجتماع.',
            'report_type' => 'advanced',
            'evaluation_score' => null,
            'strengths' => null,
            'weaknesses' => null,
            'recommendations' => null,
            'status' => 'pending',
            'meeting_id' => $meeting->id,
        ]);
    }

    $businessPlan->update([
        'meeting_id' => $meeting->id,
        'report_id' => $report->id,
    ]);

    $evaluation = \App\Models\Evaluation::where([
        'idea_id' => $idea->id,
        'committee_id' => $idea->committee_id,
        'evaluation_type' => 'advanced',
    ])->first();

    if ($evaluation) {
        $evaluation->update([
            'business_plan_id' => $businessPlan->id,
            'status' => 'pending',
        ]);
    } else {
        \App\Models\Evaluation::create([
            'idea_id' => $idea->id,
            'committee_id' => $idea->committee_id,
            'business_plan_id' => $businessPlan->id,
            'evaluation_type' => 'advanced',
            'score' => null,
            'recommendation' => null,
            'comments' => null,
            'strengths' => null,
            'weaknesses' => null,
            'financial_analysis' => null,
            'risks' => null,
            'status' => 'pending',
        ]);
    }

    if ($idea->roadmap) {
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

        $currentStageIndex = 3; 
        $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;

        $idea->roadmap->update([
            'current_stage' => 'التخطيط المنهجي',
            'stage_description' => 'تم إنشاء أو تحديث خطة العمل والفكرة الآن في مرحلة التخطيط المنهجي',
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => 'انتظار تقييم اللجنة المتقدم',
        ]);
    }

       $idea->update([
        'roadmap_stage' => 'خطة العمل قيد المراجعة',
    ]);

    return response()->json([
        'message' => 'تم إنشاء خطة العمل، الاجتماع، التقرير وسجل التقييم وتحديث المراحل بنجاح',
        'business_plan' => $businessPlan,
        'meeting' => $meeting,
        'report' => $report,
    ], 201);
}



public function showAllBMCsForCommittee(Request $request)
{
    $user = $request->user();
    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'هذا المستخدم ليس عضو لجنة.',
        ], 403);
    }
    $committeeId = $user->committeeMember->committee_id;
    $ideas = \App\Models\Idea::where('committee_id', $committeeId)
        ->whereHas('businessPlan') 
        ->with('businessPlan') 
        ->orderBy('created_at', 'desc')
        ->get();
    if ($ideas->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد أفكار تحتوي على خطة عمل للمراجعة حالياً.',
            'total_ideas' => 0,
            'data' => [],
        ], 200);
    }
    $formatted = $ideas->map(function ($idea) {
        $plan = $idea->businessPlan; 
        return [
            'idea_id' => $idea->id,
            'idea_title' => $idea->title,
            'idea_description' => $idea->description,
            'idea_status' => $idea->status,
            'roadmap_stage' => $idea->roadmap_stage,
            'business_plan' => $plan ? [
                'id' => $plan->id,
                'key_partners' => $plan->key_partners,
                'key_activities' => $plan->key_activities,
                'key_resources' => $plan->key_resources,
                'value_proposition' => $plan->value_proposition,
                'customer_relationships' => $plan->customer_relationships,
                'channels' => $plan->channels,
                'customer_segments' => $plan->customer_segments,
                'cost_structure' => $plan->cost_structure,
                'revenue_streams' => $plan->revenue_streams,
                'status' => $plan->status,
                'latest_score' =>$plan->latest_score,
                'created_at' => $plan->created_at->format('Y-m-d H:i'),
            ] : null,
        ];
    });
    return response()->json([
        'message' => 'تم جلب جميع خطط العمل (BMC) الخاصة بأفكار اللجنة بنجاح.',
        'total_ideas' => $formatted->count(),
        'data' => $formatted,
    ], 200);
}




public function updateBMC(Request $request, Idea $idea)//تعديل ال bmc اذا كان التقييم اقل من 80
{
   $user = $request->user();
$ideaOwner = IdeaOwner::where('user_id', $user->id)->first();

if (!$ideaOwner || $idea->owner_id != $ideaOwner->id) {
    return response()->json([
        'message' => 'ليس لديك صلاحية لتعديل خطة العمل لهذه الفكرة.'
    ], 403);
}   
    $advancedEvaluation = $idea->evaluations()
        ->where('evaluation_type', 'advanced')
        ->latest()
        ->first();

    if (!$advancedEvaluation) {
        return response()->json(['message' => 'لم يتم تقييم الفكرة بعد. لا يمكنك تعديل خطة العمل.'], 403);
    }

    $score = $advancedEvaluation->score;
    if ($score < 50) {
        return response()->json(['message' => 'نتيجة التقييم أقل من 50، لا يمكنك تعديل خطة العمل.'], 403);
    }
    if ($score >= 80) {
        return response()->json(['message' => 'تمت الموافقة على خطة العمل، لا يمكن تعديلها الآن.'], 403);
    }

    $validator = Validator::make($request->all(), [
        'key_partners' => 'nullable|string',
        'key_activities' => 'nullable|string',
        'key_resources' => 'nullable|string',
        'value_proposition' => 'nullable|string',
        'customer_relationships' => 'nullable|string',
        'channels' => 'nullable|string',
        'customer_segments' => 'nullable|string',
        'cost_structure' => 'nullable|string',
        'revenue_streams' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $businessPlan = $idea->businessPlan()->first();
    if ($businessPlan) {
        $businessPlan->update(array_merge(
            $validator->validated(),
            ['status' => 'needs_revision']
        ));
    } else {
        $businessPlan = $idea->businessPlan()->create(array_merge(
            $validator->validated(),
            [
                'idea_id' => $idea->id,
                'owner_id' => $ideaOwner->id,
                'committee_id' => $idea->committee_id ?? null,
                'status' => 'needs_revision',
            ]
        ));
    }

    if ($idea->roadmap) {
        $idea->roadmap()->update([
            'stage_description' => 'تم تعديل خطة العمل بناءً على ملاحظات التقييم المتقدم. بانتظار إعادة المراجعة.',
            'last_update' => now(),
        ]);
    }

    $idea->update([
        'roadmap_stage' => 'خطة العمل تحتاج إعادة مراجعة',
    ]);

    return response()->json([
        'message' => 'تم تعديل خطة العمل بنجاح. يمكنك الآن إعادة تقديمها للمراجعة.',
        'business_plan' => $businessPlan,
    ]);
}


public function showOwnerIdeaBMC(Request $request, $idea_id)//جلب ال BMC لصاحب الفكرة و لفكرة محددة
{
    $user = $request->user();

    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();

    if (!$ideaOwner) {
        return response()->json([
            'message' => 'أنت لا تملك أي أفكار.'
        ], 404);
    }
    $idea = $ideaOwner->ideas()
        ->with('businessPlan')
        ->where('id', $idea_id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.'
        ], 404);
    }

    $bmc = $idea->businessPlan;

    return response()->json([
        'message' => 'تم جلب خطة العمل بنجاح.',
        'idea' => [
            'idea_id' => $idea->id,
            'title' => $idea->title,
            'status' => $idea->status,
            'roadmap_stage' => $idea->roadmap_stage,
            'business_model_canvas' => $bmc ? [
                'bmc_id' => $bmc->id,
                'status' => $bmc->status,
                'key_partners' => $bmc->key_partners,
                'key_activities' => $bmc->key_activities,
                'key_resources' => $bmc->key_resources,
                'value_proposition' => $bmc->value_proposition,
                'customer_relationships' => $bmc->customer_relationships,
                'channels' => $bmc->channels,
                'customer_segments' => $bmc->customer_segments,
                'cost_structure' => $bmc->cost_structure,
                'revenue_streams' => $bmc->revenue_streams,
                'created_at' => optional($bmc->created_at)->format('Y-m-d H:i'),
                'updated_at' => optional($bmc->updated_at)->format('Y-m-d H:i'),
            ] : null,
        ]
    ], 200);
}





    
}
