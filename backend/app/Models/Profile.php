<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'created_by',
        'first_name',
        'birth_date',
        'country_code',
        'system_code',
        'level_code',
        'track_code',
        'level_changed_at',
        'pin_hash',
        'pin_failed_attempts',
        'pin_locked_until',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'level_changed_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'pin_hash' => 'hashed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function guardianships(): HasMany
    {
        return $this->hasMany(Guardianship::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function age(): int
    {
        return $this->birth_date->age;
    }

    public function pinEstBloque(): bool
    {
        return $this->pin_locked_until !== null && $this->pin_locked_until->isFuture();
    }
}