<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Idea;
use App\Models\ImprovementPlan;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use App\Models\Task;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function ownerIdeaReports(Request $request, $idea_id)//جلب التقارير لصاحب الفكرة و للفكرة التي هو بها الان 
{
    $user = $request->user();
    $ideaOwner = $user->ideaOwner;
    if (!$ideaOwner) {
        return response()->json([
            'message' => 'هذا المستخدم لا يملك أي فكرة بعد.'
        ], 404);
    }
    $idea = $ideaOwner->ideas()->where('id', $idea_id)->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على هذه الفكرة أو أنها لا تتبع لك.',
        ], 404);
    }
    $reports = \App\Models\Report::where('idea_id', $idea_id)
        ->with([
            'idea:id,title,status',
            'committee:id,committee_name'
        ])
        ->orderBy('created_at', 'desc')
        ->get();

    if ($reports->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد تقارير لهذه الفكرة.',
            'total_reports' => 0,
            'data' => [],
        ], 200);
    }

    $formattedReports = $reports->map(function ($report) {
        return [
            'report_id' => $report->id,
            'report_type' => $report->report_type,
            'description' => $report->description,
            'evaluation_score' => $report->evaluation_score,
            'status' => $report->status,
            'idea' => [
                'id' => $report->idea->id,
                'title' => $report->idea->title,
                'status' => $report->idea->status,
            ],
            'committee' => $report->committee?->committee_name,
            'strengths' => $report->strengths,
            'weaknesses' => $report->weaknesses,
            'recommendations' => $report->recommendations,
            'created_at' => $report->created_at->format('Y-m-d H:i'),
        ];
    });

    return response()->json([
        'message' => 'تم جلب جميع التقارير الخاصة بهذه الفكرة.',
        'total_reports' => $formattedReports->count(),
        'data' => $formattedReports,
    ], 200);
}


public function advancedEvaluation(Request $request, Idea $idea)//تقييم خطة العمل
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
        ->where('committee_id', $idea->committee_id)
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

    $evaluation = Evaluation::updateOrCreate(
        [
            'idea_id' => $idea->id,
            'evaluation_type' => 'advanced',
        ],
        [
            'committee_id' => $idea->committee_id,
            'business_plan_id' => $businessPlan->id,
            'score' => $request->score,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'financial_analysis' => $request->financial_analysis,
            'risks' => $request->risks,
            'recommendation' => $request->recommendation,
            'comments' => $request->comments,
            'status' => 'completed',
        ]
    );
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

    $currentStageIndex = array_search("التقييم المتقدم قبل التمويل", $roadmapStages);

    if ($request->score >= 80) {
        $stageDescription = "تم اجتياز التقييم المتقدم بنجاح؛ الانتقال إلى مرحلة التمويل.";
        $nextStep = $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة';
        $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
    } elseif ($request->score >= 50) {
        $stageDescription = "نتيجة التقييم المتقدم متوسطة؛ يرجى تحسين خطة العمل (BMC) وإعادة التقديم.";
        $nextStep = "تحسين خطة العمل وإعادة التقديم";
        $progressPercentage = (($currentStageIndex + 0.5) / count($roadmapStages)) * 100;
    } else {
        $stageDescription = "نتيجة التقييم المتقدم منخفضة؛ خطة العمل رُفضت.";
        $nextStep = "إعادة كتابة خطة العمل أو رفض الفكرة";
        $progressPercentage = (($currentStageIndex + 0.2) / count($roadmapStages)) * 100;
    }

    $currentStage = $roadmapStages[$currentStageIndex];

    $roadmap = $idea->roadmap;
    if ($roadmap) {
        $roadmap->update([
            'committee_id' => $idea->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => $currentStage,
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStep,
        ]);
    } else {
        $roadmap = Roadmap::create([
            'idea_id' => $idea->id,
            'committee_id' => $idea->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => $currentStage,
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStep,
        ]);
    }

    $idea->update([
        'roadmap_stage' => $currentStage,
    ]);

    $report = Report::updateOrCreate(
        [
            'idea_id' => $idea->id,
            'report_type' => 'advanced',
        ],
        [
            'committee_id' => $idea->committee_id,
            'roadmap_id' => $roadmap->id,
            'meeting_id' => $meeting->id, 
            'description' => 'تقرير التقييم المتقدم الصادر عن اللجنة بعد مراجعة خطة العمل والاجتماع.',
            'evaluation_score' => $request->score,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'recommendations' => $request->recommendation,
            'status' => 'completed',
        ]
    );
Notification::create([
    'user_id' => $idea->ideaowner?->user_id, 
    'title' => 'تقرير تقييم جديد',
    'message' => 'تم إصدار تقرير تقييم متقدم جديد لفكرتك "' . $idea->title . '". يرجى الاطلاع عليه.',
    'type' => 'advance_report_owner',
    'is_read' => false,
]);

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
        'evaluation' => $evaluation,
        'report' => $report,
        'roadmap' => $roadmap,
        'meeting' => $meeting,
    ]);
}






public function updatePhaseReport(Request $request, Idea $idea, $gantt_id)//اصدار تقرير بعد انتهاء كل مرحلة من الغانت
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
                'title' => "خطة تحسين مطلوبة للفكرة '{$idea->title}'",
                'message' => "تم إصدار 3 تقارير سلبية على الأقل، يرجى تقديم خطة تحسين خلال أسبوعين.",
                'type' => 'improvement_plan_required',
                'is_read' => false,
            ]);
        }
    }

    Notification::create([
        'user_id' => $idea->ideaowner?->user_id,
        'title' => 'تقرير تقييم المرحلة تم تحديثه',
        'message' => 'تم تحديث تقرير تقييم المرحلة لفكرتك "' . $idea->title . '". يرجى الاطلاع عليه.',
        'type' => 'phase_evaluation_report_owner',
        'is_read' => false,
    ]);

    $this->checkIfProjectReadyForLaunch($idea);

    return response()->json([
        'message' => 'تم تحديث التقرير بنجاح.',
        'report' => $report,
        'meeting' => $meeting,
    ]);
}



private function checkIfProjectReadyForLaunch($idea)
{
    $hasPendingImprovement = $idea->improvementPlans()
        ->whereIn('status', ['pending', 'in_progress'])
        ->exists();

    if ($hasPendingImprovement) {
        return; 
    }

    $allPhasesCompleted = $idea->ganttCharts()
        ->where(function ($q) {
            $q->where('progress', '<', 100)
              ->orWhere('status', '!=', 'completed');
        })
        ->doesntExist();

    if (!$allPhasesCompleted) {
        return; 
    }

    $allTasksCompleted = Task::where('idea_id', $idea->id)
        ->where(function ($q) {
            $q->where('progress_percentage', '<', 100)
              ->orWhere('status', '!=', 'completed');
        })
        ->doesntExist();

    if (!$allTasksCompleted) {
        return; 
    }

    $committeeId = $idea->committee?->id;

     $meeting = Meeting::firstOrCreate(
        [
            'idea_id' => $idea->id,
            'type'    => 'final_launch'
        ],
        [
            'owner_id'     => $idea->ideaowner?->id,
            'committee_id' => $committeeId,
            'meeting_date' => now()->addDays(2),
            'notes'        => "اجتماع لمناقشة الإطلاق النهائي و لمراجعة جاهزية المشروع.",
            'requested_by' => 'committee',
        ]
    );

    $idea->update([
        'roadmap_stage' => "الإطلاق"
    ]);

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

    $currentStage = "الإطلاق";
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

    if ($idea->ideaowner) {
        Notification::create([
            'user_id' => $idea->ideaowner->user_id,
            'title' => "مبروك! مشروعك جاهز للإطلاق",
            'message' => "تم إنشاء اجتماع  مناقشة الإطلاق بعد اكتمال جميع المراحل والمهام.",
            'type' => 'project_ready_for_launch',
            'is_read' => false,
        ]);
    }

    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => "مشروع جاهز للإطلاق",
            'message' => "فكرة '{$idea->title}'  مناقشة جاهزية المشروع للإطلاق وتم إنشاء اجتماع نهائي.",
            'type' => 'project_ready_for_launch_committee',
            'is_read' => false,
        ]);
    }
}


}
