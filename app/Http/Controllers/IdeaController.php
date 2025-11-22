<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Models\Committee;
use App\Models\Evaluation;
use App\Models\Idea;
use App\Models\IdeaOwner;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Roadmap;
use Illuminate\Http\Request;
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

    $user = $request->user();//
    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();
    if (!$ideaOwner) {
        return response()->json(['message' => 'المستخدم ليس مسجلاً كصاحب فكرة.'], 403);
    }

    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();

    $idea = Idea::create([
        'owner_id' => $ideaOwner->id,
        'title' => $request->title,
        'description' => $request->description,
        'problem' => $request->problem,
        'solution' => $request->solution,
        'target_audience' => $request->target_audience,
        'additional_notes' => $request->additional_notes,
        'status' => 'pending',
        'roadmap_stage' => 'مرحلة تقديم الفكرة',

    ]);

    $committee = Committee::doesntHave('ideas')->first();
    if (!$committee) {
        $committee = Committee::withCount('ideas')->orderBy('ideas_count', 'asc')->first();
    }
    $idea->committee_id = $committee->id;
    $idea->save();

    $existingRoadmap = Roadmap::where('owner_id', $ideaOwner->id)->first();

    if ($existingRoadmap) {
        $existingRoadmap->update([
            'idea_id' => $idea->id,
            'committee_id' => $committee->id,
            'current_stage' => 'التقييم الأولي',
            'stage_description' => 'تم بدء التقييم الأولي للفكرة من قبل اللجنة',
            'progress_percentage' => 0,
            'last_update' => now(),
            'next_step' => 'بانتظار نتائج التقييم الأولي',
        ]);
        $roadmap = $existingRoadmap;
    } else {
        $roadmap = $idea->roadmap()->create([
            'committee_id' => $committee->id,
            'owner_id' => $ideaOwner->id,
            'current_stage' => 'التقييم الأولي',
            'stage_description' => 'تم بدء التقييم الأولي للفكرة من قبل اللجنة',
            'progress_percentage' => 0,
            'last_update' => now(),
            'next_step' => 'بانتظار نتائج التقييم الأولي',
        ]);
    }
    return response()->json([
        'message' => 'تم تسجيل الفكرة، إسنادها للجنة، وإنشاء خارطة الطريق بنجاح!',
        'idea' => $idea,
        'committee' => $committee,
        'roadmap' => $roadmap
    ], 201);
}




public function update(Request $request, Idea $idea)//تعديل الفكرة بعد التقييم الضعيف
{
    $user = $request->user();

    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();
    if (!$ideaOwner || $ideaOwner->id !== $idea->owner_id) {
        return response()->json(['message' => 'ليس لديك صلاحية لتعديل هذه الفكرة.'], 403);
    }
    if ($idea->status === 'needs_revision') {
        $initialEvaluation = $idea->evaluations()
            ->where('evaluation_type', 'initial')
            ->latest()
            ->first();

        if (!$initialEvaluation || $initialEvaluation->score < 50 || $initialEvaluation->score >= 80) {
            return response()->json(['message' => 'لا يمكن تعديل الفكرة في هذه المرحلة.'], 403);
        }
    } elseif (in_array($idea->status, ['approved', 'rejected'])) {
        return response()->json(['message' => 'لا يمكن تعديل الفكرة بعد الموافقة أو الرفض.'], 403);
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
            'roadmap_stage' => 'بانتظار إعادة التقييم بعد التعديلات',
        ]);

        $idea->roadmap()->update([
            'stage_description' => 'تم تعديل الفكرة بناءً على ملاحظات اللجنة وهي بانتظار إعادة التقييم.',
            'roadmap_stage' => 'بانتظار إعادة التقييم بعد التعديلات',
            'last_update' => now(),
        ]);
    }

    return response()->json([
        'message' => 'تم تعديل الفكرة بنجاح.',
        'idea' => $idea,
    ]);
}





 public function evaluate(Request $request, Idea $idea)//تابع تقييم الفكرة الاولية 
{
    $user = $request->user();
    if (!$user->committeeMember || !$idea->committee_id || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تقييم هذه الفكرة.'], 403);
    }

    $request->validate([
        'evaluation_score' => 'required|integer|min:0|max:100',
        'description' => 'nullable|string',
        'strengths' => 'nullable|string',
        'weaknesses' => 'nullable|string',
        'recommendations' => 'nullable|string',
    ]);

    $report = $idea->reports()->where('report_type', 'initial')->first();
    $reportData = [
        'description' => $request->description,
        'evaluation_score' => $request->evaluation_score,
        'strengths' => $request->strengths,
        'weaknesses' => $request->weaknesses,
        'recommendations' => $request->recommendations,
        'status' => 'completed',
    ];

    if ($report) {
        $report->update($reportData);
    } else {
        $reportData = array_merge($reportData, [
            'idea_id' => $idea->id,
            'committee_id' => $idea->committee_id,
            'roadmap_id' => $idea->roadmap?->id,
            'report_type' => 'initial',
        ]);
        $report = Report::create($reportData);
    }

    if ($request->evaluation_score >= 80) {
        $idea->status = 'approved';
    } elseif ($request->evaluation_score >= 50) {
        $idea->status = 'needs_revision';
    } else {
        $idea->status = 'rejected';
    }
    $idea->initial_evaluation_score = $request->evaluation_score;

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

    $currentStageIndex = 0;

    if ($request->evaluation_score >= 80) {
        $currentStageIndex = 1;
    }
     $idea->roadmap_stage = $roadmapStages[$currentStageIndex];
     $idea->save();

    $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages) * 100);

    $roadmap = $idea->roadmap;
    if ($roadmap) {
        $roadmap->update([
            'current_stage' => $roadmapStages[$currentStageIndex],
            'stage_description' => 'تم تنفيذ التقييم الأولي للفكرة من قبل اللجنة',
            'progress_percentage' => $progressPercentage,
            'last_update' => now(),
            'next_step' => $this->getNextStep($idea->status),
        ]);
    }

    $meeting = $idea->meetings()->where('type', 'initial')->first();
    if (!$meeting) {
        $meeting = Meeting::create([
            'idea_id' => $idea->id,
            'owner_id' => $idea->owner_id,
            'committee_id' => $idea->committee_id,
            'meeting_date' => now()->addDays(2),
            'type' => 'initial',
            'requested_by' => 'committee',
            'meeting_link' => null,
            'notes' => null,
        ]);
    }
    $report->update(['meeting_id' => $meeting->id]);

    $evaluation = $idea->evaluations()->where('evaluation_type', 'initial')->first();
    $evalData = [
        'score' => $request->evaluation_score,
        'recommendation' => $request->recommendations,
        'comments' => $request->description,
        'strengths' => $request->strengths,
        'weaknesses' => $request->weaknesses,
        'status' => 'completed',
    ];
    if ($evaluation) {
        $evaluation->update($evalData);
    } else {
        $evalData = array_merge($evalData, [
            'idea_id' => $idea->id,
            'committee_id' => $idea->committee_id,
            'business_plan_id' => $idea->businessPlan?->id,
            'evaluation_type' => 'initial',
        ]);
        Evaluation::create($evalData);
    }

$ideaOwner = $idea->ideaowner;
if ($ideaOwner) {
    Notification::create([
        'user_id'    => $ideaOwner->user_id, 
        'title'      => 'تقرير التقييم الأولي متاح للمراجعة',
        'message'    => "تم إصدار تقرير التقييم الأولي لفكرتك '{$idea->title}'. يرجى الاطلاع على ملاحظات اللجنة ونتيجة التقييم.",
        'type'       => 'initial_report_owner',
        'is_read'    => false,
    ]);
}

if ($idea->committee && $idea->committee->committeeMember) {
    $committeeMembers = $idea->committee->committeeMember()->get();

    foreach ($committeeMembers as $member) {
        if ($member->user_id == $user->id) continue;

        Notification::create([
            'user_id'    => $member->user_id,    
            'title'      => "تم إنشاء تقرير تقييم أولي لفكرة '{$idea->title}'",
            'message'    => "أصدر أحد أعضاء اللجنة تقرير التقييم الأولي للفكرة '{$idea->title}'. يمكنك الاطلاع عليه في لوحة التقارير.",
            'type'       => 'initial_report_committee',
            'is_read'    => false,
        ]);
    }
}



    return response()->json([
        'message' => 'تم تقييم الفكرة وتحديث التقرير والاجتماع والتقييم بنجاح.',
        'idea' => $idea,
        'report' => $report,
        'meeting' => $meeting,
    ]);
}



private function getNextStep($status)
{
    return match($status) {
        'approved' => 'انتقل لمرحلة إعداد خطة العمل',
        'needs_revision' => 'تحسين الفكرة وإعادة التقييم',
        'rejected' => 'الفكرة مرفوضة',
        default => 'بانتظار التقييم',
    };
}




public function committeeIdeas(Request $request)
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json(['message' => 'ليس لديك صلاحية الوصول.'], 403);
    }

    $committeeId = $user->committeeMember->committee_id;
    $ideas = Idea::where('committee_id', $committeeId)->get();
    return response()->json([
        'committee_id' => $committeeId,
        'ideas' => $ideas
    ]);
}


public function getUserIdeasWithCommittee(Request $request)
{
    $user = $request->user();

    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();
    if (!$ideaOwner) {
        return response()->json([
            'message' => 'المستخدم ليس لديه حساب صاحب فكرة.'
        ], 404);
    }

    $ideas = Idea::where('owner_id', $ideaOwner->id)
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




public function getIdeasWithCommittee()
{
    $ideas = Idea::with([
        'committee.committeeMember.user',  
        'ideaowner.user',        
    ])->get();
    $data = $ideas->map(function ($idea) {
        return [
            'idea_id' => $idea->id,
            'title' => $idea->title,
            'description' => $idea->description,
            'status' => $idea->status,
            'idea_owner' => [
                'id' => $idea->ideaowner?->id,
                'name' => $idea->ideaowner?->user?->name,
                'email' => $idea->ideaowner?->user?->email,
            ],
            'committee' => [
                'id' => $idea->committee?->id,
                'name' => $idea->committee?->name,
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

    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();
    if (!$ideaOwner) {
        return response()->json([
            'message' => 'المستخدم ليس لديه أفكار بعد.'
        ], 404);
    }

    $ideas = Idea::where('owner_id', $ideaOwner->id)
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





public function getIdeaRoadmap(Request $request, Idea $idea)//جلب خارطة الطريق لكل فكرة
{
    $user = $request->user();

    $ideaOwner = $idea->ideaowner;
    $isOwner = $ideaOwner && $ideaOwner->user_id === $user->id;
    $isCommittee = $user->committeeMember && $user->committeeMember->committee_id === $idea->committee_id;

    if (!$isOwner && !$isCommittee) {
        return response()->json(['message' => 'ليس لديك صلاحية الوصول لخارطة الطريق.'], 403);
    }

    $roadmap = $idea->roadmap;

    if (!$roadmap) {
        return response()->json(['message' => 'لا توجد خارطة طريق لهذه الفكرة.'], 404);
    }

    return response()->json([
        'idea_id' => $idea->id,
        'title' => $idea->title,
        'roadmap' => [
            'current_stage' => $roadmap->current_stage,
            'description' => $roadmap->stage_description,
            'progress_percentage' => $roadmap->progress_percentage,
            'last_update' => $roadmap->last_update,
            'next_step' => $roadmap->next_step,
        ]
    ]);
}





public function upcomingMeetings(Request $request, $idea_id)//جلب الاجتماعات الخاصة بصاحب الفكرة 
{
    $user = $request->user();
    $ideaOwner = IdeaOwner::where('user_id', $user->id)->first();
    if (!$ideaOwner) {
        return response()->json([
            'message' => 'المستخدم ليس لديه أفكار بعد.'
        ], 404);
    }

    $idea = $ideaOwner->ideas()->where('id', $idea_id)->first();
    if (!$idea) {
        return response()->json([
            'message' => 'هذه الفكرة لا تتبع لك أو غير موجودة.',
        ], 404);
    }

    $meetings = Meeting::with(['idea:id,title', 'committee:id,committee_name'])
        ->where('owner_id', $ideaOwner->id)
        ->where('idea_id', $idea_id)
        ->where('meeting_date', '>=', now())
        ->orderBy('meeting_date', 'asc')
        ->get()
           ->map(function ($meeting) {
            $hoursLeft = $meeting->meeting_date->diffInHours(now());
            $isSoon = $hoursLeft <= 24;

            return [
                'id' => $meeting->id,
                'idea_title' => $meeting->idea?->title,
                'committee_name' => $meeting->committee?->committee_name,
                'meeting_date' => $meeting->meeting_date->format('Y-m-d H:i'),
                'meeting_link' => $meeting->meeting_link,
                'notes' => $meeting->notes,
                'requested_by' => $meeting->requested_by,
                'type' => $meeting->type,
                'hours_left' => $hoursLeft,
                'is_soon' => $isSoon,
            ];
        });

    return response()->json([
        'message' => 'تم جلب الاجتماعات القادمة لهذه الفكرة بنجاح.',
        'idea_id' => $idea_id,
        'upcoming_meetings' => $meetings
    ]);
}



public function committee_Ideas(Request $request) // عرض الأفكار مع الاجتماعات وصاحب الفكرة للجنة
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'أنت لست عضوًا في لجنة.'
        ], 403);
    }

    $committeeId = $user->committeeMember->committee_id;

    $ideas = \App\Models\Idea::with([
            'ideaowner.user', 
            'meetings'
        ])
        ->where('committee_id', $committeeId)
        ->get()
        ->map(function ($idea) {
            $owner = $idea->ideaowner?->user;

            return [
                'idea_id' => $idea->id,
                'title' => $idea->title,
                'description' => $idea->description,
                'status' => $idea->status,
                'meeting' => $idea->meetings->map(function ($m) {
                    return [
                        'meeting_id' => $m->id,
                        'meeting_date' => $m->meeting_date?->format('Y-m-d H:i'),
                        'meeting_link' => $m->meeting_link,
                        'notes' => $m->notes,
                        'requested_by' => $m->requested_by,
                        'type' => $m->type,
                    ];
                }),
                'idea_owner' => [
                    'name' => $owner?->name,
                    'email' => $owner?->email,
                    'phone' => $owner?->phone,
                    'profile_image' => $owner?->profile_image,
                    'bio' => $owner?->bio,
                    'user_type' => $owner?->role, 
                ],
            ];
        });

    return response()->json([
        'message' => 'تم جلب جميع الأفكار التي تشرف عليها اللجنة بنجاح.',
        'ideas' => $ideas,
    ]);
}





public function updateMeeting(Request $request, $meetingId)//تحديد رابط الاجتماع و الملاحظات من قبل اللجنة 
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json(['message' => 'أنت لست عضو لجنة.'], 403);
    }

    $meeting = Meeting::find($meetingId);
    if (!$meeting) {
        return response()->json(['message' => 'الاجتماع غير موجود.'], 404);
    }

    if ($meeting->committee_id != $user->committeeMember->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية تعديل هذا الاجتماع.'], 403);
    }

    $validatedData = $request->validate([
        'meeting_link' => 'nullable|url',
        'notes' => 'nullable|string',
        'meeting_date' => 'nullable|date|after_or_equal:today',
    ]);

    $meeting->update([
        'meeting_link' => $validatedData['meeting_link'] ?? $meeting->meeting_link,
        'notes' => $validatedData['notes'] ?? $meeting->notes,
        'meeting_date' => $validatedData['meeting_date'] ?? $meeting->meeting_date, 
    ]);

    return response()->json([
        'message' => 'تم تحديث رابط الاجتماع والملاحظات ',
        'meeting' => $meeting,
    ]);
}



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




 



}







