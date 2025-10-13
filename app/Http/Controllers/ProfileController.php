<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{


    //عرض البروفايل لصاحب الفكرة
   public function showProfile(Request $request)
{
    $user = $request->user(); 

    if (!$user || $user->user_type !== 'idea_owner') {
        return response()->json(['message' => 'هذه البيانات متاحة لأصحاب الأفكار فقط.'], 403);
    }

    $profile = $user->profile;

    if (!$profile) {
        return response()->json(['message' => 'لم يتم إنشاء البروفايل بعد.'], 404);
    }

    $data = collect([$user])->map(function($u) use ($profile) {
        return [
            'user_id'       => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'user_type'     => $u->user_type,
            'profile' => [
                'profile_id'    => $profile->profile_id ?? $profile->id,
                'phone'         => $profile->phone,
                'profile_image' => $profile->profile_image,
                'bio'           => $profile->bio,
                'roadmap_stage' => $profile->roadmap_stage,
            ]
        ];
    });

    return response()->json(['idea_owner' => $data], 200);
}

//عرض البروفايل لاعضاء اللجنة
public function showCommitteeMemberProfile(Request $request)
{
    $user = $request->user(); 

    if (!$user || $user->user_type !== 'committee_member') {
        return response()->json(['message' => 'هذه البيانات متاحة لأعضاء اللجنة فقط.'], 403);
    }

    $profile = $user->profile;

    if (!$profile) {
        return response()->json(['message' => 'لم يتم إنشاء البروفايل بعد.'], 404);
    }

    $data = collect([$user])->map(function($u) use ($profile) {
        return [
            'user_id'       => $u->id,
            'name'          => $u->name,
            'email'         => $u->email,
            'user_type'     => $u->user_type,
            'profile' => [
                'profile_id'      => $profile->profile_id ?? $profile->id,
                'phone'           => $profile->phone,
                'profile_image'   => $profile->profile_image,
                'bio'             => $profile->bio,
                'committee_role'  => $profile->committee_role,
            ]
        ];
    });

    return response()->json(['committee_member' => $data], 200);
}


//تعديل البروفايل
public function updateIdeaOwnerProfile(Request $request)
{
    $user = $request->user(); 

    $profile = $user->profile;
    $profile->phone = $request->phone ?? $profile->phone;
    $profile->bio = $request->bio ?? $profile->bio;

    if ($request->hasFile('profile_image')) {
        $image = $request->file('profile_image');
        $filename = time() . '_' . $image->getClientOriginalName();
        $path = $image->storeAs('profile_images', $filename, 'public');
        $profile->profile_image = '/storage/' . $path;
    }

    $profile->save();

    return response()->json([
        'message' => 'تم تحديث البروفايل بنجاح.',
        'profile' => $profile
    ], 200);
}









   
}
