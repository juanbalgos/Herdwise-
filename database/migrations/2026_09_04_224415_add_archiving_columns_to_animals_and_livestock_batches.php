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
        Schema::table('animals', function (Blueprint $table) {
            $table->string('status_before_archive')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['farm_id', 'archived_at']);
        });

        Schema::table('livestock_batches', function (Blueprint $table) {
            $table->string('status_before_archive')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['farm_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'archived_at']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['status_before_archive', 'archived_at']);
        });

        Schema::table('livestock_batches', function (Blueprint $table) {
            $table->dropIndex(['farm_id', 'archived_at']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['status_before_archive', 'archived_at']);
        });
    }
};
