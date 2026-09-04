<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_users')->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function activeFarm(?int $preferredFarmId = null): ?Farm
    {
        if ($preferredFarmId) {
            $preferredFarm = $this->farms()
                ->wherePivot('is_active', true)
                ->whereKey($preferredFarmId)
                ->first();

            if ($preferredFarm) {
                return $preferredFarm;
            }
        }

        return $this->farms()->wherePivot('is_active', true)->first();
    }

    public function accessEventsAsSubject(): HasMany
    {
        return $this->hasMany(FarmAccessEvent::class, 'subject_user_id');
    }

    public function accessEventsAsActor(): HasMany
    {
        return $this->hasMany(FarmAccessEvent::class, 'actor_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
