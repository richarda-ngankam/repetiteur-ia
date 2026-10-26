<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = [
        'type',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'password',
        'country_code',
        'locale',
        'status',
        'last_login_at',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }

    public function createdProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'created_by');
    }

    public function guardianships(): HasMany
    {
        return $this->hasMany(Guardianship::class, 'guardian_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}