<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\LivestockBatch;
use App\Models\Reminder;
use App\Models\Species;
use App\Models\User;
use App\Notifications\ReminderDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class ReminderNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_due_reminder_notifies_active_operational_farm_members(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $manager = $this->addFarmMember($farm, 'manager');
        $staff = $this->addFarmMember($farm, 'staff');
        $veterinarian = $this->addFarmMember($farm, 'veterinarian');
        $inactiveStaff = $this->addFarmMember($farm, 'staff', false);
        $reminder = $this->reminderForAnimal($farm, $animal, $owner, [
            'title' => 'Vaccination due: G-001',
            'due_on' => '2026-09-08',
        ]);

        $this->artisan('reminders:notify-due')
            ->expectsOutput('Sent 3 notification(s) for 1 reminder(s).')
            ->assertSuccessful();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $veterinarian->notifications()->count());
        $this->assertSame(0, $inactiveStaff->notifications()->count());
        $this->assertNotNull($reminder->fresh()->notified_at);
        $this->assertSame([
            'farm_id' => $farm->id,
            'reminder_id' => $reminder->id,
            'title' => 'Vaccination due: G-001',
            'type' => 'vaccination',
            'due_on' => '2026-09-08',
            'subject_type' => 'animal',
            'subject_id' => $animal->id,
            'subject_code' => 'G-001',
        ], $owner->notifications()->firstOrFail()->data);
    }

    public function test_reminder_outside_the_lead_window_is_not_notified(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $reminder = $this->reminderForAnimal($farm, $animal, $owner, ['due_on' => '2026-09-09']);

        $this->artisan('reminders:notify-due')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
        $this->assertNull($reminder->fresh()->notified_at);
    }

    public function test_completed_reminder_is_not_notified(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $reminder = $this->reminderForAnimal($farm, $animal, $owner, [
            'due_on' => '2026-09-05',
            'status' => 'completed',
        ]);

        $this->artisan('reminders:notify-due')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
        $this->assertNull($reminder->fresh()->notified_at);
    }

    public function test_repeated_command_does_not_duplicate_notifications(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $this->reminderForAnimal($farm, $animal, $owner, ['due_on' => '2026-09-05']);

        $this->artisan('reminders:notify-due')->assertSuccessful();
        $this->artisan('reminders:notify-due')
            ->expectsOutput('Sent 0 notification(s) for 0 reminder(s).')
            ->assertSuccessful();

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_command_uses_the_farms_local_date_for_the_lead_window(): void
    {
        $this->travelTo('2026-09-05 16:30:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'Asia/Manila']);
        $this->reminderForAnimal($farm, $animal, $owner, ['due_on' => '2026-09-09']);

        $this->artisan('reminders:notify-due')->assertSuccessful();

        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_command_rejects_an_invalid_lead_window(): void
    {
        $this->artisan('reminders:notify-due', ['--days' => '31'])
            ->expectsOutput('The notification lead time must be a whole number from 0 to 30 days.')
            ->assertExitCode(Command::INVALID);
    }

    public function test_notification_page_shows_only_the_current_farms_alerts(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $firstFarm, $firstAnimal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $secondFarm = Farm::factory()->create(['timezone' => 'UTC']);
        $secondFarm->users()->attach($owner, ['role' => 'owner', 'is_active' => true]);
        $species = Species::factory()->create();
        $secondAnimal = Animal::factory()->for($secondFarm)->for($species)->create(['animal_code' => 'SECOND-001']);
        $firstReminder = $this->reminderForAnimal($firstFarm, $firstAnimal, $owner, ['title' => 'Visible farm reminder', 'due_on' => '2026-09-05']);
        $secondReminder = $this->reminderForAnimal($secondFarm, $secondAnimal, $owner, ['title' => 'Hidden farm reminder', 'due_on' => '2026-09-05']);
        $owner->notify(new ReminderDueNotification($firstReminder));
        $owner->notify(new ReminderDueNotification($secondReminder));

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $firstFarm->id])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Visible farm reminder')
            ->assertDontSee('Hidden farm reminder')
            ->assertSee('1 unread reminder notification', false)
            ->assertSee('DUE TODAY');
    }

    public function test_batch_reminder_notification_targets_the_batch_record(): void
    {
        [$owner, $farm] = $this->farmWithOwner();
        $species = Species::factory()->create();
        $batch = LivestockBatch::factory()->for($farm)->for($species)->create(['batch_code' => 'B-NOTIFY']);
        $reminder = Reminder::factory()->for($farm)->create([
            'livestock_batch_id' => $batch->id,
            'created_by' => $owner->id,
            'title' => 'Batch vaccination due',
        ]);
        $owner->notify(new ReminderDueNotification($reminder));

        $data = $owner->notifications()->firstOrFail()->data;

        $this->assertSame('batch', $data['subject_type']);
        $this->assertSame($batch->id, $data['subject_id']);
        $this->assertSame('B-NOTIFY', $data['subject_code']);
    }

    public function test_notification_page_escapes_reminder_titles(): void
    {
        [$owner, $farm, $animal] = $this->farmWithOwner();
        $dangerousTitle = "Care <script>alert('unsafe')</script>";
        $reminder = $this->reminderForAnimal($farm, $animal, $owner, ['title' => $dangerousTitle]);
        $owner->notify(new ReminderDueNotification($reminder));

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($dangerousTitle)
            ->assertDontSee($dangerousTitle, false);
    }

    public function test_notification_page_labels_upcoming_and_overdue_work(): void
    {
        $this->travelTo('2026-09-05 01:00:00');
        [$owner, $farm, $animal] = $this->farmWithOwner(['timezone' => 'UTC']);
        $overdue = $this->reminderForAnimal($farm, $animal, $owner, ['title' => 'Overdue task', 'due_on' => '2026-09-04']);
        $upcoming = $this->reminderForAnimal($farm, $animal, $owner, ['title' => 'Upcoming task', 'due_on' => '2026-09-07']);
        $owner->notify(new ReminderDueNotification($overdue));
        $owner->notify(new ReminderDueNotification($upcoming));

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('OVERDUE BY 1 DAY')
            ->assertSee('DUE IN 2 DAYS');
    }

    public function test_user_can_mark_a_current_farm_notification_as_read(): void
    {
        [$owner, $farm, $animal] = $this->farmWithOwner();
        $reminder = $this->reminderForAnimal($farm, $animal, $owner);
        $owner->notify(new ReminderDueNotification($reminder));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->from(route('notifications.index'))
            ->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        [$owner, $farm, $animal] = $this->farmWithOwner();
        $otherUser = $this->addFarmMember($farm, 'staff');
        $reminder = $this->reminderForAnimal($farm, $animal, $owner);
        $otherUser->notify(new ReminderDueNotification($reminder));
        $notification = $otherUser->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $farm->id])
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_reads_only_the_current_farms_notifications(): void
    {
        [$owner, $firstFarm, $firstAnimal] = $this->farmWithOwner();
        $secondFarm = Farm::factory()->create();
        $secondFarm->users()->attach($owner, ['role' => 'owner', 'is_active' => true]);
        $species = Species::factory()->create();
        $secondAnimal = Animal::factory()->for($secondFarm)->for($species)->create();
        $firstReminder = $this->reminderForAnimal($firstFarm, $firstAnimal, $owner);
        $secondReminder = $this->reminderForAnimal($secondFarm, $secondAnimal, $owner);
        $owner->notify(new ReminderDueNotification($firstReminder));
        $firstNotification = $owner->notifications()->firstOrFail();
        $owner->notify(new ReminderDueNotification($secondReminder));
        $secondNotification = $owner->notifications()->where('data->farm_id', $secondFarm->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_farm_id' => $firstFarm->id])
            ->from(route('notifications.index'))
            ->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($firstNotification->fresh()->read_at);
        $this->assertNull($secondNotification->fresh()->read_at);
    }

    /** @return array{User, Farm, Animal} */
    private function farmWithOwner(array $farmAttributes = []): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create($farmAttributes);
        $farm->users()->attach($owner, ['role' => 'owner', 'is_active' => true]);
        $species = Species::factory()->create(['name' => 'Goat', 'code' => fake()->unique()->bothify('GOAT-###')]);
        $animal = Animal::factory()->for($farm)->for($species)->create(['animal_code' => 'G-001']);

        return [$owner, $farm, $animal];
    }

    private function addFarmMember(Farm $farm, string $role, bool $isActive = true): User
    {
        $user = User::factory()->create();
        $farm->users()->attach($user, ['role' => $role, 'is_active' => $isActive]);

        return $user;
    }

    private function reminderForAnimal(Farm $farm, Animal $animal, User $creator, array $attributes = []): Reminder
    {
        return Reminder::factory()->for($farm)->create($attributes + [
            'animal_id' => $animal->id,
            'created_by' => $creator->id,
            'type' => 'vaccination',
            'title' => 'Vaccination due: '.$animal->animal_code,
            'due_on' => today()->toDateString(),
            'status' => 'pending',
        ]);
    }
}
