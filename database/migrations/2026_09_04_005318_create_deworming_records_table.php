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
        Schema::create('deworming_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('livestock_batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->date('administered_on');
            $table->string('dose')->nullable();
            $table->string('administered_by')->nullable();
            $table->date('next_due_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'next_due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deworming_records');
    }
};
