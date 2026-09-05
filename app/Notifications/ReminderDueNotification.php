<?php

namespace App\Notifications;

use App\Models\Animal;
use App\Models\LivestockBatch;
use App\Models\Reminder;
use Illuminate\Notifications\Notification;

class ReminderDueNotification extends Notification
{
    public const DATABASE_TYPE = 'herdwise.reminder-due';

    public function __construct(public Reminder $reminder) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return self::DATABASE_TYPE;
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->reminder->loadMissing(['animal', 'batch']);
        $subject = $this->reminder->animal ?? $this->reminder->batch;

        return [
            'farm_id' => $this->reminder->farm_id,
            'reminder_id' => $this->reminder->id,
            'title' => $this->reminder->title,
            'type' => $this->reminder->type,
            'due_on' => $this->reminder->due_on->toDateString(),
            'subject_type' => match (true) {
                $subject instanceof Animal => 'animal',
                $subject instanceof LivestockBatch => 'batch',
                default => null,
            },
            'subject_id' => $subject?->id,
            'subject_code' => $subject instanceof Animal ? $subject->animal_code : $subject?->batch_code,
        ];
    }
}
