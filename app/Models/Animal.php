<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['farm_id', 'species_id', 'breed_id', 'animal_code', 'ear_tag', 'name', 'sex', 'birth_date', 'status', 'location', 'notes', 'created_by'])]
class Animal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    public function dewormingRecords(): HasMany
    {
        return $this->hasMany(DewormingRecord::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function breedingRecordsAsFemale(): HasMany
    {
        return $this->hasMany(BreedingRecord::class, 'female_animal_id');
    }

    public function pregnancyRecords(): HasMany
    {
        return $this->hasMany(PregnancyRecord::class);
    }

    public function birthRecords(): HasMany
    {
        return $this->hasMany(BirthRecord::class);
    }

    public function weightRecords(): HasMany
    {
        return $this->hasMany(WeightRecord::class);
    }
}
