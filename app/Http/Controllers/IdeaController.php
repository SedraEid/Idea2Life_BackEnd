<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\Committee;
use App\Models\Idea;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IdeaController extends Controller
{
    //اضافة فكرة 
 public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'problem' => 'nullable|string',
        'solution' => 'nullable|string',
        'target_audience' => 'nullable|string',
        'additional_notes' => 'nullable|string',
        'terms_accepted' => 'required|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    if (!$request->terms_accepted) {
        return response()->json(['message' => 'يجب الموافقة على الشروط والأحكام قبل الإرسال.'], 403);
    }

    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json(['message' => 'المستخدم ليس مسجلاً كصاحب فكرة.'], 403);
    }
    $idea = Idea::create([
        'owner_id' => $user->id,
        'title' => $request->title,
        'description' => $request->description,
        'problem' => $request->problem,
        'solution' => $request->solution,
        'target_audience' => $request->target_audience,
        'additional_notes' => $request->additional_notes,
        'status' => 'submitted',
        'roadmap_stage' => 'Idea Submission',
    ]);
    $committee = Committee::doesntHave('ideas')->first();
    if (!$committee) {
        $committee = Committee::withCount('ideas')->orderBy('ideas_count', 'asc')->first();
    }
    $idea->committee_id = $committee->id;
    $idea->save();
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
$initialStage = $roadmapStages[0];
$initialStageIndex = 0;
$progressPercentage = (($initialStageIndex + 1) / count($roadmapStages)) * 100;
$nextStep = $initialStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$initialStageIndex + 1]['name'] 
    : null;

    $nextActor = $initialStageIndex + 1 < count($roadmapStages) 
    ? $roadmapStages[$initialStageIndex + 1]['actor'] 
    : null;
$stageDescription = "Stage executed by: " . $initialStage['actor'] . 
                    ($nextStep ? " | Next stage: $nextStep (executed by: $nextActor)" : "");
$roadmap = Roadmap::create([
    'idea_id'           => $idea->id,
    'current_stage'     => $initialStage['name'],
    'stage_description' => $stageDescription,
    'progress_percentage'=> $progressPercentage,
    'last_update'       => now(),
    'next_step'         => $nextStep,
]);
  $initialMeetingDate = now()->addDays(2);
    $meeting = Meeting::create([
        'idea_id' => $idea->id,
        'meeting_date' => $initialMeetingDate,
        'type' => 'initial',
        'requested_by' => 'committee',
        'meeting_link' => null,
        'notes' => 'الاجتماع الأولي لتقييم الفكرة بعد التسجيل',
    ]);
    $admins = User::where('role', 'admin')->get();
foreach ($admins as $admin) {
    Notification::create([
        'user_id' => $admin->id,
        'title'   => 'مطلوب اجتماع لجنة',
        'message' => "تم تسجيل فكرة جديدة بعنوان '{$idea->title}'. يرجى إنشاء اجتماع لجنة لتحديد المسؤول عن إدخال البيانات.",
        'type'    => 'committee_meeting_request',
        'is_read' => false,
    ]);
}
    return response()->json([
        'message' => 'تم تسجيل الفكرة، إسنادها للجنة، وإنشاء خارطة الطريق بنجاح!',
        'idea' => $idea,
        'committee' => $committee,
        'roadmap' => $roadmap,
        'meeting' => $meeting
    ], 201);
}





public function update(Request $request, Idea $idea) // تعديل الفكرة بعد التقييم الضعيف
{
    $user = $request->user();
      if ($user->id !== $idea->owner_id || $user->role !== 'idea_owner') {
        return response()->json(['message' => 'ليس لديك صلاحية لتعديل هذه الفكرة.'], 403); 
    }
    if ($idea->status === 'needs_revision') {
        $initialReport = $idea->reports()
            ->latest()
            ->first();
        if (!$initialReport) {
            return response()->json(['message' => 'لا يمكن تعديل الفكرة في هذه المرحلة.'], 403);
        }
    }if ($idea->status === 'approved') {
    return response()->json(['message' => 'لا يمكن تعديل الفكرة بعد الموافقة.'], 403);
}
if ($idea->status === 'rejected') {
    return response()->json(['message' => 'تم رفض هذه الفكرة بشكل نهائي ولا يمكن تعديلها. الرجاء تقديم فكرة جديدة إذا كنت ترغب بالمتابعة.'], 403);
}
    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|string|max:255',
        'description' => 'sometimes|string',
        'problem' => 'nullable|string',
        'solution' => 'nullable|string',
        'target_audience' => 'nullable|string',
        'additional_notes' => 'nullable|string',
    ]);
    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    $idea->update($validator->validated());
    if ($idea->status === 'needs_revision') {
        $idea->update([
        'roadmap_stage' => 'Awaiting re-evaluation after revisions',
        ]); }
    return response()->json([
        'message' => 'تم تعديل الفكرة بنجاح.',
        'idea' => $idea,
    ]);
}

public function committeeIdeasFullDetailsClean(Request $request)
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'ليس لديك صلاحية'
        ], 403);
    }

    $committeeId = $user->committeeMember->committee_id;

    $ideas = Idea::where('committee_id', $committeeId)
        ->with([
            'owner:id,name,email',
            'roadmap',
        ])
        ->get();

    return response()->json([
        'committee_id' => $committeeId,
        'ideas' => $ideas
    ]);
}


public function getUserIdeasWithCommittee(Request $request)//يعرض اللجنة و الاعضاء التي تشرف على فكرة لصاحب الفكرة
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'المستخدم ليس لديه حساب صاحب فكرة.'
        ], 404);
    }
    $ideas = Idea::where('owner_id', $user->id)
        ->with(['committee.committeeMember.user']) 
        ->get();

    if ($ideas->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد أفكار مملوكة لهذا المستخدم.'
        ], 404);
    }
    $data = $ideas->map(function($idea) {
        $committee = $idea->committee;
        return [
            'idea_id' => $idea->id,
            'idea_title' => $idea->title,
            'committee' => $committee ? [
                'id' => $committee->id,
                'name' => $committee->committee_name,
                'members' => $committee->committeeMember->map(function($member) {
                    return [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'role_in_committee' => $member->role_in_committee
                    ];
                })
            ] : null
        ];
    });
    return response()->json([
        'ideas' => $data
    ]);
}




public function getIdeasWithCommittee()//جلب كل الافكار مع اللجنة المشرفة على كل فكرة
{
    $ideas = Idea::with([
         'committee.committeeMember.user',  
        'owner',          
    ])->get();
    $data = $ideas->map(function ($idea) {
        return [
            'idea_id' => $idea->id,
            'title' => $idea->title,
            'description' => $idea->description,
            'status' => $idea->status,
            'committee' => [
                'id' => $idea->committee?->id,
                'name' => $idea->committee?->committee_name,
                'committeeMember' => $idea->committee?->committeeMember?->map(function ($committeeMember) {
                    return [
                        'id' => $committeeMember->id,
                        'user_id' => $committeeMember->user_id,
                        'name' => $committeeMember->user?->name,
                        'email' => $committeeMember->user?->email,
                        'role' => $committeeMember->role ?? 'عضو لجنة',
                    ];
                }),
            ],
        ];
    });

    return response()->json([
        'message' => 'تم جلب جميع الأفكار مع تفاصيل اللجنة والأعضاء بنجاح.',
        'ideas' => $data
    ], 200);
}



public function myIdeas(Request $request) // تابع جلب افكار صاحب الفكرة مع أعضاء اللجنة
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'المستخدم ليس لديه أفكار بعد.'
        ], 404);
    }
    $ideas = Idea::where('owner_id', $user->id)
        ->with([
            'committee.committeeMember.user', 
            'roadmap'
        ])
        ->get();
    $data = $ideas->map(function ($idea) {
        return [
            'id' => $idea->id,
            'title' => $idea->title,
            'description' => $idea->description,
            'status' => $idea->status,
            'initial_evaluation_score' => $idea->initial_evaluation_score,
            'committee' => $idea->committee ? [
                'id' => $idea->committee->id,
                'name' => $idea->committee->committee_name,
                'members' => $idea->committee->committeeMember->map(function ($member) {
                    return [
                        'id' => $member->user->id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'role_in_committee' => $member->role_in_committee
                    ];
                })
            ] : null,
            'roadmap_stage' => $idea->roadmap?->current_stage ?? null,
            'created_at' => $idea->created_at->format('Y-m-d H:i'),
        ];
    });
    return response()->json([
        'message' => 'تم جلب جميع أفكار المستخدم بنجاح.',
        'ideas' => $data
    ]);
}






}







