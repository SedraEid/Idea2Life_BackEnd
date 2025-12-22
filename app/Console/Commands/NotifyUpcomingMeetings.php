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

        Log::info("بدء فحص الاجتماعات عند {$now}");

        foreach ($reminders as $minutesBefore => $label) {
            $from = $now->copy()->addMinutes($minutesBefore - $window);
            $to   = $now->copy()->addMinutes($minutesBefore + $window);
            $meetings = Meeting::with(['idea.owner', 'idea.committee.committeeMember.user'])
                ->whereBetween('meeting_date', [$from, $to])
                ->get();

            Log::info("تم العثور على {$meetings->count()} اجتماع(ات) ضمن فترة {$label} (من {$from} إلى {$to})");

            foreach ($meetings as $meeting) {
                $idea = $meeting->idea;
                if (!$idea || !$idea->owner) {
                    Log::warning(" الاجتماع {$meeting->id} لا يحتوي على فكرة أو صاحب فكرة — تم تخطيه.");
                    continue;
                }
                $ownerUserId = $idea->owner->id;
                $typeOwner = "meeting_reminder_owner_{$minutesBefore}m";
                $alreadyNotifiedOwner = Notification::where('meeting_id', $meeting->id)
                    ->where('user_id', $ownerUserId)
                    ->where('type', $typeOwner)
                    ->exists();
                if (!$alreadyNotifiedOwner) {
                    Notification::create([
                        'user_id'    => $ownerUserId,
                        'title'      => "تذكير باقتراب موعد اجتماعك {$label}",
                        'message'    => "لديك اجتماع بعنوان '{$meeting->type}' سيبدأ في {$meeting->meeting_date->format('Y-m-d H:i')}",
                        'type'       => $typeOwner,
                        'is_read'    => false,
                        'meeting_id' => $meeting->id,
                    ]);

                    Log::info(" إشعار جديد لصاحب الفكرة (User {$ownerUserId}) للاجتماع {$meeting->id}.");
                } else {
                    Log::info(" إشعار مكرر لصاحب الفكرة (User {$ownerUserId}) — تم تخطيه.");
                }
                if ($idea->committee) {
                    foreach ($idea->committee->committeeMember as $member) {
                        $committeeUserId = $member->user->id ?? null;
                        if (!$committeeUserId) continue;

                        $typeCommittee = "meeting_reminder_committee_{$minutesBefore}m";

                        $alreadyNotifiedCommittee = Notification::where('meeting_id', $meeting->id)
                            ->where('user_id', $committeeUserId)
                            ->where('type', $typeCommittee)
                            ->exists();

                        if (!$alreadyNotifiedCommittee) {
                            Notification::create([
                                'user_id'    => $committeeUserId,
                                'title'      => "تذكير باقتراب اجتماع الفكرة '{$idea->title}' {$label}",
                                'message'    => "هناك اجتماع للفكرة '{$idea->title}' سيبدأ في {$meeting->meeting_date->format('Y-m-d H:i')}",
                                'type'       => $typeCommittee,
                                'is_read'    => false,
                                'meeting_id' => $meeting->id,
                            ]);

                            Log::info(" إشعار جديد لعضو لجنة (User {$committeeUserId}) للاجتماع {$meeting->id}.");
                        } else {
                            Log::info(" إشعار مكرر لعضو لجنة (User {$committeeUserId}) — تم تخطيه.");
                        }
                    }
                }
            }
        }

        $this->info('تم تنفيذ مهمة التذكير بالاجتماعات بنجاح.');
        Log::info('انتهاء عملية فحص الاجتماعات.');
        return 0;
    }
}
