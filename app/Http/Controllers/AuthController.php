<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\IdeaOwner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Roadmap;
use App\Models\Wallet;

class AuthController extends Controller
{

  public function registerIdeaOwner(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'Notification' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $user = User::create([
        'name'      => $request->name,
        'email'     => $request->email,
        'password'  => Hash::make($request->password),
        'role' => 'idea_owner',
    ]);

    $ideaOwner = IdeaOwner::create([
        'user_id' => $user->id,
    ]);

    $wallet = Wallet::create([
        'user_id'   => $user->id,
        'user_type' => 'creator',
        'balance'   => 0,        
        'status'    => 'active', 
    ]);

    $roadmap_created = false;
    if ($request->create_roadmap) {
        Roadmap::create([
            'idea_id' => null, 
            'committee_id' => null,
            'owner_id' => $ideaOwner->id,
            'current_stage' => 'غير محدد بعد',
            'stage_description' => 'سيتم تحديد مراحل خارطة الطريق لاحقًا.',
            'progress_percentage' => 0,
            'last_update' => now(),
            'next_step' => 'ابدأ بإضافة مراحل خارطة الطريق.',
        ]);
        $roadmap_created = true;
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Idea Owner registered successfully!',
        'user'    => $user,
        'token'   => $token,
        'roadmap_created' => $roadmap_created
    ], 201);
}


    //عميلة تسجيل الدخول لصاحب الفكرة

     public function loginIdeaOwner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('role', 'idea_owner') 
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('idea-owner-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ], 200);
    }


//انشاء حساب لاعضاء اللجنة 
public function registerCommitteeMember(Request $request)
{
    $request->validate([
        'name'  => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'role_in_committee' => 'required|in:economist,market,legal,technical,investor'
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'committee_member',
        'committee_role'  => $request->role_in_committee,
    ]);

    $committee = Committee::whereDoesntHave('committeeMember', function($q) use ($request) {
        $q->where('role_in_committee', $request->role_in_committee);
    })->first();

if (!$committee) {
    $committeeCount = Committee::count();
    $committee = Committee::create([
        'committee_name' => 'لجنة ' . ($committeeCount + 1),
        'status' => 'active'
    ]);
}

    $committeeMember = CommitteeMember::create([
        'committee_id' => $committee->id,
        'user_id' => $user->id,
        'role_in_committee' => $request->role_in_committee
    ]);


$wallet = Wallet::create([
    'user_id' => $user->id,
    'user_type' => $request->role_in_committee,
    'balance' => 0.00,
    'status' => 'active',
]);


    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'عضو اللجنة تم تسجيله بنجاح',
        'user' => $user,
        'committee' => $committee,
        'committee_member' => $committeeMember,
        'token' => $token
    ], 201);
}

//تسجيل الدخول لاعضاء اللجنة 

public function loginCommitteeMember(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string|min:6'
    ]);

    $user = User::where('email', $request->email)
                ->where('role', 'committee_member') 
                ->first();

    if(!$user || !Hash::check($request->password, $user->password)){
        return response()->json([
            'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
        ], 401);
    }

    $token = $user->createToken('committee_member_token')->plainTextToken;

    return response()->json([
        'message' => 'تم تسجيل الدخول بنجاح',
        'user' => $user,
        'token' => $token
    ]);
}



public function updateCommitteeDescription(Request $request, $committeeId)
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


















