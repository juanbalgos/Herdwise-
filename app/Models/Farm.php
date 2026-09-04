<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'location', 'contact_number', 'timezone'])]
class Farm extends Model
{
    use HasFactory;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'farm_users')->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(LivestockBatch::class);
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

    public function breedingRecords(): HasMany
    {
        return $this->hasMany(BreedingRecord::class);
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

    public function invitations(): HasMany
    {
        return $this->hasMany(FarmInvitation::class);
    }

    public function accessEvents(): HasMany
    {
        return $this->hasMany(FarmAccessEvent::class);
    }
}
