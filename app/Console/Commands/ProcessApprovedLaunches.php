<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LaunchRequest;
use App\Models\Notification;
use Carbon\Carbon;

class ProcessApprovedLaunches extends Command
{
    protected $signature = 'launch:process-approved';
    protected $description = 'تحويل طلبات الإطلاق الموافق عليها إلى launched عند حلول موعد الإطلاق';
    public function handle()
    {
        $now = Carbon::now();
        $launchRequests = LaunchRequest::with(['idea.roadmap', 'idea.committee.committeeMember', 'idea.owner'])
            ->where('status', 'approved')
            ->whereNotNull('launch_date')
            ->where('launch_date', '<=', $now)
            ->get();

        foreach ($launchRequests as $launchRequest) {
            $idea = $launchRequest->idea;
            $launchRequest->update([
                'status' => 'launched',
            ]);

            Notification::create([
                'user_id' => $idea->owner->id,
                'title' => "تم إطلاق مشروعك '{$idea->title}'",
            'message' => "تم إطلاق المشروع تلقائيًا بتاريخ {$launchRequest->launch_date->format('Y-m-d H:i')}.",
                    'type' => 'launch_launched',
                'is_read' => false,
            ]);

            foreach ($idea->committee->committeeMember as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'title' => "تم إطلاق مشروع '{$idea->title}'",
                    'message' => 'تم إطلاق المشروع تلقائيًا حسب الموعد المحدد.',
                    'type' => 'launch_launched',
                    'is_read' => false,
                ]);
            }

            $this->info("LaunchRequest #{$launchRequest->id} launched successfully.");
        }

        return Command::SUCCESS;
    }
}
