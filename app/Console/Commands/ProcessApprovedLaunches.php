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

        $roadmapStages = [
            "تقديم الفكرة",
            "التقييم الأولي",
            "التخطيط المنهجي",
            "التقييم المتقدم قبل التمويل",
            "التمويل",
            "التنفيذ والتطوير",
            "الإطلاق",
            "المتابعة بعد الإطلاق",
            "استقرار المشروع وانفصاله عن المنصة",
        ];

        foreach ($launchRequests as $launchRequest) {
            $idea = $launchRequest->idea;
            $launchRequest->update([
                'status' => 'launched',
            ]);
            $currentStage = "المتابعة بعد الإطلاق";
            $index = array_search($currentStage, $roadmapStages);
            $progress = round((($index + 1) / count($roadmapStages)) * 100, 2);

            $idea->update([
                'status' => 'launched',
                'roadmap_stage' => $currentStage,
            ]);

            if ($roadmap = $idea->roadmap) {
                $roadmap->update([
                    'current_stage'       => $currentStage,
                    'stage_description'   => 'تم إطلاق المشروع رسميًا وبدأت مرحلة المتابعة بعد الإطلاق.',
                    'progress_percentage' => $progress,
                    'last_update'         => now(),
                    'next_step'           => $roadmapStages[$index + 1] ?? null,
                ]);
            }

            Notification::create([
                'user_id' => $idea->owner->id,
                'title'   => "تم إطلاق مشروعك '{$idea->title}'",
                'message' => "تم إطلاق المشروع تلقائيًا بتاريخ {$launchRequest->launch_date->format('Y-m-d H:i')}، وبدأت مرحلة المتابعة بعد الإطلاق.",
                'type'    => 'launch_launched',
                'is_read' => false,
            ]);

            foreach ($idea->committee->committeeMember as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'title'   => "تم إطلاق مشروع '{$idea->title}'",
                    'message' => 'تم إطلاق المشروع تلقائيًا حسب الموعد المحدد وبدأت مرحلة المتابعة بعد الإطلاق.',
                    'type'    => 'launch_launched',
                    'is_read' => false,
                ]);
            }

            $this->info("LaunchRequest #{$launchRequest->id} launched successfully.");
        }

        return Command::SUCCESS;
    }
}
