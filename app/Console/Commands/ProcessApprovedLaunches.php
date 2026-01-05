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
            ['name' => 'Idea Submission', 'actor' => 'Idea Owner'],
            ['name' => 'Initial Evaluation', 'actor' => 'Committee'],
            ['name' => 'Systematic Planning / Business Plan Preparation', 'actor' => 'Idea Owner'],
            ['name' => 'Advanced Evaluation Before Funding', 'actor' => 'Committee'],
            ['name' => 'Funding', 'actor' => 'Idea Owner (Funding Request) + Committee / Investor'],
            ['name' => 'Execution and Development', 'actor' => 'Idea Owner (Implementation) + Committee (Stage Evaluation)'],
            ['name' => 'Launch', 'actor' => 'Idea Owner + Committee'],
            ['name' => 'Post-Launch Follow-up', 'actor' => 'Idea Owner + Committee'],
            ['name' => 'Project Stabilization / Platform Separation', 'actor' => 'Idea Owner (Separation Request) + Committee (Approval of Stabilization)'],
        ];

        foreach ($launchRequests as $launchRequest) {
            $idea = $launchRequest->idea;
            $currentStageName = 'Post-Launch Follow-up'; 
            $currentStageIndex = array_search($currentStageName, array_column($roadmapStages, 'name'));
            $nextStageName = $currentStageIndex + 1 < count($roadmapStages) ? $roadmapStages[$currentStageIndex + 1]['name'] : null;
            $nextActor = $currentStageIndex + 1 < count($roadmapStages) ? $roadmapStages[$currentStageIndex + 1]['actor'] : null;
            $progressPercentage = round((($currentStageIndex + 1) / count($roadmapStages)) * 100, 2);

            $launchRequest->update([
                'status' => 'launched',
            ]);

            $idea->update([
                'roadmap_stage' => $currentStageName,
            ]);

            $stageDescription = "Stage executed by: " . $roadmapStages[$currentStageIndex]['actor'] .
                                ($nextStageName ? " | Next stage: $nextStageName (executed by: $nextActor)" : " | Project in post-launch follow-up.");

            if ($roadmap = $idea->roadmap) {
                $roadmap->update([
                    'current_stage' => $currentStageName,
                    'stage_description' => $stageDescription,
                    'progress_percentage' => $progressPercentage,
                    'last_update' => now(),
                    'next_step' => $nextStageName ? "Proceed to $nextStageName" : "Monitor project stabilization",
                ]);
            } else {
                \App\Models\Roadmap::create([
                    'idea_id' => $idea->id,
                    'current_stage' => $currentStageName,
                    'stage_description' => $stageDescription,
                    'progress_percentage' => $progressPercentage,
                    'last_update' => now(),
                    'next_step' => $nextStageName ? "Proceed to $nextStageName" : "Monitor project stabilization",
                ]);
            }

            Notification::create([
                'user_id' => $idea->owner->id,
                'title'   => "Your project '{$idea->title}' has been launched",
                'message' => "The project was automatically launched on {$launchRequest->launch_date->format('Y-m-d H:i')}, entering the post-launch follow-up stage.",
                'type'    => 'launch_launched',
                'is_read' => false,
            ]);

            foreach ($idea->committee->committeeMember as $member) {
                Notification::create([
                    'user_id' => $member->user_id,
                    'title'   => "Project '{$idea->title}' launched",
                    'message' => 'The project has been automatically launched according to the scheduled date and is now in the post-launch follow-up stage.',
                    'type'    => 'launch_launched',
                    'is_read' => false,
                ]);
            }

            $this->info("LaunchRequest #{$launchRequest->id} processed successfully.");
        }

        return Command::SUCCESS;
    }
}
