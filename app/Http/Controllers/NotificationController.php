<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Notifications\ReminderDueNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $farm = $this->currentFarm($request);
        $today = CarbonImmutable::now($farm->timezone)->startOfDay();
        $notifications = $request->user()
            ->notifications()
            ->where('type', ReminderDueNotification::DATABASE_TYPE)
            ->where('data->farm_id', $farm->id)
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification): array => $this->present($notification, $farm, $today));
        $unreadNotificationCount = $request->user()
            ->unreadNotifications()
            ->where('type', ReminderDueNotification::DATABASE_TYPE)
            ->where('data->farm_id', $farm->id)
            ->count();

        return view('notifications.index', [
            'farm' => $farm,
            'notifications' => $notifications,
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $this->notificationForFarm($request, $farm, $notification)->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $farm = $this->currentFarm($request);
        $request->user()
            ->unreadNotifications()
            ->where('type', ReminderDueNotification::DATABASE_TYPE)
            ->where('data->farm_id', $farm->id)
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications for this farm were marked as read.');
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification, Farm $farm, CarbonImmutable $today): array
    {
        $data = $notification->data;
        $dueOn = CarbonImmutable::parse($data['due_on'], $farm->timezone)->startOfDay();
        $daysUntilDue = (int) $today->diffInDays($dueOn, false);

        $dueLabel = match (true) {
            $daysUntilDue < 0 => 'Overdue by '.abs($daysUntilDue).' '.str('day')->plural(abs($daysUntilDue)),
            $daysUntilDue === 0 => 'Due today',
            default => 'Due in '.$daysUntilDue.' '.str('day')->plural($daysUntilDue),
        };

        return [
            'id' => $notification->id,
            'title' => $data['title'],
            'type' => str($data['type'])->headline()->toString(),
            'subject_code' => $data['subject_code'] ?? 'Farm task',
            'due_on' => $dueOn,
            'due_label' => $dueLabel,
            'target_url' => $this->targetUrl($data),
            'created_at' => $notification->created_at->timezone($farm->timezone),
            'is_unread' => $notification->unread(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function targetUrl(array $data): string
    {
        if (blank($data['subject_id'] ?? null)) {
            return route('activities.index');
        }

        return match ($data['subject_type'] ?? null) {
            'animal' => route('animals.show', ['animal' => $data['subject_id']]),
            'batch' => route('batches.show', ['batch' => $data['subject_id']]),
            default => route('activities.index'),
        };
    }

    private function notificationForFarm(Request $request, Farm $farm, string $notificationId): DatabaseNotification
    {
        return $request->user()
            ->notifications()
            ->where('type', ReminderDueNotification::DATABASE_TYPE)
            ->where('data->farm_id', $farm->id)
            ->findOrFail($notificationId);
    }
}
