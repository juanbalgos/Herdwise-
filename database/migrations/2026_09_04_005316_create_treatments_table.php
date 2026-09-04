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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('health_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('treated_on');
            $table->string('reason');
            $table->text('diagnosis')->nullable();
            $table->string('medication')->nullable();
            $table->string('dosage')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('veterinarian_name')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'treated_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
