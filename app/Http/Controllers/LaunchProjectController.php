<?php

namespace App\Http\Controllers;

use App\Models\Funding;
use App\Models\Idea;
use App\Models\LaunchProject;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaunchProjectController extends Controller
{
public function markReadyForLaunch(Request $request, Idea $idea)
{
    $user = $request->user();
    if ($idea->owner_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية طلب إطلاق هذا المشروع.'
        ], 403);
    }
    $failedPhasesCount = $idea->ganttCharts()
        ->where('failure_count', '>=', 1)
        ->count();
    if ($failedPhasesCount >= 3) {
        return response()->json([
            'message' => "لا يمكن طلب الإطلاق لأن المشروع يحتوي على {$failedPhasesCount} مراحل فاشلة."
        ], 422);
    }
    $incompletePhases = $idea->ganttCharts()
        ->where('progress', '<', 100)
        ->count();
    if ($incompletePhases > 0) {
        return response()->json([
            'message' => "لا يمكن طلب الإطلاق، يوجد {$incompletePhases} مراحل غير مكتملة."
        ], 422);
    }
    $unevaluatedPhases = $idea->ganttCharts()
        ->whereNull('evaluation_score')
        ->count();
    if ($unevaluatedPhases > 0) {
        return response()->json([
            'message' => "لا يمكن طلب الإطلاق، يوجد {$unevaluatedPhases} مراحل غير مُقيّمة من اللجنة."
        ], 422);
    }
    $pendingLaunch = LaunchProject::where('idea_id', $idea->id)
        ->where('status', 'pending')
        ->first();
    if ($pendingLaunch) {
        return response()->json([
            'message' => 'يوجد طلب إطلاق قيد المراجعة حالياً.'
        ], 400);
    }
    $newVersion = LaunchProject::where('idea_id', $idea->id)->max('launch_version');
    $newVersion = ($newVersion ?? 0) + 1;
    $launch = LaunchProject::create([
        'idea_id' => $idea->id,
        'launch_version' => $newVersion,
        'status' => 'pending',
        'followup_status' => 'pending',
        'launch_date' => null,
    ]);
    $meeting = $idea->meetings()->create([
        'meeting_date' => now()->addDays(2),
        'notes' => "مناقشة طلب إطلاق الإصدار {$newVersion} للمشروع '{$idea->title}'",
        'requested_by' => 'owner',
        'type' => 'launch_request',
    ]);
    $committeeMembers = $idea->committee?->committeeMember ?? collect();
    foreach ($committeeMembers as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => 'طلب إطلاق مشروع',
            'message' => "تم تقديم طلب إطلاق الإصدار {$newVersion} لمشروع '{$idea->title}'.",
            'type' => 'launch_request',
            'is_read' => false,
        ]);
    }
    if ($request->has('request_funding') && $request->boolean('request_funding') === true) {
        $request->validate([
            'requested_amount' => 'required|numeric|min:1',
            'justification' => 'nullable|string|max:1000',
        ]);
          $funding = Funding::create([
            'idea_id' => $idea->id,
            'requested_amount' => $request->requested_amount,
            'justification' => $request->justification,
            'status' => 'requested',
        ]);
        foreach ($committeeMembers as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title' => 'طلب تمويل مشروع',
                'message' => "قدّم صاحب المشروع '{$idea->title}' طلب تمويل بقيمة {$request->requested_amount}.",
                'type' => 'funding_request',
                'is_read' => false,
            ]);
        }
    }
    return response()->json([
        'message' => "تم إرسال طلب إطلاق الإصدار {$newVersion} بنجاح.",
        'launch' => $launch,
        'meeting' => $meeting,
    ], 200);
}


public function committeeLaunchRequests(Request $request)//جلب طلبات الاطلاق للجنة 
{
    $user = $request->user();
    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'يجب أن تكون عضو لجنة لعرض طلبات الإطلاق.'
        ], 403);
    }
    $committeeId = $user->committeeMember->committee_id;
    $launchRequests = LaunchProject::with([
            'idea:id,title,committee_id,owner_id,status'
        ])
        ->where('status', 'pending')
        ->whereHas('idea', function ($query) use ($committeeId) {
            $query->where('committee_id', $committeeId);
        })
        ->orderByDesc('created_at')
        ->get();
    return response()->json([
        'message' => 'طلبات جاهزية الإطلاق الخاصة بلجنتك.',
        'data'    => $launchRequests
    ], 200);
}


public function committeeDecision(Request $request, LaunchProject $launch)
{
    $user = $request->user();
    if (!$user->committeeMember || $user->committeeMember->committee_id != $launch->idea->committee_id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لاتخاذ قرار حول إطلاق المشروع.'
        ], 403);
    }

    $request->validate([
        'decision' => 'required|in:approved,rejected',
        'notes' => 'nullable|string',
        'launch_date' => 'nullable|date', 
    ]);

    $idea = $launch->idea()->first();
    $meeting = $idea->meetings()
        ->where('type', 'launch_request')
        ->latest()
        ->first();

    if (!$meeting) {
        return response()->json([
            'message' => 'لا يوجد اجتماع خاص بطلب الإطلاق بعد.'
        ], 422);
    }

    if ($meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن اتخاذ القرار قبل موعد الاجتماع مع اللجنة.'
        ], 422);
    }
    $launchDate = $request->has('launch_date')
        ? Carbon::parse($request->launch_date)
        : now()->addHours(24);

    $launch->status = $request->decision;
    if ($request->decision === 'approved') {
        $launch->launch_date = $launchDate;
        $launch->status = 'approved';
        $launch->followup_status = 'ongoing';
    }
    $launch->save();
    $report = Report::create([
        'idea_id' => $idea->id,
        'committee_id' => $idea->committee_id,
        'meeting_id' => $meeting->id,
        'report_type' => 'launch',
        'description' => $request->decision === 'approved'
            ? "تمت الموافقة على إطلاق المشروع، وموعد الإطلاق: {$launchDate->format('Y-m-d H:i')}."
            : "تم رفض طلب إطلاق المشروع.",
        'status' => $request->decision,
        'recommendations' => $request->notes,
        'evaluation_score' => null,
        'strengths' => null,
        'weaknesses' => null,
    ]);
    Notification::create([
        'user_id' => $idea->owner->id,
        'title' => $request->decision === 'approved' ? 'موافقة على إطلاق مشروعك' : 'تم رفض طلب إطلاق مشروعك',
        'message' => $request->decision === 'approved'
            ? "تهانينا! تمت الموافقة على إطلاق مشروع '{$idea->title}'. سيتم الإطلاق بتاريخ: {$launchDate->format('Y-m-d H:i')}."
            : "نأسف، تم رفض طلب إطلاق مشروع '{$idea->title}'. ملاحظات اللجنة: {$request->notes}",
        'type' => $request->decision === 'approved' ? 'success' : 'warning',
        'is_read' => false,
    ]);

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

    $currentStage = "الإطلاق";
    $currentStageIndex = array_search($currentStage, $roadmapStages);
    $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;

    $idea->roadmap_stage = $currentStage;
    $idea->save();

    $roadmap = $idea->roadmap()->first();
    if ($roadmap) {
        $roadmap->update([
            'current_stage' => $currentStage,
            'stage_description' => "المرحلة الحالية: {$currentStage}",
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $roadmapStages[$currentStageIndex + 1] ?? 'لا توجد مراحل لاحقة',
        ]);
    }

    return response()->json([
        'message' => 'تم تسجيل قرار اللجنة بنجاح.',
        'launch' => $launch,
        'report' => $report,
    ]);
}


//عرض نتيجة طلب الاطلاق لصاحب الفكرة 
public function launchResult(Request $request, Idea $idea)
{
    $user = $request->user();
    if ($idea->owner_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لعرض نتيجة طلب الإطلاق لهذا المشروع.'
        ], 403);
    }
    $launch = LaunchProject::with(['followUps', 'idea'])
        ->where('idea_id', $idea->id)
        ->first();

    if (!$launch) {
        return response()->json([
            'message' => 'لا يوجد طلب إطلاق تم تقديمه لهذه الفكرة بعد.'
        ], 404);
    }
    $report = $idea->reports()
        ->where('report_type', 'launch')
        ->latest()
        ->first();
    return response()->json([
        'message' => 'نتيجة طلب إطلاق المشروع.',
        'data' => [
            'launch_status' => $launch->status,
            'launch_date' => $launch->launch_date,
            'followup_status' => $launch->followup_status,
            'committee_report' => $report ? [
                'description' => $report->description,
                'recommendations' => $report->recommendations,
                'status' => $report->status
            ] : null,
            'post_launch_followups' => $launch->followUps 
        ]
    ], 200);
}




}
