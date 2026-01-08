<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Idea;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\PostLaunchFollowUp;
use App\Models\Report;
use App\Models\Roadmap;
use App\Models\Task;
use Illuminate\Http\Request;

class ReportController extends Controller
{
public function ownerIdeaReports(Request $request, $idea_id)
{
    $user = $request->user();

    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'غير مصرح لك.'
        ], 403);
    }

    $idea = Idea::where('owner_id', $user->id)
        ->where('id', $idea_id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.',
        ], 404);
    }

    $reports = Report::where('idea_id', $idea_id)
        ->with([
            'meeting:id,meeting_date,notes', 
        ])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($report) {
            return [
                'report_id'        => $report->id,
                'report_type'      => $report->report_type,
                'evaluation_score' => $report->evaluation_score,
                'status'           => $report->status,

                'description'      => $report->description,
                'strengths'        => $report->strengths,
                'weaknesses'       => $report->weaknesses,
                'recommendations'  => $report->recommendations,

                'meeting' => $report->meeting ? [
                    'meeting_date' => $report->meeting->meeting_date,
                    'notes'        => $report->meeting->notes,
                ] : null,

                'created_at' => $report->created_at->format('Y-m-d H:i'),
            ];
        });

    if ($reports->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد تقارير لهذه الفكرة.',
            'total_reports' => 0,
            'data' => [],
        ], 200);
    }

    return response()->json([
        'idea' => [
            'id'     => $idea->id,
            'title'  => $idea->title,
            'status' => $idea->status,
        ],
        'total_reports' => $reports->count(),
        'reports' => $reports,
    ]);
}





public function evaluate(Request $request, Idea $idea)//التقييم الاولي للفكرة من قبل اللجنة 
{
    $user = $request->user();
    if (!$user->committeeMember || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذه الفكرة.'], 403);
    }
    $meeting = $idea->meetings()
        ->where('type', 'initial')
        ->where('meeting_date', '<=', now()) 
        ->first();

    if (!$meeting) {
        return response()->json([
            'message' => 'لا يمكن تقييم الفكرة قبل انعقاد الاجتماع الأولي من قبل اللجنة.'
        ], 422);
    }

    $validated = $request->validate([
        'evaluation_score' => 'required|integer|min:0|max:100',
        'description'      => 'nullable|string',
        'strengths'        => 'nullable|string',
        'weaknesses'       => 'nullable|string',
        'recommendations'  => 'nullable|string',
    ]);
    $report = Report::updateOrCreate(
        [
            'idea_id'     => $idea->id,
            'report_type' => 'initial',
        ],
        [
            'description'       => $validated['description'] ?? 'تقرير التقييم الأولي للفكرة.',
            'evaluation_score'  => $validated['evaluation_score'],
            'strengths'         => $validated['strengths'],
            'weaknesses'        => $validated['weaknesses'],
            'recommendations'   => $validated['recommendations'] ?? null,
            'status'            => 'completed',
        ]
    );

    if ($validated['evaluation_score'] >= 80) {
        $idea->status = 'approved';
    } elseif ($validated['evaluation_score'] >= 50) {
        $idea->status = 'needs_revision';
    } else {
        $idea->status = 'rejected';
    }
    $idea->initial_evaluation_score = $validated['evaluation_score'];
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
$currentStageName = 'Initial Evaluation';
$currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
$progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;

if ($validated['evaluation_score'] >= 80) {
    $idea->status = 'approved';
    $nextStageName = $roadmapStages[$currentStageIndex + 1]['name'];
    $nextActor = $currentStageIndex + 1 < count($roadmapStages) 
        ? $roadmapStages[$currentStageIndex + 1]['actor'] 
        : null;

    $stageDescription = "Stage executed by: " . $roadmapStages[$currentStageIndex]['actor'] .
                        ($nextStageName ? " | Next stage: $nextStageName (executed by: $nextActor)" : "");}

    elseif ($validated['evaluation_score'] >= 50) {
    $idea->status = 'needs_revision';
    $nextStageName = 'Rework the idea'; 
    $stageDescription = "Please revise your idea according to committee feedback";
} else {
    $idea->status = 'rejected';
    $nextStageName = 'Submit a new idea'; 
    $stageDescription = "Your idea was not feasible / not implementable";
}

$idea->roadmap_stage = $currentStageName;
$idea->save();
$roadmap = $idea->roadmap;
if ($roadmap) {
    $roadmap->update([
        'current_stage'       => $currentStageName,
        'stage_description'   => $stageDescription,
        'progress_percentage' => $progressPercentage,
        'last_update'         => now(),
        'next_step'           => $nextStageName,
    ]);
}
$ideaOwner = $idea->owner;
if ($ideaOwner) {
    $message = match($idea->status) {
        'approved'       => "تم قبول فكرتك '{$idea->title}'. يمكنك الانتقال للمرحلة التالية.",
        'needs_revision' => "فكرتك '{$idea->title}' بحاجة لإعادة صياغة.",
        'rejected'       => "فكرتك '{$idea->title}' غير قابلة للتنفيذ، يرجى تقديم فكرة جديدة.",
    };

    Notification::create([
        'user_id' => $ideaOwner->id,
        'title'   => 'نتيجة التقييم الأولي لفكرتك',
        'message' => $message,
        'type'    => 'initial_report_owner',
        'is_read' => false,
    ]);
}

    return response()->json([
        'message' => 'تم تقييم الفكرة وتحديث التقرير والاجتماع والتقييم بنجاح.',
        'idea'    => $idea,
        'report'  => $report,
    ]);
}



public function advancedEvaluation(Request $request, Idea $idea) // تقييم خطة العمل
{
    $user = $request->user();
    if (!$user->committeeMember || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذه الفكرة.'], 403);
    }
    $businessPlan = $idea->businessPlan()->latest()->first();
    if (!$businessPlan) {
        return response()->json(['message' => 'لا توجد خطة عمل لهذه الفكرة.'], 404);
    }

    $meeting = $idea->meetings()
        ->where('type', 'business_plan_review')
        ->latest('meeting_date')
        ->first();

    if (!$meeting) {
        return response()->json([
            'message' => 'لا يمكن إجراء التقييم المتقدم قبل تحديد اجتماع مراجعة خطة العمل.',
        ], 400);
    }

    if ($meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن إجراء التقييم قبل موعد الاجتماع.',
            'meeting_date' => $meeting->meeting_date->toDateTimeString(),
        ], 400);
    }
    $request->validate([
        'score' => 'required|integer|min:0|max:100',
        'strengths' => 'nullable|string',
        'weaknesses' => 'nullable|string',
        'financial_analysis' => 'nullable|string',
        'risks' => 'nullable|string',
        'recommendation' => 'nullable|string',
        'comments' => 'nullable|string',
    ]);

    $businessPlan->latest_score = $request->score;

    if ($request->score >= 80) {
        $businessPlan->status = 'approved';
    } elseif ($request->score >= 50) {
        $businessPlan->status = 'needs_revision';
    } else {
        $businessPlan->status = 'rejected';
    }
    $businessPlan->save();

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

$currentStageName = "Advanced Evaluation Before Funding";
$currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
$nextStageName = $currentStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$currentStageIndex + 1]['name'] 
    : null;
$nextActor = $currentStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$currentStageIndex + 1]['actor'] 
    : null;

if ($request->score >= 80) {
    $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
    $stageDescription = "Stage executed by: " . $roadmapStages[$currentStageIndex]['actor'] . 
                        ($nextStageName ? " | Next stage: $nextStageName (executed by: $nextActor)" : "") ;
                } elseif ($request->score >= 50) {
    $progressPercentage = (($currentStageIndex + 0.5) / count($roadmapStages)) * 100;
    $nextStageName = "Improve Business Plan & Resubmit";
       $nextActor = 'Idea Owner';
    $stageDescription = "Please revise your idea according to committee feedback | Next stage: $nextStageName (executed by: $nextActor)";
}
 else {
    $progressPercentage = (($currentStageIndex + 0.2) / count($roadmapStages)) * 100;
    $nextStageName = "Submit a New Idea";
    $nextActor = 'Idea Owner';
    $stageDescription = "Your idea was not feasible / not implementable | Next stage: $nextStageName (executed by: $nextActor)";
}

$roadmap = $idea->roadmap;
if ($roadmap) {
    $roadmap->update([
        'current_stage' => $currentStageName,
        'stage_description' => $stageDescription,
        'progress_percentage' => $progressPercentage,
        'last_update' => now(),
        'next_step' => $nextStageName,
    ]);
} else {
    $roadmap = Roadmap::create([
        'idea_id' => $idea->id,
        'current_stage' => $currentStageName,
        'stage_description' => $stageDescription,
        'progress_percentage' => $progressPercentage,
        'last_update' => now(),
        'next_step' => $nextStageName,
    ]);
}

   $idea->update(['roadmap_stage' => $currentStageName]);
    $report = Report::updateOrCreate(
        [
            'idea_id' => $idea->id,
            'report_type' => 'advanced',
        ],
        [
            'meeting_id' => $meeting->id,
            'description' => 'تقرير التقييم المتقدم الصادر عن اللجنة بعد مراجعة خطة العمل والاجتماع.',
            'evaluation_score' => $request->score,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'recommendations' => $request->recommendation,
            'status' => 'completed',
        ]
    );
    $ideaOwner = $idea->owner;
    if ($ideaOwner) {
        Notification::create([
            'user_id' => $ideaOwner->id,
            'title' => 'تقرير تقييم جديد',
            'message' => 'تم إصدار تقرير تقييم متقدم جديد لفكرتك "' . $idea->title . '". يرجى الاطلاع عليه.',
            'type' => 'advance_report_owner',
            'is_read' => false,
        ]);
    }
    $committeeMembers = CommitteeMember::where('committee_id', $idea->committee_id)
        ->where('user_id', '!=', $user->id)
        ->get();

    foreach ($committeeMembers as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => 'تقرير تقييم جديد',
            'message' => 'تم إصدار تقرير تقييم متقدم جديد للفكرة "' . $idea->title . '".',
            'type' => 'advance_report_committee',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => 'تم إجراء التقييم المتقدم وتحديث خارطة الطريق وإصدار التقرير وحالة خطة العمل بنجاح.',
        'business_plan_status' => $businessPlan->status,
        'report' => $report,
        'roadmap' => $roadmap,
        'meeting' => $meeting,
    ]);
}


//عرض التقارير الاولية
public function ownerIdeaFirstStageReports(Request $request, $idea_id)
{
    $user = $request->user();

    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'غير مصرح لك.'
        ], 403);
    }
    $idea = Idea::where('owner_id', $user->id)
        ->where('id', $idea_id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.',
        ], 404);
    }
    $reports = Report::where('idea_id', $idea_id)
        ->where('report_type', 'initial') 
        ->with([
            'meeting:id,meeting_date,notes',
        ])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($report) {
            return [
                'report_id'        => $report->id,
                'report_type'      => $report->report_type,
                'evaluation_score' => $report->evaluation_score,
                'status'           => $report->status,
                'description'      => $report->description,
                'strengths'        => $report->strengths,
                'weaknesses'       => $report->weaknesses,
                'recommendations'  => $report->recommendations,
                'meeting' => $report->meeting ? [
                    'meeting_date' => $report->meeting->meeting_date,
                    'notes'        => $report->meeting->notes,
                ] : null,
                'created_at' => $report->created_at->format('Y-m-d H:i'),
            ];
        });

    if ($reports->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد تقارير للمرحلة الأولى لهذه الفكرة.',
            'total_reports' => 0,
            'data' => [],
        ], 200);
    }

    return response()->json([
        'idea' => [
            'id'     => $idea->id,
            'title'  => $idea->title,
            'status' => $idea->status,
        ],
        'total_reports' => $reports->count(),
        'reports' => $reports,
    ]);
}

//عرض التقارير المتقدمة 
public function ownerIdeaAdvancedReports(Request $request, $idea_id)
{
    $user = $request->user();

    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'غير مصرح لك.'
        ], 403);
    }

    $idea = Idea::where('owner_id', $user->id)
        ->where('id', $idea_id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.',
        ], 404);
    }
    $reports = Report::where('idea_id', $idea_id)
        ->where('report_type', 'advanced') 
        ->with(['meeting:id,meeting_date,notes'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($report) {
            return [
                'report_id'        => $report->id,
                'report_type'      => $report->report_type,
                'evaluation_score' => $report->evaluation_score,
                'status'           => $report->status,
                'description'      => $report->description,
                'strengths'        => $report->strengths,
                'weaknesses'       => $report->weaknesses,
                'recommendations'  => $report->recommendations,
                'meeting' => $report->meeting ? [
                    'meeting_date' => $report->meeting->meeting_date,
                    'notes'        => $report->meeting->notes,
                ] : null,
                'created_at' => $report->created_at->format('Y-m-d H:i'),
            ];
        });

    if ($reports->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد تقارير للمرحلة المتقدمة لهذه الفكرة.',
            'total_reports' => 0,
            'data' => [],
        ], 200);
    }

    return response()->json([
        'idea' => [
            'id'     => $idea->id,
            'title'  => $idea->title,
            'status' => $idea->status,
        ],
        'total_reports' => $reports->count(),
        'reports' => $reports,
    ]);
}

//عرض التقارير الخاصة ب launch_evaluation
public function ownerLaunchEvaluationReports(Request $request, $idea_id)
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'غير مصرح لك.'
        ], 403);
    }
    $idea = Idea::where('owner_id', $user->id)
        ->where('id', $idea_id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.',
        ], 404);
    }
    $reports = Report::where('idea_id', $idea_id)
        ->where('report_type', 'launch_evaluation') 
        ->with(['meeting:id,meeting_date,notes'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($report) {
            return [
                'report_id'        => $report->id,
                'report_type'      => $report->report_type,
                'evaluation_score' => $report->evaluation_score,
                'status'           => $report->status,
                'description'      => $report->description,
                'strengths'        => $report->strengths,
                'weaknesses'       => $report->weaknesses,
                'recommendations'  => $report->recommendations,
                'meeting' => $report->meeting ? [
                    'meeting_date' => $report->meeting->meeting_date,
                    'notes'        => $report->meeting->notes,
                ] : null,
                'created_at' => $report->created_at->format('Y-m-d H:i'),
            ];
        });

    if ($reports->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد تقارير تقييم الإطلاق لهذه الفكرة.',
            'total_reports' => 0,
            'data' => [],
        ], 200);
    }

    return response()->json([
        'idea' => [
            'id'     => $idea->id,
            'title'  => $idea->title,
            'status' => $idea->status,
        ],
        'total_reports' => $reports->count(),
        'reports' => $reports,
    ]);
}

//عرض التقارير ال post_launch_followup
public function ownerPostLaunchReportByFollowup(Request $request, $idea_id, $followup_id)
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    $idea = Idea::where('id', $idea_id)
        ->where('owner_id', $user->id)
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة أو لا تتبع لك.'
        ], 404);
    }

    $followup = PostLaunchFollowUp::with('launchRequest')
        ->where('id', $followup_id)
        ->first();

    if (
        !$followup ||
        $followup->launchRequest->idea_id !== $idea->id
    ) {
        return response()->json([
            'message' => 'المتابعة غير موجودة أو لا تتبع لهذه الفكرة.'
        ], 404);
    }

    $report = Report::where('idea_id', $idea->id)
        ->where('report_type', 'post_launch_followup')
        ->where('description', 'LIKE', '%' . $followup->followup_phase . '%')
        ->with(['meeting:id,meeting_date,notes'])
        ->first();

    if (!$report) {
        return response()->json([
            'message' => 'لا يوجد تقرير مطابق لهذه المتابعة حتى الآن.'
        ], 404);
    }
    return response()->json([
        'idea' => [
            'id'    => $idea->id,
            'title' => $idea->title,
        ],
        'followup' => [
            'id'     => $followup->id,
            'phase'  => $followup->followup_phase,
            'status' => $followup->status,
        ],
        'report' => [
            'id'                => $report->id,
            'status'            => $report->status,
            'evaluation_score'  => $report->evaluation_score,
            'description'       => $report->description,
            'strengths'         => $report->strengths,
            'weaknesses'        => $report->weaknesses,
            'recommendations'   => $report->recommendations,
            'meeting' => $report->meeting ? [
                'meeting_date' => $report->meeting->meeting_date,
                'notes'        => $report->meeting->notes,
            ] : null,
            'created_at' => $report->created_at->format('Y-m-d H:i'),
        ],
    ]);
}



}
