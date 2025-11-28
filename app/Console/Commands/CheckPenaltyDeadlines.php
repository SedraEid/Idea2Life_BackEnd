<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Idea;
use App\Models\Notification;
use Carbon\Carbon;

class CheckPenaltyDeadlines extends Command
{
    protected $signature = 'penalty:check-deadlines';
    protected $description = 'Check projects with unpaid penalties and notify committee if the month passed';

    public function handle()
    {
        $ideas = Idea::with('ideaowner', 'ganttCharts', 'committee.committeeMember')
                     ->get();

        foreach ($ideas as $idea) {
            $lastPenaltyNotification = Notification::where('user_id', $idea->ideaowner->user_id)
                ->where('type', 'critical')
                ->latest()
                ->first();

            if (!$lastPenaltyNotification) continue;

            $deadline = $lastPenaltyNotification->created_at->addMonth();
            if (now()->gt($deadline)) {
                foreach ($idea->committee->committeeMember as $member) {
                    Notification::create([
                        'user_id' => $member->user_id,
                        'title' => 'تم انتهاء المهلة لدفع الغرامة',
                        'message' => "تم إيقاف مشروع '{$idea->title}' لصاحب المشروع لأنه لم يدفع الغرامة خلال المهلة المحددة (شهر).",
                        'type' => 'warning',
                        'is_read' => false,
                    ]);
                }

                Notification::create([
                    'user_id' => $idea->ideaowner->user_id,
                    'title' => 'انتهت مهلة الدفع',
                        'message' => "لم تدفع الغرامة خلال المهلة المحددة (شهر). يجب التواصل مع اللجنة لاتخاذ الإجراءات.",
                    'type' => 'critical',
                    'is_read' => false,
                ]);

                $idea->update(['status' => 'suspended']);
            }
        }

        $this->info('Checked all penalty deadlines successfully.');
    }
}
