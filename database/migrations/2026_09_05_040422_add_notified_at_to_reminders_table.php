<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable();
            $table->index(
                ['farm_id', 'status', 'notified_at', 'due_on'],
                'reminders_notification_dispatch_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_notification_dispatch_index');
            $table->dropColumn('notified_at');
        });
    }
};
