<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Idea;
use App\Models\Report;

class RoadmapController extends Controller
{
    
public function getIdeaRoadmap(Request $request, Idea $idea) // جلب خارطة الطريق للفكرة
{
    $user = $request->user();
    $isOwner = $idea->owner_id === $user->id;
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

//عرض مراحل خارطة الطريق الخاصة بالمنصة 
 public function getAllPlatformStages()
    {
      $roadmapStages = [
    [
        'name' => 'Idea Submission',
        'message_for_owner' => 'أنت بحاجة لتقديم فكرتك مع كافة التفاصيل المطلوبة، انتظر تقييم اللجنة.',
    ],
    [
        'name' => 'Initial Evaluation',
        'message_for_owner' => 'اللجنة ستقوم بتقييم فكرتك وإعطاء ملاحظات أولية. أنت لا تحتاج لفعل شيء في هذه المرحلة إلا الملاحظة.',
    ],
    [
        'name' => 'Systematic Planning / Business Plan Preparation',
        'message_for_owner' => 'أنت بحاجة لتحضير خطة عمل منهجية وإرسالها للجنة للمراجعة.',
    ],
    [
        'name' => 'Advanced Evaluation Before Funding',
        'message_for_owner' => 'اللجنة ستقيم جاهزية مشروعك للتمويل. استجب لأي ملاحظات إذا طلبت.',
    ],
    [
        'name' => 'Funding',
        'message_for_owner' => 'أنت بحاجة لتقديم طلب تمويل مع توضيح الاحتياجات، اللجنة أو المستثمر سيوافق أو يطلب تعديلات.',
    ],
    [
        'name' => 'Execution and Development',
        'message_for_owner' => 'قم بتنفيذ المشروع ورفع التقارير، اللجنة ستقوم بمراجعة التقدم وتقديم التوصيات.',
    ],
    [
        'name' => 'Launch',
        'message_for_owner' => 'حضّر المشروع للإطلاق وراجع جاهزيته، اللجنة ستوافق على الإطلاق وتقدم التوصيات.',
    ],
    [
        'name' => 'Post-Launch Follow-up',
        'message_for_owner' => 'اللجنة ستقوم برفع تقارير متابعة، أنت تراقب وتتعامل مع أي ملاحظات أو مشاكل.',
    ],
    [
        'name' => 'Project Stabilization / Platform Separation',
        'message_for_owner' => 'إذا لزم، قدم طلب انفصال المشروع عن المنصة واستكمل الوثائق المطلوبة، اللجنة ستوافق على الاستقرار والانفصال.',
    ],
];

        return response()->json([
            'platform_roadmap_stages' => $roadmapStages
        ]);
    }


}
