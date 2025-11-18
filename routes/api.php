<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessPlanController;
use App\Http\Controllers\FundingController;
use App\Http\Controllers\GanttChartController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WalletController;

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
    Route::post('/profile/update', [ProfileController::class, 'updateProfile']);
});
Route::middleware('auth:sanctum')->put('/committees/{committeeId}/description', [AuthController::class, 'updateCommitteeDescription']);//تعديل الوصف الخاص باللجنة 

Route::middleware('auth:sanctum')->group(function () {//اضافة فكرة 
    Route::post('/ideas', [IdeaController::class, 'store']);
});
Route::middleware('auth:sanctum')->put('/ideas/{idea}', [IdeaController::class, 'update']);//تعديل الفكرة من قبل صاحبها بعد التقييم الضعيف
Route::middleware('auth:sanctum')->post('/ideas/{idea}/evaluate', [IdeaController::class, 'evaluate']);//تقييم الفكرة من قبل اللجنة
Route::middleware('auth:sanctum')->get('/committee/ideas', [IdeaController::class, 'committeeIdeas']);//عرض كل الافكار التي تشرف عليها اللجنة
Route::middleware('auth:sanctum')->get('/my-committee', [IdeaController::class, 'getUserIdeasWithCommittee']);//عرض اللجان او اللجنة التي تشرف على فكرة صاحب الفكرة 

Route::middleware('auth:sanctum')->group(function () {//ملئ مخطط ال bmc
    Route::post('/ideas/{idea}/business-plan', [BusinessPlanController::class, 'store']);
});

Route::get('/ideas/with-committee', [IdeaController::class, 'getIdeasWithCommittee']);//جلب كل الافكار مع من اللجنة المشرفة

Route::middleware('auth:sanctum')->get('/my_ideas', [IdeaController::class, 'myIdeas']);//جلب افكاري مع اللجنة المشرفة عليها

Route::middleware('auth:sanctum')->get('/ideas/{idea}/roadmap', [IdeaController::class, 'getIdeaRoadmap']);//جلب خارطة الطريق للفكرة

Route::middleware('auth:sanctum')->group(function () {//جلب التقارير الخاصة بصاحب الفكرة
    Route::get('/my_idea_reports', [IdeaController::class, 'ownerReports']);
});

Route::middleware('auth:sanctum')->group(function () {//يعرض لصاحب الفكرة الاجتماعات و كم تبقى للاجتماع 
    Route::get('/my/upcoming_meetings', [IdeaController::class, 'upcomingMeetings']);
});

Route::middleware('auth:sanctum')->group(function () {
    // عرض كل الأفكار التابعة للجنة
    Route::get('/committee/ideas', [IdeaController::class, 'committee_Ideas']);
    Route::put('/committee/meetings/{meeting}', [IdeaController::class, 'updateMeeting']);//وضع اللينك و الملاحظات للاجتماع من قبل اللجنة 
  
});
Route::post('/ideas/{idea}/advanced-meeting', [BusinessPlanController::class, 'scheduleAdvancedMeeting'])
    ->middleware('auth:sanctum'); //تحديد موعد الاجتماع المتقدم من قبل اللجنة 

Route::middleware('auth:sanctum')->post('/ideas/{idea}/advanced-evaluation', [BusinessPlanController::class, 'advancedEvaluation']);//تقييم المتقدم لل BMC

Route::get('/my-committee/dashboard', [AuthController::class, 'myCommitteeDashboard'])//يعرض لي لجنتي مع دوري بللجنة و باقي الاعضاء
    ->middleware('auth:sanctum'); 

Route::get('/committee/bmcs', [BusinessPlanController::class, 'showAllBMCsForCommittee'])//عرض كل فكرة مع ال bmc التي تشرف عليها اللجنة 
    ->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->post('/ideas/{idea}/update-bmc', [BusinessPlanController::class, 'updateBMC']);//تعديل ال bmc  اذا كان التقييم اقل من 80

Route::middleware('auth:sanctum')->get('/owner/ideas-with-bmc', [BusinessPlanController::class, 'showAllOwnerIdeasWithBMC']);//عرض ال bmc لصاحب الفكرة

Route::middleware('auth:sanctum')->get('/committee/upcoming-meetings', [BusinessPlanController::class, 'upcomingCommitteeMeetings']); //عرض الاجتماعات للجنة

Route::middleware('auth:sanctum')->get('/my_wallet', [WalletController::class, 'getMyWallet']);//جلب المحفظة لاي مستخدم في النظام

Route::middleware('auth:sanctum')->post('/ideas/{idea}/funding-request', [FundingController::class, 'requestFunding']);//طلب التمويل من قبل صاحب الفكرة

Route::middleware('auth:sanctum')->group(function () {
    //  إلغاء طلب التمويل من قبل صاحب الفكرة
    Route::post('/ideas/{idea}/cancel-funding', [FundingController::class, 'cancelFundingRequest']);
});

Route::middleware('auth:sanctum')->group(function () {//جلب طلبات التمويل للجنة 
    Route::get('/committee/fundings', [FundingController::class, 'getCommitteeFundRequests']);
});

Route::middleware('auth:sanctum')->group(function () {//تقييم طلب التمويل من قبل اللجنة
    Route::post('/fundings/{funding}/evaluate', [FundingController::class, 'evaluateFunding']);
});

Route::middleware('auth:sanctum')->get('/my-fundings', [FundingController::class, 'showMyFunding']);//عرض التمويل لصاحب الفكرة

Route::middleware('auth:sanctum')->get('/committee/funding-checks', [FundingController::class, 'showCommitteeFundingChecks']);//عرض الشيك للجنة


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gantt-charts', [GanttChartController::class, 'index']); // صاحب الفكرة   )كل المراحل لكل أفكار المستخدم)
    Route::post('/gantt-charts/{idea_id}', [GanttChartController::class, 'store']); // إنشاء مرحلة
    Route::put('/gantt-charts/{gantt_id}', [GanttChartController::class, 'update']); // تعديل مرحلة
    Route::delete('/gantt-charts/{id}', [GanttChartController::class, 'destroy']); // حذف مرحلة
});


Route::middleware('auth:sanctum')
    ->get('/committee/ideas/{ideaId}/gantt-charts', [GanttChartController::class, 'getCommitteeIdeaGanttCharts']);//عرض المراحل و التاسكات لاعضاء اللجنة 


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gantt-charts/{gantt_id}/tasks', [TaskController::class, 'index']); // كل التاسكات داخل مرحلة
    Route::post('/gantt-charts/{gantt_id}/tasks', [TaskController::class, 'store']); // إنشاء تاسك
    Route::put('/tasks/{id}', [TaskController::class, 'update']); // تعديل تاسك
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']); // حذف تاسك
});


Route::middleware('auth:sanctum')->group(function () {
Route::get('/notifications/owner', [NotificationController::class, 'ownerNotifications']);});//عرض الاشعارات للكل

Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);//لتحديث حالة القراءة



Route::middleware('auth:sanctum')->group(function () {
    // تحديث تقرير تقييم المرحلة
    Route::put('/ideas/{idea}/phase-reports/{gantt_id}', [GanttChartController::class, 'updatePhaseReport']);
});

//رفض او قبول الغانت تشارت من قبل اللجنة المشرفة 
Route::middleware('auth:sanctum')->post('/ideas/{idea}/gantt/approve-or-reject', [GanttChartController::class, 'approveOrRejectAllPhases']);


Route::middleware('auth:sanctum')->get('/improvement-plan/{idea_id}', //جلب خطة التحسين لكي يماؤها صاحب الفكرة لاحقا
    [GanttChartController::class, 'getImprovementPlan']
);


Route::middleware('auth:sanctum')->group(function () {//ملئ خطة التحسين من قبل صاحب الفكرة 
    Route::put('/improvement-plans/{plan}', [GanttChartController::class, 'updateImprov']);
});


Route::middleware('auth:sanctum')->get(//جلب خطة التحسين التي كتبها صاحب الفكرة و عرضها للجنة المشرفة 
    '/committee/idea/{idea_id}/improvement-plan',
    [GanttChartController::class, 'getIdeaImprovementPlanForCommittee']
);


Route::middleware('auth:sanctum')->group(function () {
    // رد اللجنة على خطة التحسين
    Route::post('/improvement-plans/{plan_id}/respond', [GanttChartController::class, 'respondToImprovementPlan']);
});

Route::middleware('auth:sanctum')->group(function () {//طلب التمويل لمرحلة او لتاسك 
    Route::post('/funding/request/gantt/{gantt_id}', [GanttChartController::class, 'requestFundingGantt']);
    Route::post('/funding/request/task/{task_id}', [GanttChartController::class, 'requestFundingTask']);
});

Route::middleware('auth:sanctum')->group(function () {//تمويل للمرحلة او للتاسك 
    Route::post('/funding/{funding}/evaluate/gantt/task', [GanttChartController::class, 'evaluateFunding']);
});