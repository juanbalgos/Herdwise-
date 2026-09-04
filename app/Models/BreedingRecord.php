<?php

namespace App\Models;

use Database\Factories\BreedingRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['farm_id', 'female_animal_id', 'male_animal_id', 'male_reference', 'heat_detected_on', 'occurred_on', 'method', 'pregnancy_check_due_on', 'notes', 'recorded_by'])]
class BreedingRecord extends Model
{
    /** @use HasFactory<BreedingRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['heat_detected_on' => 'date', 'occurred_on' => 'date', 'pregnancy_check_due_on' => 'date'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function femaleAnimal(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'female_animal_id');
    }

    public function maleAnimal(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'male_animal_id');
    }

    public function pregnancyRecords(): HasMany
    {
        return $this->hasMany(PregnancyRecord::class);
    }

    public function reminder(): MorphOne
    {
        return $this->morphOne(Reminder::class, 'source');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
