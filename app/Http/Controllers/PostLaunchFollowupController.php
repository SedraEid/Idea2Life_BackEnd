<?php

namespace App\Http\Controllers;

use App\Jobs\DistributeProfitsJob;
use App\Models\Idea;
use App\Models\LaunchProject;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\PostLaunchFollowUp;
use App\Models\ProfitDistribution;
use App\Models\Report;
use App\Models\Roadmap;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostLaunchFollowupController extends Controller
{
public function getMyIdeaPostLaunchFollowups(Request $request, $idea_id)
{
    $user = $request->user();
    if ($user->role !== 'idea_owner') {
        return response()->json([
            'message' => 'المستخدم ليس صاحب فكرة.'
        ], 403);
    }

    $idea = Idea::where('id', $idea_id)
        ->where('owner_id', $user->id)
        ->with([
            'launchRequests.postLaunchFollowups.reviewer'
        ])
        ->first();

    if (!$idea) {
        return response()->json([
            'message' => 'لم يتم العثور على الفكرة أو لا تملك صلاحية الوصول إليها.'
        ], 404);
    }

    $followups = $idea->launchRequests
        ->flatMap(function ($launch) use ($idea) {
            return $launch->postLaunchFollowups->map(function ($followup) use ($idea, $launch) {

                return [
                    'idea' => [
                        'id'    => $idea->id,
                        'title' => $idea->title,
                    ],

                    'launch_request_id' => $launch->id,

                    'followup' => [
                        'id'             => $followup->id,
                        'phase'          => $followup->followup_phase,
                        'scheduled_date' => $followup->scheduled_date,
                        'status'         => $followup->status,

                        'active_users' => $followup->active_users,
                        'revenue'      => $followup->revenue,
                        'growth_rate'  => $followup->growth_rate,

                        'performance_status' => $followup->performance_status,
                        'risk_level'         => $followup->risk_level,
                        'risk_description'   => $followup->risk_description,

                        'committee_decision'   => $followup->committee_decision,

                        'is_stable'        => $followup->is_stable,
                        'graduation_date' => $followup->graduation_date,
                        'marketing_support_given' => $followup->marketing_support_given,
'product_issue_detected'  => $followup->product_issue_detected,
'profit_distributed'      => $followup->profit_distributed,
'owner_acknowledged'      => $followup->owner_acknowledged,
'owner_response'  => $followup->owner_response,


                        'reviewed_by' => $followup->reviewer ? [
                            'id'   => $followup->reviewer->id,
                            'name' => $followup->reviewer->name,
                        ] : null,
                    ],
                ];
            });
        })
        ->values();

    return response()->json([
        'message' => 'تم جلب متابعات ما بعد الإطلاق للفكرة بنجاح.',
        'idea' => [
            'id'    => $idea->id,
            'title' => $idea->title,
        ],
        'total_followups' => $followups->count(),
        'data' => $followups,
    ]);
}


 public function getCommitteePostLaunchFollowups(Request $request)
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
        })
        ->with([
            'launchRequest.idea.owner:id,name',
            'reviewer:id,name',
        ])
        ->orderBy('scheduled_date', 'asc')
        ->get()
        ->map(function ($followup) {

            $idea   = $followup->launchRequest->idea;
            $owner  = $idea->owner;

            return [
                'idea' => [
                    'id'    => $idea->id,
                    'title' => $idea->title,
                    'owner' => [
                        'id'   => $owner->id,
                        'name' => $owner->name,
                    ],
                ],

                'followup' => [
                    'id'             => $followup->id,
                    'phase'          => $followup->followup_phase,
                    'scheduled_date' => $followup->scheduled_date,
                    'status'         => $followup->status,

                    'active_users' => $followup->active_users,
                    'revenue'      => $followup->revenue,
                    'growth_rate'  => $followup->growth_rate,

                    'performance_status' => $followup->performance_status,
                    'risk_level'         => $followup->risk_level,
                    'risk_description'   => $followup->risk_description,

                    'committee_decision'   => $followup->committee_decision,


                    'marketing_support_given' => $followup->marketing_support_given,
                    'product_issue_detected'  => $followup->product_issue_detected,

                    'actions_taken'  => $followup->actions_taken,
                    'committee_notes' => $followup->committee_notes,
                    'owner_acknowledged'      => $followup->owner_acknowledged,
'owner_response'  => $followup->owner_response,

                    'is_stable'        => $followup->is_stable,
                    'graduation_date' => $followup->graduation_date,

                    'reviewed_by' => $followup->reviewer ? [
                        'id'   => $followup->reviewer->id,
                        'name' => $followup->reviewer->name,
                    ] : null,
                ],
            ];
        });

    return response()->json([
        'message' => 'تم جلب جميع متابعات ما بعد الإطلاق للأفكار المشرفة عليها اللجنة.',
        'total_followups' => $followups->count(),
        'data' => $followups,
    ]);
}

//صاحب الفكرة يملا الحقول المطلوبة عند موعد كل متابعة
public function updateFollowupByOwner(Request $request, $followupId)
{
    $user = $request->user();

    $followup = PostLaunchFollowup::with([
        'launchRequest.idea.owner', 
        'launchRequest.idea.committee.committeeMember'
    ])->findOrFail($followupId);
    if ($followup->launchRequest->idea->owner->id !== $user->id) {
        return response()->json([
            'message' => 'غير مصرح لك بتحديث هذه المتابعة.'
        ], 403);
    }
    if (now()->lt($followup->scheduled_date)) {
        return response()->json([
            'message' => 'لا يمكن تحديث هذه المتابعة قبل موعدها المحدد.'
        ], 403);
    }
    if ($followup->status === 'done') {
        return response()->json([
            'message' => 'لا يمكن تعديل متابعة منتهية.'
        ], 403);
    }

    $data = $request->validate([
        'active_users'       => 'nullable|integer|min:0',
        'revenue'            => 'nullable|numeric|min:0',
        'growth_rate'        => 'nullable|numeric|min:-100|max:100',
    ]);

    $followup->update($data);
    if ($followup->launchRequest->idea->committee) {
        foreach ($followup->launchRequest->idea->committee->committeeMember as $member) {
            Notification::create([
                'user_id' => $member->user_id,
                'title'   => "متابعة بعد الإطلاق محدثة من صاحب الفكرة",
                'message' => "قام صاحب الفكرة '{$user->name}' بتعبئة متطلبات مرحلة المتابعة للفكرة '{$followup->launchRequest->idea->title}'. يرجى مراجعة البيانات.",
                'type'    => 'post_launch_followup_owner_filled',
                'is_read' => false,
            ]);
        }
    }

    return response()->json([
        'message' => 'تم تحديث بيانات المتابعة من قبل صاحب الفكرة، وتم إرسال إشعار للجنة.',
        'followup' => $followup
    ]);
}

public function committeeSubmitFollowup(Request $request, $followup_id)
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'المستخدم ليس عضو لجنة.'
        ], 403);
    }

    $followup = PostLaunchFollowUp::with(['launchRequest.idea.roadmap', 'launchRequest.idea.committee.committeeMember'])->findOrFail($followup_id);
    $idea = $followup->launchRequest->idea;

    if ($idea->committee_id !== $user->committeeMember->committee_id) {
        return response()->json([
            'message' => 'ليس لديك صلاحية تعديل هذه المتابعة.'
        ], 403);
    }

    $startOfDay = Carbon::today();
    $endOfDay = Carbon::tomorrow()->subSecond();
    $meeting = Meeting::where('idea_id', $idea->id)
        ->where('type', 'post_launch_followup')
        ->whereBetween('meeting_date', [$startOfDay, $endOfDay])
        ->first();

    if (!$meeting) {
        return response()->json([
            'message' => 'لا يمكن تقييم المتابعة إلا بعد عقد اجتماع متابعة بعد الإطلاق اليوم لهذه الفكرة.'
        ], 400);
    }

    foreach (['active_users', 'revenue', 'growth_rate'] as $field) {
        if (is_null($followup->{$field})) {
            return response()->json([
                'message' => "صاحب الفكرة لم يملأ الحقل المطلوب: $field بعد."
            ], 400);
        }
    }

    $data = $request->validate([
        'evaluation_score'       => 'nullable|numeric|min:0|max:100',
        'strengths'              => 'nullable|string',
        'weaknesses'             => 'nullable|string',
        'recommendations'        => 'nullable|string',
        'performance_status'     => 'required|in:excellent,stable,at_risk,failing',
        'risk_level'             => 'nullable|in:low,medium,high',
        'risk_description'       => 'nullable|string',
        'committee_decision'     => 'required|in:continue,extra_support,pivot_required,terminate,graduate',
        'actions_taken'          => 'nullable|string',
        'committee_notes'        => 'nullable|string',
        'marketing_support_given'=> 'nullable|boolean',
        'product_issue_detected' => 'nullable|boolean',
        'is_stable'              => 'nullable|boolean',
        'graduation_date'        => 'nullable|date',
    ]);

    $followup->update(array_merge($data, [
        'reviewed_by' => $user->id,
        'status'      => 'done',
    ]));

    $report = Report::where([
        'idea_id'     => $idea->id,
        'report_type' => 'post_launch_followup',
        'meeting_id'  => $meeting->id,
    ])->first();
    if (!$report) {
    return response()->json([
        'message' => 'لا يوجد تقرير متابعة مرتبط بهذا الاجتماع.'
    ], 404);
}
if (!str_contains($report->description, $followup->followup_phase)) {
    return response()->json([
        'message' => 'لا يمكن انشاء تقرير .'
    ], 422);
}

    if ($report) {
        $report->update([
            'evaluation_score' => $data['evaluation_score'] ?? null,
            'strengths'        => $data['strengths'] ?? null,
            'weaknesses'       => $data['weaknesses'] ?? null,
            'recommendations'  => $data['recommendations'] ?? null,
            'status'           => 'done',
        ]);
    }

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

    if ($data['committee_decision'] === 'graduate' && !empty($data['graduation_date'])) {

        $currentStageName = 'Project Stabilization / Platform Separation';
        $currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
        $progressPercentage = (($currentStageIndex + 1) / count($roadmapStages)) * 100;
        $stageDescription = "Stage executed by: " . $roadmapStages[$currentStageIndex]['actor'];

        if ($idea->roadmap) {
            $idea->roadmap->update([
                'current_stage'       => $currentStageName,
                'stage_description'   => $stageDescription,
                'progress_percentage' => $progressPercentage,
                'last_update'         => now(),
                'next_step'           => "You are all done",
            ]);
        } else {
            Roadmap::create([
                'idea_id'             => $idea->id,
                'current_stage'       => $currentStageName,
                'stage_description'   => $stageDescription,
                'progress_percentage' => $progressPercentage,
                'last_update'         => now(),
                'next_step'           => "You are all done",
            ]);
        }

        Notification::create([
            'user_id' => $idea->owner_id,
            'title'   => "مشروعك مستقر وجاهز للانفصال",
            'message' => "اللجنة أكملت المتابعة النهائية وقررت أن مشروعك مستقر وجاهز للانفصال عن الحاضنة.",
            'type'    => 'project_graduation',
            'is_read' => false,
        ]);
$totalRevenue = $idea->postLaunchFollowups()->sum('revenue');

if ($totalRevenue > 0 && !$idea->profitDistributions()->exists()) {
    $ownerPercent     = 0.6;
    $committeePercent = 0.2; 
    $investorPercent  = 0.1; 
    $adminPercent     = 0.1; 

    ProfitDistribution::create([
        'idea_id' => $idea->id,
        'user_id' => $idea->owner_id,
        'user_role' => 'idea_owner',
        'amount' => $totalRevenue * $ownerPercent,
        'percentage' => $ownerPercent * 100,
        'distributed_at' => now(),
    ]);

    $committeeMembers = $idea->committee ? $idea->committee->committeeMember : collect();
    $investorId = null;

    foreach ($committeeMembers as $member) {
        if ($member->role_in_committee === 'investor') {
            $investorId = $member->user_id;
            break;
        }
    }

    $regularMembers = $committeeMembers->filter(fn($m) => $m->user_id !== $investorId);
    $perMember = $regularMembers->count() > 0 ? ($totalRevenue * $committeePercent) / $regularMembers->count() : 0;

    foreach ($regularMembers as $member) {
        ProfitDistribution::create([
            'idea_id' => $idea->id,
            'user_id' => $member->user_id,
            'user_role' => 'committee_member',
            'amount' => $perMember,
            'percentage' => ($committeePercent / $regularMembers->count()) * 100,
            'distributed_at' => now(),
                        'notes' => "دوره باللجنة: " . $member->role_in_committee,

        ]);
    }

    if ($investorId) {
        ProfitDistribution::create([
            'idea_id' => $idea->id,
            'user_id' => $investorId,
            'user_role' => 'investor',
            'amount' => $totalRevenue * $investorPercent,
            'percentage' => $investorPercent * 100,
            'distributed_at' => now(),
        ]);
    }

    $admin = \App\Models\User::where('role', 'admin')->first();
    if ($admin) {
        ProfitDistribution::create([
            'idea_id' => $idea->id,
            'user_id' => $admin->id,
            'user_role' => 'admin',
            'amount' => $totalRevenue * $adminPercent,
            'percentage' => $adminPercent * 100,
            'distributed_at' => now(),
        ]);
    }
    DistributeProfitsJob::dispatch($idea->id);
    $idea->postLaunchFollowups()->update(['profit_distributed' => true]);
}
    }

    return response()->json([
        'message'  => 'تم تحديث المتابعة والتقرير وخارطة الطريق بنجاح.',
        'followup'=> $followup,
        'report'  => $report ?? null,
        'roadmap' => $idea->roadmap ?? null,
    ]);
}

//هون تابع حتى يرد صاحب الفكرة على متابعة اللجنة 
public function acknowledgePostLaunchFollowup(Request $request, PostLaunchFollowup $followup)
{
    $user = $request->user();
    if ($user->id !== $followup->launchRequest->idea->owner_id) {
        return response()->json(['message' => 'ليس لديك صلاحية للتفاعل مع هذه المتابعة.'], 403);
    }
    if (!$followup->committee_decision) {
        return response()->json(['message' => 'لا يمكنك الرد قبل أن تقوم اللجنة بالتقييم.'], 400);
    }

    $data = $request->validate([
        'owner_response'     => 'required|string|max:2000',
        'owner_acknowledged' => 'required|boolean',
    ]);

    $followup->update($data);
    foreach ($followup->launchRequest->idea->committee->committeeMember as $member) {
        Notification::create([
            'user_id' => $member->user_id,
            'title'   => "صاحب الفكرة اطلع على ملاحظات المتابعة",
            'message' => "صاحب الفكرة '{$user->name}' رد على ملاحظات اللجنة للمتابعة: {$followup->followup_phase}.",
            'type'    => 'post_launch_acknowledged',
            'is_read' => false,
        ]);
    }

    return response()->json([
        'message' => 'تم حفظ ردك وتأكيد الاطلاع على ملاحظات اللجنة.',
        'followup' => $followup,
    ]);
}

//يعرض لي المشارع الناجحة التي انفصلت عن المنصة 
public function getGraduatedProjects(Request $request)
{
    $followups = PostLaunchFollowUp::where('committee_decision', 'graduate')
        ->whereNotNull('graduation_date')
        ->with([
            'launchRequest.idea.owner:id,name',
            'launchRequest.idea.committee:id,committee_name'
        ])
        ->get();

    $projects = $followups->map(function ($followup) {
        $idea = $followup->launchRequest->idea;
        return [
            'idea_id'      => $idea->id,
            'title'        => $idea->title,
            'owner'        => [
                'id'   => $idea->owner->id,
                'name' => $idea->owner->name,
            ],
            'committee'    => $idea->committee ? [
                'id'   => $idea->committee->id,
                'name' => $idea->committee->committee_name,
            ] : null,
            'graduation_date' => $followup->graduation_date,
            'profit_distributed' => $followup->profit_distributed,
        ];
    });

    return response()->json([
        'message' => 'تم جلب جميع المشاريع التي انفصلت عن المنصة بنجاح.',
        'total'   => $projects->count(),
        'data'    => $projects,
    ]);
}


//عرض لصاحب الفكرة هل تم توزيع الارباح ام لا و كم نسبة كل واحد
public function profitDistributionSummary(Request $request, Idea $idea)
{
    $user = $request->user();
    if ($user->id !== $idea->owner_id) {
        return response()->json([
            'message' => 'غير مصرح لك بالوصول لهذه البيانات.'
        ], 403);
    }

    $isDistributed = $idea->postLaunchFollowups()
        ->where('profit_distributed', true)
        ->exists();

    if (!$isDistributed) {
        return response()->json([
            'idea_id' => $idea->id,
            'profit_distributed' => false,
            'message' => 'لم يتم توزيع الأرباح بعد.',
            'distributions' => []
        ]);
    }

    $distributions = $idea->profitDistributions()
        ->with('user:id,name,role')
        ->get()
        ->map(function ($distribution) {
            return [
                'user_name' => $distribution->user->name,
                'role'      => $distribution->user_role,
                'percentage'=> $distribution->percentage . '%',
                'amount'    => $distribution->amount,
            ];
        });

    $ownerDistribution = $idea->profitDistributions()
        ->where('user_id', $idea->owner_id)
        ->first();

    return response()->json([
        'idea_id' => $idea->id,
        'profit_distributed' => true,
        'owner_percentage' => $ownerDistribution?->percentage . '%',
        'your_amount'    => $ownerDistribution?->amount,
        'distributions'   => $distributions
    ]);
}

//عرض لاعضاء اللجنة هل تم توزيع الارباح ام لا 
public function profitDistributionSummaryForCommittee(Request $request, Idea $idea)
{
    $user = $request->user();
    $committeeMember = $user->committeeMember;
    if (!$committeeMember) {
        return response()->json([
            'message' => 'أنت لست عضو لجنة.'
        ], 403);
    }
    if ($idea->committee_id !== $committeeMember->committee_id) {
        return response()->json([
            'message' => 'غير مصرح لك بالوصول لهذه البيانات.'
        ], 403);
    }

    $isDistributed = $idea->postLaunchFollowups()
        ->where('profit_distributed', true)
        ->exists();

    if (!$isDistributed) {
        return response()->json([
            'idea_id' => $idea->id,
            'profit_distributed' => false,
            'message' => 'لم يتم توزيع الأرباح بعد.',
            'distributions' => []
        ]);
    }
    $distributions = $idea->profitDistributions()
        ->with('user:id,name,role')
        ->get()
        ->map(function ($distribution) {
            return [
                'user_name' => $distribution->user->name,
                'role'      => $distribution->user_role,
                'percentage'=> $distribution->percentage . '%',
                'amount'    => $distribution->amount,
            ];
        });

    $yourDistribution = $idea->profitDistributions()
        ->where('user_id', $user->id)
        ->first();

    return response()->json([
        'idea_id' => $idea->id,
        'idea_title' => $idea->title,
        'profit_distributed' => true,
        'your_percentage' => $yourDistribution?->percentage . '%',
        'your_amount'     => $yourDistribution?->amount,
        'distributions'   => $distributions
    ]);
}





}
