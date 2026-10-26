<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'profile_id',
        'type',
        'document_version',
        'country_code',
        'granted_at',
        'withdrawn_at',
        'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
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

    public static function hacherIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, config('app.key'));
    }

    public function estActif(): bool
    {
        return $this->withdrawn_at === null;
    }
}