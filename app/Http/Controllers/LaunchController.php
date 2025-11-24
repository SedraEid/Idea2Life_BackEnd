<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\LaunchProject;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LaunchController extends Controller
{
    public function approveLaunch(Request $request, $ideaId)
{
    $user = $request->user();
    $idea = Idea::findOrFail($ideaId);

    if (!$user->committeeMember || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية إنشاء تقرير الإطلاق لهذه الفكرة.'
        ], 403);
    }
    $meeting = $idea->meetings()
        ->where('type', 'final_launch')
        ->first();

    if (!$meeting) {
        return response()->json([
            'message' => 'لا يوجد اجتماع الإطلاق النهائي تم عقده بعد.'
        ], 400);
    }

   
    if ($meeting->meeting_date > now()) {
        return response()->json([
            'message' => 'لا يمكن تعديل التقرير قبل انتهاء الاجتماع.',
            'meeting_date' => $meeting->meeting_date->toDateTimeString()
        ], 400);
    }
    $validated = $request->validate([
        'description'      => 'required|string',
        'strengths'        => 'nullable|string',
        'weaknesses'       => 'nullable|string',
        'recommendations'  => 'nullable|string',
        'evaluation_score' => 'required|integer|min:0|max:100',
        'approved'         => 'required|boolean',
    ]);

    if (!$validated['approved']) {
        return response()->json([
            'message' => "لا يمكن إنشاء سجل الإطلاق لأن اللجنة لم توافق."
        ], 400);
    }

    $committeeId = $idea->committee?->id;
    $report = Report::create([
        'idea_id'         => $idea->id,
        'meeting_id'      => $meeting->id,
        'committee_id'    => $committeeId,
        'report_type'     => 'final_launch',
        'status'          => 'approved',
        'description'     => $validated['description'],
        'strengths'       => $validated['strengths'] ?? null,
        'weaknesses'      => $validated['weaknesses'] ?? null,
        'recommendations' => $validated['recommendations'] ?? null,
        'evaluation_score'=> $validated['evaluation_score'],
        'roadmap_id'      => $idea->roadmap?->id,
    ]);

    $launch = LaunchProject::create([
        'idea_id'     => $idea->id,
        'status'      => 'launched',
        'launch_date' => now(),
    ]);


    Notification::create([
        'user_id' => $idea->ideaowner?->id,
        'idea_id' => $idea->id,
        'title'   => "تم إطلاق مشروعك بنجاح",
        'message' => "وافقت لجنة الحاضنة على إطلاق مشروعك بشكل رسمي. بالتوفيق!",
        'type'    => 'launch_approved',
    ]);

    return response()->json([
        'message' => "تم إنشاء تقرير اللجنة وتم إطلاق المشروع بنجاح.",
        'report'  => $report,
        'launch'  => $launch
    ]);
}

   public function launchedProjects()//عرض المشاريع التي تم نشرها بالمنصة
    {
        $projects = LaunchProject::with('idea') 
            ->where('status', 'launched')
            ->get()
            ->map(function($launch) {
                return [
                    'project_name' => $launch->idea->title,
                    'idea_id'      => $launch->idea->id,
               'start_date'   => Carbon::parse($launch->idea->created_at)->format('d-m-Y'),
        'launch_date'  => $launch->launch_date ? Carbon::parse($launch->launch_date)->format('d-m-Y') : null,
                    'status'       => $launch->status,
                ];
            });

        return response()->json([
            'launched_projects' => $projects,
        ]);
    }


}
