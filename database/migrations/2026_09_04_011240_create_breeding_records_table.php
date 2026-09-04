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
        Schema::create('breeding_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('female_animal_id')->constrained('animals')->cascadeOnDelete();
            $table->foreignId('male_animal_id')->nullable()->constrained('animals')->nullOnDelete();
            $table->string('male_reference')->nullable();
            $table->date('heat_detected_on')->nullable();
            $table->date('occurred_on');
            $table->string('method');
            $table->date('pregnancy_check_due_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'occurred_on']);
            $table->index(['female_animal_id', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('breeding_records');
    }
};
