<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Meeting;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyUpcomingMeetings extends Command
{
    protected $signature = 'notify:upcoming-meetings';
    protected $description = 'إرسال إشعار للمستخدمين عند اقتراب موعد اجتماعهم';

    public function handle()
    {
        $now = Carbon::now();

        $reminders = [
            1440 => 'قبل 24 ساعة',
            60   => 'قبل ساعة',
            30   => 'قبل نصف ساعة',
            1    => 'قبل دقيقة',
        ];

        Log::info("بدء فحص الاجتماعات عند {$now}");
        foreach ($reminders as $minutesBefore => $label) {
            $meetings = Meeting::with(['idea.owner', 'idea.committee.committeeMember.user'])
                ->where('meeting_date', '>', $now)
                ->where('meeting_date', '<=', $now->copy()->addMinutes($minutesBefore))
                ->get();
            Log::info("تم العثور على {$meetings->count()} اجتماع(ات) لتذكير {$label}");

            foreach ($meetings as $meeting) {

                if (!$meeting->idea || !$meeting->idea->owner) {
                    Log::warning("الاجتماع {$meeting->id} بدون فكرة أو صاحب فكرة");
                    continue;
                }
                $ownerId = $meeting->idea->owner->id;

                $typeOwner = "meeting_reminder_owner_{$meeting->id}_{$minutesBefore}";
                $alreadyOwner = Notification::where('user_id', $ownerId)
                    ->where('type', $typeOwner)
                    ->exists();

                if (!$alreadyOwner) {
                    Notification::create([
                        'user_id' => $ownerId,
                        'title'   => "تذكير اجتماع {$label}",
'message' => 
    "سبب الاجتماع: " . ($meeting->notes ?? '—') .
    "\nموعد الاجتماع: " . $meeting->meeting_date->format('Y-m-d H:i'),
                        'type'    => $typeOwner,
                        'is_read' => false,
                    ]);

                    Log::info("إشعار لصاحب الفكرة {$ownerId} للاجتماع {$meeting->id}");
                }
                if ($meeting->idea->committee) {
                    foreach ($meeting->idea->committee->committeeMember as $member) {

                        if (!$member->user) continue;

                        $committeeUserId = $member->user->id;

                        $typeCommittee = "meeting_reminder_committee_{$meeting->id}_{$minutesBefore}";

                        $alreadyCommittee = Notification::where('user_id', $committeeUserId)
                            ->where('type', $typeCommittee)
                            ->exists();

                        if (!$alreadyCommittee) {
                            Notification::create([
                                'user_id' => $committeeUserId,
                                'title'   => "تذكير اجتماع فكرة '{$meeting->idea->title}'",
'message' => 
    "سبب الاجتماع: " . ($meeting->notes ?? '—') .
    "\nموعد الاجتماع: " . $meeting->meeting_date->format('Y-m-d H:i'),
                                'type'    => $typeCommittee,
                                'is_read' => false,
                            ]);

                            Log::info("إشعار لعضو لجنة {$committeeUserId} للاجتماع {$meeting->id}");
                        }
                    }
                }
            }
        }

        $this->info('تم تنفيذ تذكير الاجتماعات بنجاح');
        Log::info('انتهاء مهمة تذكير الاجتماعات');

        return 0;
    }
}
