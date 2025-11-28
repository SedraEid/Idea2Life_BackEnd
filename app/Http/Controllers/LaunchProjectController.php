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





}
