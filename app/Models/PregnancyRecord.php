<?php

namespace App\Models;

use Database\Factories\PregnancyRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['farm_id', 'animal_id', 'breeding_record_id', 'checked_on', 'status', 'check_method', 'gestation_days', 'expected_delivery_on', 'is_estimated', 'notes', 'recorded_by'])]
class PregnancyRecord extends Model
{
    /** @use HasFactory<PregnancyRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['checked_on' => 'date', 'expected_delivery_on' => 'date', 'is_estimated' => 'boolean'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function breedingRecord(): BelongsTo
    {
        return $this->belongsTo(BreedingRecord::class);
    }

    public function birthRecords(): HasMany
    {
        return $this->hasMany(BirthRecord::class);
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
