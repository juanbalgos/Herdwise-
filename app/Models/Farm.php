<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'location', 'contact_number', 'timezone'])]
class Farm extends Model
{
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
}
