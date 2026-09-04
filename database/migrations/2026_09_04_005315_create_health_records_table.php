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
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->text('observation');
            $table->text('symptoms')->nullable();
            $table->string('severity')->default('low');
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('appetite')->nullable();
            $table->string('behavior')->nullable();
            $table->string('resulting_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'observed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
