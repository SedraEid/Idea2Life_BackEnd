<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{


public function showProfile(Request $request)//عرض البروفايل لصاحب الفكرة 
{
    $user = $request->user(); 

    if (!$user || $user->role !== 'idea_owner') {
        return response()->json(['message' => 'هذه البيانات متاحة لأصحاب الأفكار فقط.'], 403);
    }
    $idea = $user->ideas()->latest()->first();

    $data = [
        'user_id'       => $user->id,
        'name'          => $user->name,
        'email'         => $user->email,
        'role'          => $user->role,
        'phone'         => $user->phone,
        'profile_image' => $user->profile_image,
        'bio'           => $user->bio,
        'idea' => $idea ? [
            'idea_id'       => $idea->id,
            'title'         => $idea->title,
            'status'        => $idea->status,
            'roadmap_stage' => $idea->roadmap_stage ?? null,
        ] : null,
    ];

    return response()->json(['idea_owner' => $data], 200);
}


//عرض البروفايل لاعضاء اللجنة
public function showCommitteeMemberProfile(Request $request)
{
    $user = $request->user(); 

    if (!$user || $user->role !== 'committee_member') {
        return response()->json(['message' => 'هذه البيانات متاحة لأعضاء اللجنة فقط.'], 403);
    }

    $data = [
        'user_id'       => $user->id,
        'name'          => $user->name,
        'email'         => $user->email,
        'role'          => $user->role,
        'phone'         => $user->phone,
        'profile_image' => $user->profile_image,
        'bio'           => $user->bio,
        'committee_role'=> $user->committee_role,
    ];

    return response()->json(['committee_member' => $data], 200);
}


//تعديل البروفايل
public function updateProfile(Request $request)
{
    $user = $request->user(); 
    $user->phone = $request->phone ?? $user->phone;
    $user->bio = $request->bio ?? $user->bio;
    if ($request->hasFile('profile_image')) {
        $image = $request->file('profile_image');
        $filename = time() . '_' . $image->getClientOriginalName();
        $path = $image->storeAs('profile_images', $filename, 'public');
        $user->profile_image = '/storage/' . $path;
    }

    $user->save();

    return response()->json([
        'message' => 'تم تحديث البروفايل بنجاح.',
        'profile' => [
            'user_id'       => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'phone'         => $user->phone,
            'profile_image' => $user->profile_image,
            'bio'           => $user->bio,
            'committee_role'=> $user->committee_role,
        ]
    ], 200);
}



public function updateCommitteeDescription(Request $request, $committeeId)//وضع الوصف الخاص باللجنة
{
    $user = $request->user();

    if (!$user->committeeMember) {
        return response()->json(['message' => 'أنت لست عضواً في أي لجنة.'], 403);
    }
    $committeeMember = $user->committeeMember;

    if ($committeeMember->committee_id != $committeeId) {
        return response()->json(['message' => 'لا يمكنك تعديل لجنة لا تنتمي إليها.'], 403);
    }
    $validated = $request->validate([
        'description' => 'required|string|min:10|max:1000',
    ]);
    $committee = \App\Models\Committee::findOrFail($committeeId);
    $committee->description = $validated['description'];
    $committee->save();
    return response()->json([
        'message' => 'تم تحديث وصف اللجنة بنجاح.',
        'committee' => $committee
    ]);
}


public function myCommitteeDashboard(Request $request)//يعرض لجنتي مع دوري باللجنة 
{
    $user = $request->user();
    if (!$user->committeeMember) {
        return response()->json([
            'status' => 'error',
            'message' => 'أنت لست عضوًا في أي لجنة.'
        ], 403);
    }
    $committee = Committee::with('committeeMember.user')
        ->find($user->committeeMember->committee_id);

    if (!$committee) {
        return response()->json([
            'status' => 'error',
            'message' => 'اللجنة غير موجودة.'
        ], 404);
    }
    $data = [
        'committee_name' => $committee->committee_name,
        'description' => $committee->description,
        'status' => $committee->status,
        'my_role' => $user->committeeMember->role_in_committee,
        'members' => $committee->committeeMember->map(function ($member) use ($user) {
            return [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role_in_committee' => $member->role_in_committee,
                'is_me' => $member->user->id === $user->id,
            ];
        }),
    ];

    return response()->json([
        'status' => 'success',
        'data' => $data
    ]);
}




   
}
