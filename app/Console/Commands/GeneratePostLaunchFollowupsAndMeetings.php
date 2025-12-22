<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LaunchProject;
use App\Models\PostLaunchFollowUp;
use App\Models\Meeting;
use App\Models\CommitteeMember;
use App\Models\Notification;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GeneratePostLaunchFollowupsAndMeetings extends Command
{
    protected $signature = 'followups:generate-with-meetings';
    protected $description = 'إنشاء متابعات بعد الإطلاق + اجتماعات + تقارير + إشعارات تلقائيًا';

    public function handle()
    {
        $launches = LaunchProject::with('idea')->where('status', 'launched')->get();
        $checkpoints = [
            'week_1'  => 7,
            'month_1' => 30,
            'month_3' => 90,
            'month_6' => 180,
        ];
        $window = 10; 

        $now = Carbon::now();
        Log::info("بدء فحص المتابعات بعد الإطلاق عند {$now}");

        foreach ($launches as $launch) {
            if (!$launch->launch_date || !$launch->idea) continue;

            foreach ($checkpoints as $checkpoint => $days) {
                $targetDate = Carbon::parse($launch->launch_date)->addDays($days);
                $from = $targetDate->copy()->subMinutes($window);
                $to   = $targetDate->copy()->addMinutes($window);
                if ($now->between($from, $to)) {
                    $exists = PostLaunchFollowUp::where('launch_project_id', $launch->id)
                        ->where('checkpoint', $checkpoint)
                        ->exists();
                    if ($exists) continue;
                    $followUp = PostLaunchFollowUp::create([
                        'launch_project_id' => $launch->id,
                        'idea_id'           => $launch->idea_id,
                        'checkpoint'        => $checkpoint,
                        'issue_type'        => 'none',
                        'status'            => 'pending',
                    ]);

                    $meeting = Meeting::create([
                        'idea_id'      => $launch->idea_id,
                        'type'         => 'post_launch_followup',
                        'meeting_date' => $targetDate,
                        'notes'        => "اجتماع متابعة بعد الإطلاق ({$checkpoint})",
                        'requested_by' => 'committee',
                    ]);
                    Report::create([
                        'idea_id'       => $launch->idea_id,
                        'meeting_id'    => $meeting->id,
                        'report_type'   => 'post_launch_followup',
                        'description'   => "تم إنشاء متابعة بعد الإطلاق ({$checkpoint})، الرجاء مراجعة الحالة.",
                        'status'        => 'pending',
                        'recommendations'=> 'سوف يتم تعديل التوصيات لاحقًا بعد تقييم اللجنة.',
                        'evaluation_score'=> null,
                    ]);
                    Notification::create([
                        'user_id' => $launch->idea->owner_id,
                        'title'   => 'متابعة بعد الإطلاق',
                        'message' => "تم تحديد متابعة بعد الإطلاق ({$checkpoint}) لمشروعك، وسيتم عقد اجتماع مع اللجنة.",
                        'type'    => 'info',
                        'is_read' => false,
                    ]);
                    $committeeMembers = CommitteeMember::where('committee_id', $launch->idea->committee_id)->get();
                    foreach ($committeeMembers as $member) {
                        Notification::create([
                            'user_id' => $member->user_id,
                            'title'   => 'اجتماع متابعة بعد الإطلاق',
                            'message' => "تم تحديد اجتماع متابعة بعد الإطلاق ({$checkpoint}) لمشروع '{$launch->idea->title}'.",
                            'type'    => 'info',
                            'is_read' => false,
                        ]);
                    }

                    Log::info("تم إنشاء متابعة، اجتماع وتقرير لنقطة ({$checkpoint}) لمشروع {$launch->idea->title}");
                }
            }
        }

        $this->info('تم إنشاء المتابعات والاجتماعات والتقارير والإشعارات بنجاح.');
    }
}
