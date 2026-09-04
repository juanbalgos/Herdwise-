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
        Schema::create('birth_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_record_id')->nullable()->constrained()->nullOnDelete();
            $table->date('delivered_on');
            $table->string('outcome');
            $table->unsignedSmallInteger('offspring_count');
            $table->unsignedSmallInteger('live_count')->default(0);
            $table->unsignedSmallInteger('stillborn_count')->default(0);
            $table->unsignedSmallInteger('male_count')->nullable();
            $table->unsignedSmallInteger('female_count')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['farm_id', 'delivered_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birth_records');
    }
};
