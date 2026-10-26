<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    use HasUuids;

    // La table n'a que created_at, pas updated_at.
    public $timestamps = false;

    protected $fillable = [
        'purpose',
        'channel',
        'destination',
        'code_hash',
        'user_id',
        'profile_id',
        'attempts',
        'max_attempts',
        'expires_at',
        'consumed_at',
              'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    // HMAC avec la clé de l'application : un simple hash d'un code à 6 chiffres se casse en quelques secondes.
    public static function hacher(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    public function estUtilisable(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < $this->max_attempts;
    }

    public function correspond(string $code): bool
    {
        return hash_equals($this->code_hash, self::hacher($code));
    }
}
