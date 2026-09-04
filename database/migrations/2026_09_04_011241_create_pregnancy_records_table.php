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
        Schema::create('pregnancy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('breeding_record_id')->nullable()->constrained()->nullOnDelete();
            $table->date('checked_on');
            $table->string('status');
            $table->string('check_method')->nullable();
            $table->unsignedSmallInteger('gestation_days')->nullable();
            $table->date('expected_delivery_on')->nullable();
            $table->boolean('is_estimated')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'status', 'expected_delivery_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pregnancy_records');
    }
};
