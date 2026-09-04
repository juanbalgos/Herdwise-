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
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 9, 2);
            $table->unsignedSmallInteger('sample_size')->nullable();
            $table->string('measurement_method')->nullable();
            $table->date('next_check_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'measured_on']);
            $table->index(['animal_id', 'measured_on']);
            $table->index(['livestock_batch_id', 'measured_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_records');
    }
};
