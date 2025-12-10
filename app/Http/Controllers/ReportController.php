<?php

namespace App\Http\Controllers;

use App\Models\CommitteeMember;
use App\Models\Evaluation;
use App\Models\Idea;
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




public function evaluate(Request $request, Idea $idea)
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
            'committee_id'      => $idea->committee_id,
            'description'       => $validated['description'] ?? 'تقرير التقييم الأولي للفكرة.',
            'evaluation_score'  => $validated['evaluation_score'],
            'strengths'         => $validated['strengths'],
            'weaknesses'        => $validated['weaknesses'],
            'recommendations'  => $validated['recommendations'] ?? null,
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

    $currentStage = 'التقييم الأولي';
    $currentStageIndex = array_search($currentStage, $roadmapStages);
    $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;

    $idea->roadmap_stage = $currentStage;
    $idea->save();
    $roadmap = $idea->roadmap;
    if ($roadmap) {
        $roadmap->update([
            'current_stage'       => $currentStage,
            'stage_description'   => "تم تنفيذ التقييم الأولي للفكرة من قبل اللجنة",
            'progress_percentage' => $progressPercentage,
            'last_update'         => now(),
            'next_step'           => $this->getNextStep($idea->status),
        ]);
    }
    $report->update(['meeting_id' => $meeting->id]);
    $ideaOwner = $idea->ideaowner;
    if ($ideaOwner) {
        Notification::create([
            'user_id' => $ideaOwner->user_id,
            'title'   => 'تقرير التقييم الأولي متاح للمراجعة',
            'message' => "تم إصدار تقرير التقييم الأولي لفكرتك '{$idea->title}'. يرجى الاطلاع على ملاحظات اللجنة ونتيجة التقييم.",
            'type'    => 'initial_report_owner',
            'is_read' => false,
        ]);
    }
    $committeeMembers = $idea->committee->committeeMember()->where('user_id', '!=', $user->id)->get();
    foreach ($committeeMembers as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => "تم إنشاء تقرير تقييم أولي لفكرة '{$idea->title}'",
            'message' => "أصدر أحد أعضاء اللجنة تقرير التقييم الأولي للفكرة '{$idea->title}'. يمكنك الاطلاع عليه في لوحة التقارير.",
            'type'    => 'initial_report_committee',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message' => 'تم تقييم الفكرة وتحديث التقرير والاجتماع والتقييم بنجاح.',
        'idea'    => $idea,
        'report'  => $report,
    ]);
}
private function getNextStep($status)
{
    return match($status) {
        'approved'       => 'انتقل لمرحلة إعداد خطة العمل',
        'needs_revision' => 'تحسين الفكرة وإعادة التقييم',
        'rejected'       => 'الفكرة مرفوضة',
        default          => 'بانتظار التقييم',
    };
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
        "التخطيط المنهجي",
        "التقييم المتقدم قبل التمويل",
        "التمويل",
        "التنفيذ والتطوير",
        "الإطلاق",
        "المتابعة بعد الإطلاق",
        "استقرار المشروع وانفصاله عن المنصة",
    ];

    $currentStageName = "التقييم المتقدم قبل التمويل";
    $currentStageIndex = array_search($currentStageName, $roadmapStages);
    if ($request->score >= 80) {
        $nextStageName = $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة';
        $stageDescription = "تم اجتياز التقييم المتقدم بنجاح؛ الانتقال إلى المرحلة التالية: {$nextStageName}.";
        $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
    } elseif ($request->score >= 50) {
        $nextStageName = 'تحسين خطة العمل وإعادة التقديم';
        $stageDescription = "نتيجة التقييم المتقدم متوسطة؛ يرجى تحسين خطة العمل (BMC) وإعادة التقديم.";
        $progressPercentage = (($currentStageIndex + 0.5) / count($roadmapStages)) * 100;
    } else {
        $nextStageName = 'إعادة كتابة خطة العمل أو رفض الفكرة';
        $stageDescription = "نتيجة التقييم المتقدم منخفضة؛ خطة العمل رُفضت.";
        $progressPercentage = (($currentStageIndex + 0.2) / count($roadmapStages)) * 100;
    }

    $roadmap = $idea->roadmap;
    if ($roadmap) {
        $roadmap->update([
            'committee_id' => $idea->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => $currentStageName,
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStageName,
        ]);
    } else {
        $roadmap = Roadmap::create([
            'idea_id' => $idea->id,
            'committee_id' => $idea->committee_id,
            'owner_id' => $idea->owner_id,
            'current_stage' => $currentStageName,
            'stage_description' => $stageDescription,
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $nextStageName,
        ]);
    }

    $idea->update([
        'roadmap_stage' => $currentStageName,
    ]);

    $report = Report::updateOrCreate(
        [
            'idea_id' => $idea->id,
            'report_type' => 'advanced',
        ],
        [
            'committee_id' => $idea->committee_id,
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
        'report' => $report,
        'roadmap' => $roadmap,
        'meeting' => $meeting,
    ]);
}



}
