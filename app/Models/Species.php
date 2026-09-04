<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'supports_individuals', 'supports_batches', 'default_gestation_days'])]
class Species extends Model
{
    use HasFactory;

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
