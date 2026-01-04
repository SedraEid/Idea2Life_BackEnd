<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LaunchRequest;
use App\Models\PostLaunchFollowup;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Notification;
use Carbon\Carbon;

class CreatePostLaunchFollowups extends Command
{
    protected $signature = 'launch:create-followups';
    protected $description = 'إنشاء المتابعات والاجتماعات والتقارير بعد الإطلاق تلقائياً';

    public function handle()
    {
        $now = Carbon::now();

        $launchRequests = LaunchRequest::with(['postLaunchFollowups', 'idea.committee.committeeMember', 'idea.owner'])
            ->whereIn('status', ['approved_for_launch', 'launched'])
            ->get()
            ->filter(fn($launch) => $launch->postLaunchFollowups->isEmpty());

        foreach ($launchRequests as $launch) {
            DB::beginTransaction();
            try {
                $launchStart = $launch->launch_date ? Carbon::parse($launch->launch_date) : $now;

            $followups = [
    'week_1'  => $launchStart->copy()->addDays(7),
    'month_1' => $launchStart->copy()->addDays(30),
    'month_3' => $launchStart->copy()->addDays(90),
    'month_6' => $launchStart->copy()->addDays(180),
];


                // إنشاء المتابعات والاجتماعات والتقارير
                foreach ($followups as $phaseName => $date) {
                    PostLaunchFollowup::create([
                        'launch_request_id' => $launch->id,
                        'followup_phase'    => $phaseName,
                        'scheduled_date'    => $date,
                        'status'            => 'pending',
                    ]);

                    $meeting = Meeting::create([
                        'idea_id'      => $launch->idea->id,
                        'meeting_date' => $date,
                        'notes'        => "اجتماع متابعة مرحلة {$phaseName} بعد الإطلاق للفكرة '{$launch->idea->title}'",
                        'requested_by' => 'committee',
                        'type'         => 'post_launch_followup',
                    ]);

                    Report::create([
                        'idea_id'     => $launch->idea->id,
                        'meeting_id'  => $meeting->id,
                        'report_type' => 'post_launch_followup',
                        'description' => "تقرير متابعة مرحلة {$phaseName} بعد الإطلاق",
                        'status'      => 'pending',
                    ]);
                }

                // إرسال إشعار واحد بعد إنشاء جميع المراحل
                Notification::create([
                    'user_id' => $launch->idea->owner->id,
                    'title'   => "تم إنشاء جميع المتابعات بعد الإطلاق لمشروع '{$launch->idea->title}'",
                    'message' => "تم إنشاء جميع مراحل المتابعة بعد الإطلاق (أسبوع 1، شهر 1، شهر 3، شهر 6) مع الاجتماعات والتقارير الخاصة بها. يمكنك مراجعتها الآن في لوحة المشروع.",
                    'type'    => 'post_launch_followups_created',
                    'is_read' => false,
                ]);

                foreach ($launch->idea->committee->committeeMember as $member) {
                    Notification::create([
                        'user_id' => $member->user_id,
                        'title'   => "تم إنشاء جميع المتابعات بعد الإطلاق لمشروع '{$launch->idea->title}'",
                        'message' => "تم إنشاء جميع مراحل المتابعة والاجتماعات والتقارير بعد الإطلاق. يمكنك مراجعتها الآن في لوحة إدارة المشاريع.",
                        'type'    => 'post_launch_followups_created',
                        'is_read' => false,
                    ]);
                }

                DB::commit();
                $this->info("تم إنشاء المتابعات والاجتماعات والتقارير بالكامل للـ LaunchRequest #{$launch->id}");

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("خطأ أثناء إنشاء المتابعات للـ LaunchRequest #{$launch->id}: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
