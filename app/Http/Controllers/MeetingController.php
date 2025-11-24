<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\IdeaOwner;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
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

public function committee_Ideas_meetings(Request $request) // عرض الأفكار مع الاجتماعات وصاحب الفكرة للجنة
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



public function scheduleAdvancedMeeting(Request $request, Idea $idea)//عمل اجتماع من قبل اللجنة من اجل مناقشة خطة العمل
{
    $user = $request->user();

    if (!$user->committeeMember || $user->committeeMember->committee_id != $idea->committee_id) {
        return response()->json(['message' => 'ليس لديك صلاحية جدولة الاجتماع لهذه الفكرة.'], 403);
    }

    $ideaOwner = $idea->ideaowner;
    if (!$ideaOwner) {
        return response()->json(['message' => 'الفكرة لا تملك صاحب.'], 404);
    }

    $meeting = $idea->meetings()->where('type', 'business_plan_review')->first();
    if ($meeting) {
        $meeting->update([
            'meeting_date' => $request->meeting_date ?? $meeting->meeting_date,
            'notes' => $request->notes ?? $meeting->notes,
            'meeting_link' => $request->meeting_link ?? $meeting->meeting_link,
        ]);
    } else {
        $meeting = $idea->meetings()->create([
            'idea_id' => $idea->id,
            'owner_id' => $ideaOwner->id,
            'committee_id' => $idea->committee_id,
            'meeting_date' => $request->meeting_date ?? now()->addDays(3),
            'meeting_link' => $request->meeting_link ?? null,
            'notes' => $request->notes ?? null,
            'requested_by' => 'committee',
            'type' => 'business_plan_review',
        ]);
    }
    return response()->json([
        'message' => 'تم جدولة الاجتماع للتقييم المتقدم بنجاح.',
        'meeting' => $meeting,
    ]);
}



public function upcomingCommitteeMeetings(Request $request)//عرض الاجتماعات الخاصة باللجنة 
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json([
            'message' => 'أنت لست عضو لجنة.'
        ], 403);
    }

    $committeeId = $user->committeeMember->committee_id;

    $meetings = Meeting::with(['idea:id,title', 'committee:id,committee_name'])
        ->where('committee_id', $committeeId)
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
        'message' => 'تم جلب الاجتماعات القادمة الخاصة باللجنة بنجاح.',
        'upcoming_meetings' => $meetings
    ]);
}



}
