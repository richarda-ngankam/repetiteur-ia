<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentalControl extends Model
{
    use HasUuids;

    // Valeurs proposées par la maquette.
    public const LIMITES = [30, 60, 90, 120, 180];

    public const HORAIRES = [
        '20-7' => ['20:00', '07:00'],
        '21-7' => ['21:00', '07:00'],
        '22-6' => ['22:00', '06:00'],
        'none' => [null, null],
    ];

    public const DEFAUT_LIMITE = 60;
    public const DEFAUT_HORAIRES = '21-7';

    protected $fillable = [
        'profile_id',
        'daily_limit_minutes',
        'quiet_start',
        'quiet_end',
        'purchases_allowed',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'purchases_allowed' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    // Retrouve le code des horaires (« 21-7 »...) à partir des heures stockées.
    public function codeHoraires(): ?string
    {
        if ($this->quiet_start === null) {
            return 'none';
        }

        $debut = substr((string) $this->quiet_start, 0, 5);
        $fin = substr((string) $this->quiet_end, 0, 5);

        foreach (self::HORAIRES as $code => [$d, $f]) {
            if ($d === $debut && $f === $fin) {
                return $code;
            }
        }

        return null;
    }
}