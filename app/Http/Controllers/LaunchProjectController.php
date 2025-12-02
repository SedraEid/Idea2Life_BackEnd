<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\LaunchProject;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaunchProjectController extends Controller
{
  
public function markReadyForLaunch(Request $request, Idea $idea)
{
    $user = $request->user();
    if ($idea->ideaowner->user_id !== $user->id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية لإبلاغ اللجنة بأن المشروع جاهز للإطلاق.'
        ], 403);
    }
    $badPhasesCount = $idea->ganttCharts->where('failure_count', 1)->count();
    if ($badPhasesCount >= 3) {
        return response()->json([
            'message' => "لا يمكنك الإبلاغ عن جاهزية الإطلاق لأن المشروع يحتوي على {$badPhasesCount} مراحل سيئة."
        ], 422);
    }
    $incompletePhases = $idea->ganttCharts->where('progress', '<', 100)->count();
    if ($incompletePhases > 0) {
        return response()->json([
            'message' => "لا يمكنك الإبلاغ عن جاهزية الإطلاق لأن هناك {$incompletePhases} مرحلة لم تكتمل بنسبة 100%."
        ], 422);
    }
    $existingLaunch = LaunchProject::where('idea_id', $idea->id)->first();
    if ($existingLaunch) {
        if ($existingLaunch->status === 'pending') {
            return response()->json([
                'message' => 'لقد تم بالفعل إرسال طلب جاهزية الإطلاق وهو قيد الانتظار.'
            ], 400);
        } elseif ($existingLaunch->status === 'approved') {
            return response()->json([
                'message' => 'تمت الموافقة على إطلاق المشروع مسبقًا. المشروع مُطلق بالفعل.'
            ], 400);
        }
    }

    $launch = LaunchProject::create([
        'idea_id' => $idea->id,
        'status' => 'pending',
        'launch_date' => null,
    ]);
    $meeting = $idea->meetings()->create([
 'owner_id' => $idea->ideaowner->id,
         'committee_id' => $idea->committee_id,
        'meeting_date' => now()->addDays(2),
        'meeting_link' => null,
        'notes' => "مناقشة طلب إطلاق المشروع '{$idea->title}'",
        'requested_by' => 'owner',
        'type' => 'launch_request',
    ]);
 $committeeMembers = $idea->committee?->committeeMember ?? collect();
foreach ($committeeMembers as $member) {
    if($member->user_id) {
        Notification::create([
            'user_id' => $member->user_id,
            'title' => 'طلب جاهزية الإطلاق',
            'message' => "أبلغ صاحب المشروع '{$idea->title}' أن مشروعه جاهز للإطلاق. تم إنشاء اجتماع لمناقشته بتاريخ {$meeting->meeting_date}.",
            'type' => 'info',
            'is_read' => false,
        ]);
    }
}

    return response()->json([
        'message' => 'تم إرسال طلب جاهزية الإطلاق إلى اللجنة بنجاح، وتم إنشاء اجتماع لمناقشة الإطلاق.',
        'launch' => $launch,
        'meeting' => $meeting
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
    ]);

    $idea = $launch->idea;
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
    $launchDate = now()->addHours(24);
    $launch->status = $request->decision;

    if ($request->decision === 'approved') {
        $launch->launch_date = $launchDate;
    }
    $launch->save();
    $report = Report::create([
        'idea_id' => $idea->id,
        'committee_id' => $idea->committee_id,
        'meeting_id' => $meeting->id,
        'report_type' => 'launch',
        'description' => $request->decision === 'approved'
            ? "تمت الموافقة على إطلاق المشروع، وموعد الإطلاق خلال 24 ساعة."
            : "تم رفض طلب إطلاق المشروع.",
        'status' => $request->decision,
        'recommendations' => $request->notes,
        'evaluation_score' => null,
        'strengths' => null,
        'weaknesses' => null,
    ]);
    $ownerUserId = $idea->ideaowner->user_id;
    if ($request->decision === 'approved') {
        Notification::create([
            'user_id' => $ownerUserId,
            'title' => 'موافقة على إطلاق مشروعك',
            'message' =>
                "تهانينا! تمت الموافقة على إطلاق مشروع '{$idea->title}'. سيتم الإطلاق خلال 24 ساعة بتاريخ: {$launchDate}. 
                المنصة ستتابعك بعد الإطلاق لضمان استقرار مشروعك.",
            'type' => 'success',
            'is_read' => false,
        ]);
    } else {
        Notification::create([
            'user_id' => $ownerUserId,
            'title' => 'تم رفض طلب إطلاق مشروعك',
            'message' =>
                "نأسف، تم رفض طلب إطلاق مشروع '{$idea->title}'. ملاحظات اللجنة: {$request->notes}",
            'type' => 'warning',
            'is_read' => false,
        ]);
    }
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
    return response()->json([
        'message' => 'تم تسجيل قرار اللجنة بنجاح.',
        'launch' => $launch,
        'report' => $report,
    ]);
}


}
