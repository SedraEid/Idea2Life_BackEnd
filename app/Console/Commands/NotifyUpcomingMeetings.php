<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Notification;
use App\Models\CommitteeMember;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyUpcomingMeetings extends Command
{
    protected $signature = 'notify:upcoming-meetings';
    protected $description = 'إرسال إشعار للمستخدمين عند اقتراب موعد اجتماعهم (للجنة وصاحب الفكرة)';

    public function handle()
    {
        $now = Carbon::now();

        $reminders = [
            1440 => 'قبل 24 ساعة',
            60   => 'قبل ساعة',
            30   => 'قبل نصف ساعة',
            1    => 'قبل دقيقة',
        ];

        $window = 5;

        Log::info(" بدء فحص الاجتماعات عند {$now}");

        foreach ($reminders as $minutesBefore => $label) {
            $from = $now->copy()->addMinutes($minutesBefore - $window);
            $to   = $now->copy()->addMinutes($minutesBefore + $window);

            $meetings = Meeting::with(['idea', 'report'])
                ->whereBetween('meeting_date', [$from, $to])
                ->get();

            Log::info("تم العثور على {$meetings->count()} اجتماع(ات) ضمن فترة {$label} (من {$from} إلى {$to})");

            foreach ($meetings as $meeting) {
                $idea = $meeting->idea;

                if (!$idea || !$idea->ideaowner) {
                    Log::warning(" الاجتماع {$meeting->id} لا يحتوي على فكرة أو صاحب فكرة — تم تخطيه.");
                    continue;
                }

                $ideaOwner = $idea->ideaowner;
                $ownerUserId = $ideaOwner->user_id ?? null;

                if ($ownerUserId) {
                    $type = "meeting_reminder_owner_{$minutesBefore}m";

                    $alreadyNotified = Notification::where('meeting_id', $meeting->id)
                        ->where('user_id', $ownerUserId)
                        ->where('type', $type)
                        ->exists();

                    if (!$alreadyNotified) {
                        Notification::create([
                            'user_id'    => $ownerUserId,
                            'idea_id'    => $idea->id,
                            'meeting_id' => $meeting->id,
                            'report_id'  => $meeting->report?->id,
                            'title'      => "تذكير باقتراب موعد اجتماعك {$label}",
                            'message'    => "لديك اجتماع بعنوان '" . ($meeting->type ?? 'اجتماع') . "' سيبدأ في " . $meeting->meeting_date->format('Y-m-d H:i'),
                            'type'       => $type,
                            'is_read'    => false,
                        ]);

                        Log::info("\ إشعار جديد لصاحب الفكرة (User {$ownerUserId}) للاجتماع {$meeting->id}.");
                    } else {
                        Log::info(" إشعار مكرر لصاحب الفكرة (User {$ownerUserId}) — تم تخطيه.");
                    }
                }

                if ($idea->committee_id) {
                    $committeeMembers = CommitteeMember::where('committee_id', $idea->committee_id)->get();

                    foreach ($committeeMembers as $member) {
                        $committeeUserId = $member->user_id;
                        if (!$committeeUserId) continue;

                        $type = "meeting_reminder_committee_{$minutesBefore}m";

                        $alreadyNotified = Notification::where('meeting_id', $meeting->id)
                            ->where('user_id', $committeeUserId)
                            ->where('type', $type)
                            ->exists();

                        if (!$alreadyNotified) {
                            Notification::create([
                                'user_id'    => $committeeUserId,
                                'idea_id'    => $idea->id,
                                'meeting_id' => $meeting->id,
                                'report_id'  => $meeting->report?->id,
                                'title'      => "تذكير باقتراب اجتماع الفكرة '{$idea->title}' {$label}",
                                'message'    => "هناك اجتماع للفكرة '{$idea->title}' سيبدأ في " . $meeting->meeting_date->format('Y-m-d H:i'),
                                'type'       => $type,
                                'is_read'    => false,
                            ]);

                            Log::info(" إشعار جديد لعضو لجنة (User {$committeeUserId}) للاجتماع {$meeting->id}.");
                        } else {
                            Log::info(" إشعار مكرر لعضو لجنة (User {$committeeUserId}) — تم تخطيه.");
                        }
                    }
                }
            }
        }

        $this->info(' تم تنفيذ مهمة التذكير بالاجتماعات بنجاح.');
        Log::info(' انتهاء عملية فحص الاجتماعات.');
        return 0;
    }
}
