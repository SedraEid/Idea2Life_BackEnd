<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\ImprovementPlan;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
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

    $meeting = Meeting::create([
        'idea_id'      => $idea->id,
        'owner_id'     => $idea->ideaowner?->id,
        'committee_id' => $committeeId,
        'meeting_date' => now()->addDays(2),
        'notes'        => "اجتماع لمناقشة الإطلاق النهائي و لمراجعة جاهزية المشروع.",
        'requested_by' => 'committee',
        'type'         => 'final_launch',
    ]);

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
