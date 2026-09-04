<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['species_id', 'name'])]
class Breed extends Model
{
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
