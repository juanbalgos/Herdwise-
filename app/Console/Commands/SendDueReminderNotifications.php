<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDueNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

#[Signature('reminders:notify-due {--days= : Notify this many days before a reminder is due}')]
#[Description('Send in-app notifications for reminders that are due soon')]
class SendDueReminderNotifications extends Command
{
    public function handle(): int
    {
        $leadDays = $this->leadDays();
        if ($leadDays === null) {
            $this->error('The notification lead time must be a whole number from 0 to 30 days.');

            return self::INVALID;
        }

        $reminderCount = 0;
        $notificationCount = 0;

        Farm::query()
            ->select(['id', 'timezone'])
            ->orderBy('id')
            ->chunkById(50, function (Collection $farms) use ($leadDays, &$reminderCount, &$notificationCount): void {
                foreach ($farms as $farm) {
                    $recipients = $farm->users()
                        ->wherePivot('is_active', true)
                        ->wherePivotIn('role', ['owner', 'manager', 'staff'])
                        ->get();

                    if ($recipients->isEmpty()) {
                        continue;
                    }

                    $dueThrough = now($farm->timezone)->addDays($leadDays)->toDateString();

                    $farm->reminders()
                        ->where('status', 'pending')
                        ->whereNull('notified_at')
                        ->whereDate('due_on', '<=', $dueThrough)
                        ->select('id')
                        ->orderBy('id')
                        ->chunkById(100, function (Collection $reminders) use ($recipients, $dueThrough, &$reminderCount, &$notificationCount): void {
                            foreach ($reminders as $reminder) {
                                $sent = $this->notifyReminder($reminder->id, $dueThrough, $recipients);
                                if ($sent > 0) {
                                    $reminderCount++;
                                    $notificationCount += $sent;
                                }
                            }
                        });
                }
            });

        $this->info("Sent {$notificationCount} notification(s) for {$reminderCount} reminder(s).");

        return self::SUCCESS;
    }

    private function leadDays(): ?int
    {
        $value = $this->option('days') ?? config('herdwise.reminder_notification_lead_days');
        $leadDays = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 30],
        ]);

        return $leadDays === false ? null : $leadDays;
    }

    /** @param Collection<int, User> $recipients */
    private function notifyReminder(int $reminderId, string $dueThrough, Collection $recipients): int
    {
        return DB::transaction(function () use ($reminderId, $dueThrough, $recipients): int {
            $reminder = Reminder::query()
                ->with(['animal', 'batch'])
                ->lockForUpdate()
                ->findOrFail($reminderId);

            if ($reminder->status !== 'pending' || $reminder->notified_at || $reminder->due_on->toDateString() > $dueThrough) {
                return 0;
            }

            Notification::send($recipients, new ReminderDueNotification($reminder));
            $reminder->update(['notified_at' => now()]);

            return $recipients->count();
        });
    }
}
