<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessPlanController;
use App\Http\Controllers\FundingController;
use App\Http\Controllers\GanttChartController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\LaunchRequestController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostLaunchFollowupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WalletController;
use App\Models\PostLaunchFollowUp;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/register/idea-owner', [AuthController::class, 'registerIdeaOwner']); //انشاء حساب لصاحب الفكرة
Route::post('/login/idea-owner', [AuthController::class, 'loginIdeaOwner']);//تسجيل الدخول لصاحب الفكرة
Route::post('/register/committee-member', [AuthController::class, 'registerCommitteeMember']);//انشاء حساب لاعضاء اللجنة
Route::post('/login/committee-member', [AuthController::class, 'loginCommitteeMember']);//تسجيل الدخول لاعضاء اللجنة
Route::middleware('auth:sanctum')->get('/profile', [ProfileController::class, 'showProfile']);//عرض البروفايل لصاحب الفكرة
Route::middleware('auth:sanctum')->get('/profile_member', [ProfileController::class, 'showCommitteeMemberProfile']);//عرض البروفايل لاعضاء اللجنة 

Route::get('/my-committee/dashboard', [ProfileController::class, 'myCommitteeDashboard'])//يعرض لي لجنتي مع دوري بللجنة و باقي الاعضاء
    ->middleware('auth:sanctum'); 

Route::middleware('auth:sanctum')->group(function () {//تعديل البروفايل  
    Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
});
Route::middleware('auth:sanctum')->put('/committees/{committeeId}/description', [ProfileController::class, 'updateCommitteeDescription']);//تعديل الوصف الخاص باللجنة 

Route::middleware('auth:sanctum')->group(function () {//اضافة فكرة 
    Route::post('/ideas', [IdeaController::class, 'store']);
});
Route::middleware('auth:sanctum')->put('/ideas/{idea}', [IdeaController::class, 'update']);//تعديل الفكرة من قبل صاحبها بعد التقييم الضعيف
Route::middleware('auth:sanctum')->post('/ideas/{idea}/evaluate', [ReportController::class, 'evaluate']);//تقييم الفكرة من قبل اللجنة
Route::middleware('auth:sanctum')->get(//عرض كل الافكار التي تشرف عليها اللجنة مع التفاصييلللللل
    '/committee/ideas-full-clean',
    [IdeaController::class, 'committeeIdeasFullDetailsClean']
);
Route::middleware('auth:sanctum')->get('/my-committee', [IdeaController::class, 'getUserIdeasWithCommittee']);//عرض اللجان او اللجنة التي تشرف على فكرة صاحب الفكرة 

Route::middleware('auth:sanctum')->group(function () {//ملئ مخطط ال bmc
    Route::post('/ideas/{idea}/business-plan', [BusinessPlanController::class, 'store']);
});

Route::get('/ideas/with-committee', [IdeaController::class, 'getIdeasWithCommittee']);//جلب كل الافكار مع من اللجنة المشرفة

Route::middleware('auth:sanctum')->get('/my_ideas', [IdeaController::class, 'myIdeas']);//جلب افكاري مع اللجنة المشرفة عليها

Route::middleware('auth:sanctum')->get('/ideas/{idea}/roadmap', [RoadmapController::class, 'getIdeaRoadmap']);//  جلب خارطة الطريق للفكرة لصاحب الفكرة و اللجنة

Route::middleware('auth:sanctum')->get('/idea/{idea_id}/reports', [ReportController::class, 'ownerIdeaReports']);//جلب التقارير لصاحب الفكرة


Route::middleware('auth:sanctum')->get('/idea/{idea_id}/meetings/upcoming', //يعرض لصاحب الفكرة الاجتماعات
    [MeetingController::class, 'upcomingMeetings']);

Route::middleware('auth:sanctum')->group(function () {
    //  مع الاجتماعات عرض كل الأفكار التابعة للجنة
    Route::get('/committee/ideas', [MeetingController::class, 'committee_Ideas_meetings']);
    Route::put('/committee/meetings/{meeting}', [MeetingController::class, 'updateMeeting']);//وضع اللينك و الملاحظات للاجتماع من قبل اللجنة 
  
});
Route::post('/ideas/{idea}/advanced-meeting', [MeetingController::class, 'scheduleAdvancedMeeting'])
    ->middleware('auth:sanctum'); //تحديد موعد الاجتماع المتقدم من قبل اللجنة 

Route::middleware('auth:sanctum')->post('/ideas/{idea}/advanced-evaluation', [ReportController::class, 'advancedEvaluation']);//تقييم المتقدم لل BMC


Route::get('/committee/bmcs', [BusinessPlanController::class, 'showAllBMCsForCommittee'])//عرض كل فكرة مع ال bmc التي تشرف عليها اللجنة 
    ->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->post('/ideas/{idea}/update-bmc', [BusinessPlanController::class, 'updateBMC']);//تعديل ال bmc  اذا كان التقييم اقل من 80

Route::middleware('auth:sanctum')->get('/idea/{idea_id}/bmc', [BusinessPlanController::class, 'showOwnerIdeaBMC']);
//عرض ال bmc لصاحب الفكرة

Route::middleware('auth:sanctum')->get('/committee/upcoming-meetings', [MeetingController::class, 'upcomingCommitteeMeetings']); //عرض الاجتماعات للجنة

Route::middleware('auth:sanctum')->get('/my_wallet', [WalletController::class, 'getMyWallet']);//جلب المحفظة لاي مستخدم في النظام

Route::middleware('auth:sanctum')->post('/ideas/{idea}/funding-request', [FundingController::class, 'requestFunding']);//طلب التمويل من قبل صاحب الفكرة

Route::middleware('auth:sanctum')->group(function () {
    //  إلغاء طلب التمويل من قبل صاحب الفكرة
    Route::post('/ideas/{funding_id}/cancel-funding', [FundingController::class, 'cancelFundingRequest']);
});

Route::middleware('auth:sanctum')->group(function () {//جلب طلبات التمويل للجنة 
    Route::get('/committee/fundings', [FundingController::class, 'getCommitteeFundRequests']);
});


Route::middleware('auth:sanctum')->group(function () {//تقييم طلب التمويل من قبل اللجنة
    Route::post('/fundings/{funding}/evaluate', [FundingController::class, 'evaluateFunding']);
});


Route::middleware('auth:sanctum')->group(function () {//عرض التمويل لصاحب الفكرة
Route::get('/my-ideas/{idea_id}/funding', [FundingController::class, 'showFundingForIdea']);

});

Route::middleware('auth:sanctum')->get('/committee/funding-checks', [FundingController::class, 'showCommitteeFundingChecks']);//عرض الشيك للجنة


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gantt-charts/{ideaId}', [GanttChartController::class, 'index']); // صاحب الفكرة كل المراحل لكل أفكار
    Route::post('/gantt-charts/{idea_id}', [GanttChartController::class, 'store']); // إنشاء مرحلة
    Route::put('/gantt-charts/{gantt_id}', [GanttChartController::class, 'update']); // تعديل مرحلة
    Route::delete('/gantt-charts/{id}', [GanttChartController::class, 'destroy']); // حذف مرحلة
});


Route::middleware('auth:sanctum')
    ->get('/committee/ideas/{ideaId}/gantt-charts', [GanttChartController::class, 'getCommitteeIdeaGanttCharts']);//عرض المراحل و التاسكات لاعضاء اللجنة 


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gantt-charts/{gantt_id}/tasks', [TaskController::class, 'index']); // كل التاسكات داخل مرحلة
    Route::post('/gantt-charts/{gantt_id}/tasks', [TaskController::class, 'store']); // إنشاء تاسك
    Route::post('/tasks/{id}', [TaskController::class, 'update']); // تعديل تاسك
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']); // حذف تاسك
});

//اختبار اللجنة ان صاحب الفكرة انتهى من كتابة الغانت تشارت
Route::middleware('auth:sanctum')->post('/ideas/{idea_id}/submit-timeline', [GanttChartController::class, 'submitFullTimeline']);


//رفض او قبول الغانت تشارت من قبل اللجنة المشرفة 
Route::middleware('auth:sanctum')->post('/ideas/{idea}/gantt/approve-or-reject', [GanttChartController::class, 'approveOrRejectAllPhases']);

Route::middleware('auth:sanctum')->group(function () {//تقييم المرحلة من قبل اللجنة
    Route::post('/ideas/{idea}/phase-evaluation/{gantt_id}', [GanttChartController::class, 'evaluatePhase']);
});

Route::middleware('auth:sanctum')->group(function () {
Route::get('/notifications/owner', [NotificationController::class, 'ownerNotifications']);});//عرض الاشعارات للكل

Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);//لتحديث حالة القراءة


// جلب تقييم مرحلة معيّنة لصاحب الفكرة
Route::middleware('auth:sanctum')->get('/ideas/{idea_id}/gantt/{gantt_id}/evaluation', 
    [GanttChartController::class, 'getPhaseEvaluation']
);

//عرض الغرامة المالية لصاحب الفكرة 
Route::middleware('auth:sanctum')->get('/ideas/{idea_id}/penalty', [GanttChartController::class, 'showPenaltyStatus']);

//دفع المبلغ المالي من قبل صاحب الفكرة بعد 3 تقييمات سيئة لكي يستطيع اكمال المشروع
Route::middleware('auth:sanctum')->post('/gantt/{idea_id}/pay-penalty', [GanttChartController::class, 'payPenaltyForPhase']);


Route::middleware('auth:sanctum')->group(function () {//طلب التمويل لمرحلة او لتاسك 
    Route::post('/funding/request/gantt/{gantt_id}', [GanttChartController::class, 'requestFundingGantt']);
    Route::post('/funding/request/task/{task_id}', [GanttChartController::class, 'requestFundingTask']);
});

Route::middleware('auth:sanctum')->group(function () {//تمويل للمرحلة او للتاسك 
    Route::post('/funding/{funding}/evaluate/gantt/task', [GanttChartController::class, 'approveFunding']);
});


Route::middleware('auth:sanctum')->group(function () {//طلب اطلاق المشروع من قبل صاحب الفكرة
    Route::post('/ideas/{idea_id}/launch-request', [LaunchRequestController::class, 'requestLaunch']);
});

Route::middleware('auth:sanctum')->group(function () {//عرض طلبات الاطلاق للجنة
    Route::get('/launch-requests/pending', [LaunchRequestController::class, 'showPendingLaunchRequests']);
});

//عرض طلبات الاطلاق لصاحب الفكرة
Route::middleware('auth:sanctum')->get('/my-launch-requests', [LaunchRequestController::class, 'myLaunchRequests']);


Route::middleware('auth:sanctum')->group(function () {//تقييم طلب الاطلاق من قبل اللجنة
    Route::post('/committee/launch-requests/{launchRequestId}/evaluate',[LaunchRequestController::class, 'evaluateLaunchRequest']
    );

});

Route::middleware('auth:sanctum')->group(function () {
    // عرض قرار اللجنة بخصوص طلب الإطلاق لصاحب الفكرة
    Route::get('/ideas/{idea_id}/launch-decision', [LaunchRequestController::class, 'showLaunchDecision']);
});

//طلب تمويل بعد الموافقة على طلب الاطلاق
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/ideas/{idea_id}/request-funding', [LaunchRequestController::class, 'requestFunding']);
});
//و للموافقة على التمويل استخدمي راوت سطر 103


// عرض كل المتابعات بعد الإطلاق لصاحب الفكرة
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/my-post-launch-followups', [PostLaunchFollowupController::class, 'getMyPostLaunchFollowups']);
});

//عرض كل المتابعات بعد الاطلاق للجنة المشرفة 
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/my-post-launch-followups-commitee', [PostLaunchFollowupController::class, 'getCommitteePostLaunchFollowups']);
});

//تقييم المتابعة بعد الاطلاق من قبل اللجنة 
Route::middleware('auth:sanctum')->post('/post-launch-followups/{followup}/evaluate', [PostLaunchFollowupController::class, 'evaluateFollowup']);
