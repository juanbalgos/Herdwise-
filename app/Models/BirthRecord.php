<?php

namespace App\Models;

use Database\Factories\BirthRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farm_id', 'animal_id', 'pregnancy_record_id', 'delivered_on', 'outcome', 'offspring_count', 'live_count', 'stillborn_count', 'male_count', 'female_count', 'notes', 'recorded_by'])]
class BirthRecord extends Model
{
    /** @use HasFactory<BirthRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['delivered_on' => 'date'];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    public function pregnancyRecord(): BelongsTo
    {
        return $this->belongsTo(PregnancyRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
