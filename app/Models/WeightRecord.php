<?php

namespace App\Models;

use Database\Factories\WeightRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable(['farm_id', 'animal_id', 'livestock_batch_id', 'measured_on', 'weight_kg', 'sample_size', 'measurement_method', 'next_check_on', 'notes', 'recorded_by'])]
class WeightRecord extends Model
{
    /** @use HasFactory<WeightRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['measured_on' => 'date', 'weight_kg' => 'decimal:2', 'next_check_on' => 'date'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LivestockBatch::class, 'livestock_batch_id');
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
