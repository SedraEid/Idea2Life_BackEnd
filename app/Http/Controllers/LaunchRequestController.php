<?php

namespace App\Http\Controllers;

use App\Models\Funding;
use App\Models\Idea;
use App\Models\LaunchRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaunchRequestController extends Controller
{
 public function requestLaunch(Request $request, $idea_id)
{
    $user = $request->user();
    $idea = Idea::with(['owner', 'committee.committeeMember', 'roadmap','ganttCharts'])->find($idea_id);

    if (!$idea) {
        return response()->json(['message' => 'الفكرة غير موجودة.'], 404);
    }

    if ($idea->owner->id !== $user->id) {
        return response()->json(['message' => 'ليس لديك صلاحية طلب الإطلاق لهذه الفكرة.'], 403);
    }

    foreach ($idea->ganttCharts as $gantt) {
        if (is_null($gantt->evaluation_score)) {
            return response()->json([
                'message' => "لا يمكن طلب الإطلاق لأن المرحلة '{$gantt->phase_name}' لم تُقَيَّم بعد من قبل اللجنة."
            ], 422);
        }
    }

    $minCompletion = 90;
    foreach ($idea->ganttCharts as $gantt) {
        if ($gantt->progress < $minCompletion) {
            return response()->json([
                'message' => "لا يمكن طلب الإطلاق لأن المرحلة '{$gantt->phase_name}' لم تصل إلى {$minCompletion}٪ من الإنجاز."
            ], 422);
        }
    }

    $existingLaunch = LaunchRequest::where('idea_id', $idea->id)
        ->whereIn('status', ['submitted', 'under_review'])
        ->first();

    if ($existingLaunch) {
        return response()->json([
            'message' => 'لا يمكنك تقديم طلب إطلاق جديد قبل مراجعة الطلب الحالي.',
            'existing_launch' => $existingLaunch
        ], 400);
    }

    $validated = $request->validate([
        'execution_steps'    => 'required|string|max:2000',
        'marketing_strategy' => 'required|string|max:2000',
        'risk_mitigation'    => 'required|string|max:2000',
        'founder_commitment' => 'required|boolean',
    ]);

    $launchRequest = LaunchRequest::create([
        'idea_id'            => $idea->id,
        'execution_steps'    => $validated['execution_steps'],
        'marketing_strategy' => $validated['marketing_strategy'],
        'risk_mitigation'    => $validated['risk_mitigation'],
        'founder_commitment' => $validated['founder_commitment'],
        'status'             => 'submitted',
    ]);

    if ($roadmap = $idea->roadmap) {
        $roadmap->update([
            'stage_description' => "طلب الإطلاق قدم وقيد المراجعة من قبل اللجنة."
        ]);
    }

    $meetingDate = now()->addDays(2); 
    $meeting = $idea->meetings()->create([
        'meeting_date' => $meetingDate, 
        'notes'        => "مناقشة طلب الإطلاق للفكرة '{$idea->title}'",
        'requested_by' => 'owner',
        'type'         => 'launch_request',
    ]);

    foreach ($idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => "طلب إطلاق جديد للفكرة '{$idea->title}'",
            'message' => "قدم صاحب الفكرة طلب الإطلاق. الاجتماع مقرر يوم {$meetingDate->format('Y-m-d')}. يرجى المراجعة واتخاذ القرار.",
            'type'    => 'launch_request',
            'is_read' => false,
        ]);
    }
    return response()->json([
        'message'        => 'تم تقديم طلب الإطلاق بنجاح.',
        'launch_request' => $launchRequest,
        'meeting'        => $meeting
    ], 201);
}


public function showPendingLaunchRequests(Request $request)//عرض طلبات الاطلاق للجنة 
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;

    if (!$committeeMember) {
        return response()->json([
            'message' => 'أنت غير مرتبط بأي لجنة.'
        ], 403);
    }
    $launchRequests = LaunchRequest::with(['idea:id,title', 'approver:id,name,email'])
        ->whereHas('idea', function($q) use ($committeeMember) {
            $q->where('committee_id', $committeeMember->committee_id);
        })
        ->whereIn('status', ['submitted', 'under_review'])
        ->get();

    return response()->json([
        'committee_id' => $committeeMember->committee_id,
        'pending_launch_requests' => $launchRequests,
    ]);
}


//تقييم طلب الاطلاق من قبل اللجنة 
public function evaluateLaunchRequest(Request $request, $launchRequestId)
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;
    if (!$committeeMember) {
        return response()->json(['message' => 'غير مصرح لك.'], 403);
    }

    $launchRequest = LaunchRequest::with(['idea.roadmap', 'idea.committee.committeeMember'])
        ->findOrFail($launchRequestId);

    if ($launchRequest->idea->committee_id !== $committeeMember->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية.'], 403);
    }

 $meeting = $launchRequest->idea->meetings()
    ->where('type', 'launch_request')
    ->orderBy('meeting_date', 'desc')
    ->first();

    if (!$meeting || $meeting->meeting_date->isFuture()) {
        return response()->json([
            'message' => 'لا يمكن تقييم طلب الإطلاق قبل عقد الاجتماع المخصص له.'
        ], 422);
    }

    $validated = $request->validate([
        'decision'        => 'required|in:approved,rejected,needs_revision',
        'strengths'       => 'nullable|string|max:3000',
        'weaknesses'      => 'nullable|string|max:3000',
        'recommendations' => 'required|string|max:3000',
        'committee_notes' => 'nullable|string|max:2000',
        'launch_date'     => 'nullable|date'
    ]);

    DB::beginTransaction();
    try {

        $launchRequest->update([
            'status'        => $validated['decision'],
            'committee_notes'=> $validated['committee_notes'] ?? null,
            'approved_by'   => $user->id,
            'approved_at'   => now(),
            'launch_date'   => $validated['launch_date'] ?? null,
        ]);

        Report::create([
            'idea_id'         => $launchRequest->idea_id,
            'meeting_id'      => $meeting->id,
            'report_type'     => 'launch_evaluation',
            'description'     => 'تقرير تقييم طلب الإطلاق من لجنة الحاضنة',
            'strengths'       => $validated['strengths'] ?? null,
            'weaknesses'      => $validated['weaknesses'] ?? null,
            'recommendations' => $validated['recommendations'],
            'evaluation_score'=> null,
            'status'          => $validated['decision'],
        ]);

        if ($validated['decision'] === 'approved') {
            $lastVersion = LaunchRequest::where('idea_id', $launchRequest->idea_id)->max('version') ?? 0;
            $launchRequest->update([
                'version' => $lastVersion + 1
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
            $index = array_search($currentStage, $roadmapStages);
            $progress = round((($index + 1) / count($roadmapStages)) * 100, 2);

            $launchRequest->idea->update([
                'status'        => 'approved_for_launch',
                'roadmap_stage' => $currentStage,
            ]);

            if ($roadmap = $launchRequest->idea->roadmap) {
                $roadmap->update([
                    'current_stage'       => $currentStage,
'stage_description' =>
'تمت الموافقة على طلب الإطلاق من اللجنة، والمشروع بانتظار موعد الإطلاق المحدد.',
                    'progress_percentage' => $progress,
                    'last_update'         => now(),
                    'next_step'           => 'الالتزام بتوصيات تقرير الإطلاق والمتابعة بعد الإطلاق',
                ]);
            }
        }

        DB::commit();

    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'حدث خطأ أثناء تقييم طلب الإطلاق.',
            'error'   => $e->getMessage()
        ], 500);
    }

    Notification::create([
        'user_id' => $launchRequest->idea->owner->id,
        'title'   => 'نتيجة تقييم طلب الإطلاق',
        'message' => 'تم تقييم طلب الإطلاق. يرجى الاطلاع على تقرير اللجنة والالتزام بالتوصيات.',
        'type'    => 'launch_evaluated',
        'is_read' => false,
    ]);

    foreach ($launchRequest->idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => 'تم تقييم طلب إطلاق',
            'message' => 'تم الانتهاء من تقييم طلب الإطلاق وتوثيق تقرير اللجنة.',
            'type'    => 'launch_evaluated',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => 'تم تقييم طلب الإطلاق وتوثيق تقرير اللجنة بنجاح.',
        'launch_request' => $launchRequest
    ]);
}


 public function requestFunding(Request $request, $idea_id)//طلب تمويل بعد الاطلاق 
    {
        $user = $request->user();
        $idea = Idea::with(['launchRequests', 'committee.committeeMember'])->findOrFail($idea_id);
        if ($idea->owner_id !== $user->id) {
            return response()->json(['message' => 'ليس لديك صلاحية تقديم طلب التمويل لهذه الفكرة.'], 403);
        }
        $approvedLaunch = $idea->launchRequests()
    ->whereIn('status', ['approved', 'launched'])
            ->latest()
            ->first();

        if (!$approvedLaunch) {
            return response()->json([
                'message' => 'لا يمكنك تقديم طلب تمويل إلا بعد الموافقة على طلب الإطلاق.'
            ], 422);
        }
        
        $validated = $request->validate([
            'requested_amount' => 'required|numeric|min:1',
            'justification'    => 'required|string|max:2000',
        ]);
$investorMember = $idea->committee
    ->committeeMember
    ->firstWhere('role_in_committee', 'investor');

if (!$investorMember) {
    return response()->json([
        'message' => 'لا يوجد مستثمر معرف ضمن اللجنة لهذه الفكرة.'
    ], 422);
}

$funding = Funding::create([
    'idea_id'          => $idea->id,
    'investor_id'      => $investorMember->id,  
    'requested_amount' => $validated['requested_amount'],
    'justification'    => $validated['justification'],
    'status'           => 'requested',
]);
        $meetingDate = now()->addDays(2); 
        $meeting = Meeting::create([
            'idea_id'      => $idea->id,
            'meeting_date' => $meetingDate,
            'notes'        => "اجتماع لمناقشة طلب تمويل الإطلاق للفكرة '{$idea->title}'",
            'requested_by' => 'owner',
            'type'         => 'funding_request',
        ]);
        foreach ($idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title'   => "طلب تمويل للإطلاق للفكرة '{$idea->title}'",
                'message' => "قدم صاحب الفكرة طلب تمويل للإطلاق. الاجتماع مقرر يوم {$meetingDate->format('Y-m-d')}. يرجى المراجعة واتخاذ القرار.",
                'type'    => 'funding_request',
                'is_read' => false,
            ]);
        }
        return response()->json([
            'message' => 'تم تقديم طلب التمويل بنجاح وتم إنشاء اجتماع لمناقشته مع اللجنة.',
            'funding' => $funding,
            'meeting' => $meeting
        ], 201);
    }

//عرض نتيجة طلب الاطلاق لصاحب الفكرة 
public function showLaunchDecision(Request $request, $idea_id)
{
    $user = $request->user();

    $idea = Idea::where('id', $idea_id)
                ->where('owner_id', $user->id)
                ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'الفكرة غير موجودة أو لا تخصك.'
        ], 404);
    }
    $launchRequests = LaunchRequest::where('idea_id', $idea->id)->get();

    if ($launchRequests->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد طلبات إطلاق لهذه الفكرة.'
        ], 200);
    }

    $data = $launchRequests->map(function($launchRequest) {
        $decisionText = match($launchRequest->status) {
            'submitted' => 'تم تقديم طلب الإطلاق وجاري مراجعته من قبل اللجنة.',
            'under_review' => 'طلب الإطلاق قيد المراجعة من قبل اللجنة.',
            'approved' => 'تمت الموافقة على طلب الإطلاق.',
            'rejected' => 'تم رفض طلب الإطلاق من قبل اللجنة.',
            'needs_revision' => 'طلب الإطلاق يحتاج إلى مراجعة من قبل صاحب الفكرة.',
            'launched' => 'تم إطلاق المشروع رسميًا.',
            'halted' => 'تم إيقاف المشروع بعد الإطلاق.',
            default => 'حالة غير معروفة.',
        };

        return [
            'id' => $launchRequest->id,
            'version' => $launchRequest->version,
            'launch_plan' => $launchRequest->launch_plan,
            'founder_commitment' => $launchRequest->founder_commitment,
            'committee_notes' => $launchRequest->committee_notes,
            'approved_at' => $launchRequest->approved_at?->format('Y-m-d H:i'),
            'launch_date' => $launchRequest->launch_date?->format('Y-m-d'),
            'status' => $launchRequest->status,
            'decision_text' => $decisionText,
        ];
    });

    return response()->json([
        'idea' => [
            'id' => $idea->id,
            'title' => $idea->title,
        ],
        'launch_requests' => $data
    ]);
}

//عرض طلبات الاطلاق لصاحب الفكرة 
public function myLaunchRequests(Request $request)
{
    $user = $request->user();
    $ideasIds = $user->ideas()->pluck('id');
    if ($ideasIds->isEmpty()) {
        return response()->json([
            'message' => 'ليس لديك أي أفكار لعرض طلبات الإطلاق.'
        ], 404);
    }
    $launchRequests = LaunchRequest::whereIn('idea_id', $ideasIds)
        ->with(['idea'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'تم جلب طلبات الإطلاق الخاصة بك بنجاح.',
        'launch_requests' => $launchRequests
    ], 200);
}



}
