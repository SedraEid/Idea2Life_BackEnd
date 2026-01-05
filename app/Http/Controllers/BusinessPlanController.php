<?php

namespace App\Http\Controllers;

use App\Models\BusinessPlan;
use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Idea;
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
    $ideaOwner = $idea->owner; 
    if (!$ideaOwner || $ideaOwner->id !== $user->id) {
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
     if ($businessPlan && $businessPlan->status === 'approved') {
        return response()->json([
            'message' => 'خطة العمل الخاصة بهذه الفكرة مقبولة بالفعل ولا يمكن تعديلها.',
        ], 400);
    }
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
    }
    $meeting = $idea->meetings()->where('type', 'business_plan_review')->first();
    $notes = "تم تحديد موعد الاجتماع لمراجعة وتقييم خطة العمل. 
يرجى من صاحب الفكرة ولجنة التقييم الحضور لمناقشة التفاصيل وإصدار تقرير التقييم.";
    if ($meeting) {
        $meeting->update([
            'meeting_date' => now()->addDays(2),
            'requested_by' => 'committee',
            'notes' => $notes
        ]);
    } else {
        $meeting = $idea->meetings()->create([
            'meeting_date' => now()->addDays(3),
            'type' => 'business_plan_review',
            'requested_by' => 'committee',
            'meeting_link' => null,
            'notes' => $notes
        ]);
    }
    $report = $idea->reports()->where('report_type', 'advanced')->first();
    if ($report) {
        $report->update([
            'description' => 'تحديث تقييم خطة العمل بعد الاجتماع.',
            'status' => 'pending',
            'meeting_id' => $meeting->id,
        ]);
    } else {
        $report = $idea->reports()->create([
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
   $roadmapStages = [
    ['name' => 'Idea Submission', 'actor' => 'Idea Owner'],
    ['name' => 'Initial Evaluation', 'actor' => 'Committee'],
    ['name' => 'Systematic Planning / Business Plan Preparation', 'actor' => 'Idea Owner'],
    ['name' => 'Advanced Evaluation Before Funding', 'actor' => 'Committee'],
    ['name' => 'Funding', 'actor' => 'Idea Owner (Funding Request) + Committee / Investor'],
    ['name' => 'Execution and Development', 'actor' => 'Idea Owner (Implementation) + Committee (Stage Evaluation)'],
    ['name' => 'Launch', 'actor' => 'Idea Owner + Committee'],
    ['name' => 'Post-Launch Follow-up', 'actor' => 'Idea Owner + Committee'],
    ['name' => 'Project Stabilization / Platform Separation', 'actor' => 'Idea Owner (Separation Request) + Committee (Approval of Stabilization)'],
];

$currentStageName = 'Systematic Planning / Business Plan Preparation';
$currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
$progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
$nextStep = $currentStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$currentStageIndex + 1]['name'] 
    : null;

$nextActor = $currentStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$currentStageIndex + 1]['actor'] 
    : null;

$stageDescription = "Stage executed by: " . $roadmapStages[$currentStageIndex]['actor'] .
                    ($nextStep ? " | Next stage: $nextStep (executed by: $nextActor)" : "");

$roadmap = $idea->roadmap;
if (!$roadmap) {
    $roadmap = Roadmap::create([
        'idea_id'           => $idea->id,
        'current_stage'     => $currentStageName,
        'stage_description' => $stageDescription,
        'progress_percentage'=> $progressPercentage,
        'last_update'       => now(),
        'next_step'         => $nextStep,
    ]);
} else {
    $roadmap->update([
        'current_stage'     => $currentStageName,
        'stage_description' => $stageDescription,
        'progress_percentage'=> $progressPercentage,
        'last_update'       => now(),
        'next_step'         => $nextStep,
    ]);
}

    $idea->update([
        'roadmap_stage' =>$currentStageName,
    ]);
        if ($idea->committee) {
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'تم كتابة خطة العمل',
                'message' => "تم إنشاء أو تحديث خطة العمل للفكرة '{$idea->title}'. يرجى الاطلاع عليها.",
                'type' => 'business_plan_written',
                'is_read' => false,
            ]);
        }
    }

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
    $ideas = Idea::where('committee_id', $committeeId)
        ->whereHas('businessPlan')
        ->with(['businessPlan' => function($query) {
            $query->select(
                'id',
                'idea_id',
                'key_partners',
                'key_activities',
                'key_resources',
                'value_proposition',
                'customer_relationships',
                'channels',
                'customer_segments',
                'cost_structure',
                'revenue_streams',
                'status',
                'latest_score',
                'created_at'
            );
        }])
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
                'latest_score' => $plan->latest_score,
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



public function updateBMC(Request $request, Idea $idea) // تعديل خطة العمل بعد التقييم الأقل من 80
{
    $user = $request->user();
    $ideaOwner = $idea->owner; 
    if (!$ideaOwner || $ideaOwner->id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لتعديل خطة العمل لهذه الفكرة.'
        ], 403);
    }
    $businessPlan = $idea->businessPlan;
    if (!$businessPlan) {
        return response()->json([
            'message' => 'لا توجد خطة عمل لهذه الفكرة، لا يمكن تعديل الـ BMC.'
        ], 404);
    }
    if ($businessPlan->latest_score >= 80 || $businessPlan->status === 'approved') {
        return response()->json([
            'message' => 'تمت الموافقة على خطة العمل بشكل نهائي، لا يمكن تعديل الـ BMC بعد الآن.'
        ], 403);
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
    $businessPlan->update(array_merge(
        $validator->validated(),
        ['status' => 'needs_revision'] 
    ));
    $roadmapStages = [
    ['name' => 'Idea Submission', 'actor' => 'Idea Owner'],
    ['name' => 'Initial Evaluation', 'actor' => 'Committee'],
    ['name' => 'Systematic Planning / Business Plan Preparation', 'actor' => 'Idea Owner'],
    ['name' => 'Advanced Evaluation Before Funding', 'actor' => 'Committee'],
    ['name' => 'Funding', 'actor' => 'Idea Owner (Funding Request) + Committee / Investor'],
    ['name' => 'Execution and Development', 'actor' => 'Idea Owner (Implementation) + Committee (Stage Evaluation)'],
    ['name' => 'Launch', 'actor' => 'Idea Owner + Committee'],
    ['name' => 'Post-Launch Follow-up', 'actor' => 'Idea Owner + Committee'],
    ['name' => 'Project Stabilization / Platform Separation', 'actor' => 'Idea Owner (Separation Request) + Committee (Approval of Stabilization)'],
];

$currentStageName = 'Systematic Planning / Business Plan Preparation';
$currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
$nextStep = 'Awaiting Committee Review'; // المرحلة القادمة بعد تعديل خطة العمل
$nextActor = 'Committee'; // المسؤول عن المرحلة القادمة

$progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
$stageDescription = "Stage executed by: Idea Owner (updated the business plan) | Next stage: $nextStep (executed by: $nextActor)";

if ($idea->roadmap) {
    $idea->roadmap->update([
        'current_stage'      => $currentStageName,
        'stage_description'  => $stageDescription,
        'progress_percentage'=> $progressPercentage,
        'last_update'        => now(),
        'next_step'          => $nextStep,
    ]);
} else {
    $roadmap = Roadmap::create([
        'idea_id'            => $idea->id,
        'current_stage'      => $currentStageName,
        'stage_description'  => $stageDescription,
        'progress_percentage'=> $progressPercentage,
        'last_update'        => now(),
        'next_step'          => $nextStep,
    ]);
}

    $idea->update([
        'roadmap_stage' => $currentStageName,
    ]);
    $committeeMembers = $idea->committee?->committeeMember;
    if ($committeeMembers) {
        foreach ($committeeMembers as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'تم تعديل خطة العمل لفكرة',
                'message' => "تم تعديل خطة العمل للفكرة '{$idea->title}'. يرجى مراجعتها مرة أخرى.",
                'type' => 'bmc_updated',
                'is_read' => false,
            ]);
        }
    }
    return response()->json([
        'message' => 'تم تعديل خطة العمل بنجاح. يرجى إعادة تقديمها للمراجعة.',
        'business_plan' => $businessPlan,
    ]);
}


public function showOwnerIdeaBMC(Request $request, $idea_id) // جلب الـ BMC لصاحب الفكرة ولفكرة محددة
{
    $user = $request->user();
    $idea = Idea::where('id', $idea_id)
        ->where('owner_id', $user->id) 
        ->with('businessPlan')
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
