<?php

namespace App\Http\Controllers;

use App\Models\LaunchProject;
use App\Models\Notification;
use App\Models\PostLaunchFollowUp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostLaunchFollowupController extends Controller
{
public function getMyPostLaunchFollowups(Request $request)//جلب المتابعات بعد الالطلاق لصاحب الفكرة 
{
    $user = $request->user();

    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'المستخدم ليس صاحب فكرة.'
        ], 403);
    }
    $launchRequests = $user->ideas()
        ->with(['launchRequests.postLaunchFollowups.reviewer'])
        ->get()
        ->flatMap(function($idea) {
            return $idea->launchRequests->flatMap(function($launch) use ($idea) {
                return $launch->postLaunchFollowups->map(function($followup) use ($idea, $launch) {
                    return [
                        'idea_id' => $idea->id,
                        'idea_title' => $idea->title,
                        'launch_request_id' => $launch->id,
                        'followup_id' => $followup->id,
                        'phase' => $followup->followup_phase,
                        'scheduled_date' => $followup->scheduled_date,
                        'status' => $followup->status,
                        'performance_status' => $followup->performance_status,
                        'committee_decision' => $followup->committee_decision,
                        'reviewed_by' => $followup->reviewer ? [
                            'id' => $followup->reviewer->id,
                            'name' => $followup->reviewer->name,
                        ] : null,
                    ];
                });
            });
        });

    return response()->json([
        'message' => 'تم جلب جميع المتابعات بعد الإطلاق.',
        'post_launch_followups' => $launchRequests,
    ]);
}

 public function getCommitteePostLaunchFollowups(Request $request)//جلب المتابعات بعد الالطلاق للجنة 
    {
        $user = $request->user();
        if (!$user->committeeMember) {
            return response()->json([
                'message' => 'المستخدم ليس عضو لجنة.'
            ], 403);
        }
        $committeeId = $user->committeeMember->committee_id;
        $followups = PostLaunchFollowup::whereHas('launchRequest.idea', function ($q) use ($committeeId) {
            $q->where('committee_id', $committeeId);
        })->with(['launchRequest.idea.owner'])
          ->orderBy('scheduled_date', 'asc')
          ->get();
        return response()->json([
            'message' => 'تم جلب جميع المتابعات بعد الإطلاق للأفكار المشرفة عليها اللجنة.',
            'followups' => $followups
        ]);
    }

    //تابع التقييم الخاص بالمتباعة بعد الاطلاق للجنة 
       public function evaluateFollowup(Request $request, $followupId)
    {
        $user = $request->user();
        if (!$user->committeeMember) {
            return response()->json(['message' => 'ليس لديك صلاحية لتقييم المتابعة.'], 403);
        }
        $followup = PostLaunchFollowUp::find($followupId);
        if (!$followup) {
            return response()->json(['message' => 'المتابعة المطلوبة غير موجودة.'], 404);
        }
        if (Carbon::now()->lt($followup->scheduled_date)) {
            return response()->json([
                'message' => 'لا يمكن تقييم المتابعة قبل تاريخها المحدد.'
            ], 400);
        }
    $validator = Validator::make($request->all(), [
    'performance_status' => 'required|string|in:excellent,stable,at_risk,failing',
    'committee_decision' => 'required|string|in:continue,extra_support,pivot_required,terminate,graduate',
    'marketing_support_given' => 'required|boolean',
    'product_issue_detected' => 'required|boolean',
    'actions_taken' => 'nullable|string',
    'committee_notes' => 'nullable|string',
]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
$status = $request->product_issue_detected ? 'issue_detected' : 'done';
$followup->update(array_merge(
    $validator->validated(),
    [
        'reviewed_by' => $user->id,
        'status'      => $status
    ]
));
        return response()->json([
            'message' => 'تم تقييم متابعة المشروع بنجاح.',
            'followup' => $followup
        ]);
    }


}
