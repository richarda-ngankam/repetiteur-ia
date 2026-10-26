<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardianship extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'guardian_id',
        'relation',
        'role',
        'status',
        'confirmed_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    public function scopeConfirmees(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }
}
