<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;




Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register/idea-owner', [AuthController::class, 'registerIdeaOwner']); //انشاء حساب لصاحب الفكرة
Route::post('/login/idea-owner', [AuthController::class, 'loginIdeaOwner']);//تسجيل الدخول لصاحب الفكرة
Route::post('/register/committee-member', [AuthController::class, 'registerCommitteeMember']);//انشاء حساب لاعضاء اللجنة
Route::post('/login/committee-member', [AuthController::class, 'loginCommitteeMember']);//تسجيل الدخول لاعضاء اللجنة
Route::middleware('auth:sanctum')->get('/profile', [ProfileController::class, 'showProfile']);//عرض البروفايل لصاحب الفكرة
Route::middleware('auth:sanctum')->get('/profile_member', [ProfileController::class, 'showCommitteeMemberProfile']);//عرض البروفايل لاعضاء اللجنة 

Route::middleware('auth:sanctum')->group(function () {//تعديل البروفايل  
    Route::post('/profile/update', [ProfileController::class, 'updateIdeaOwnerProfile']);
});


