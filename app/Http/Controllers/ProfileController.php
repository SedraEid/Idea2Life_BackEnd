<?php

namespace App\Http\Controllers;

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

    $ideaOwner = $user->ideaowner;
    $idea = $ideaOwner ? $ideaOwner->ideas()->latest()->first() : null;

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

    // تحديث الحقول مباشرة من جدول users
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





   
}
