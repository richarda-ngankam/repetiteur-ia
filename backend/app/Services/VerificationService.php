<?php

namespace App\Services;

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    private const VALIDITE_MINUTES = 10;
    private const DELAI_RENVOI_SECONDES = 60;

    // Retourne false si un code a été envoyé il y a moins d'une minute (le contrôleur répond pareil dans les deux cas).
    public function envoyer(string $purpose, string $channel, string $destination, ?string $userId = null, ?string $profileId = null): bool
    {
        $recent = VerificationCode::where('destination', $destination)
            ->where('purpose', $purpose)
            ->where('created_at', '>', now()->subSeconds(self::DELAI_RENVOI_SECONDES))
            ->exists();

        if ($recent) {
            return false;
        }

        // Un seul code actif par destination et par usage.
        VerificationCode::where('destination', $destination)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'purpose' => $purpose,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => VerificationCode::hacher($code),
            'user_id' => $userId,
            'profile_id' => $profileId,
            'expires_at' => now()->addMinutes(self::VALIDITE_MINUTES),
            'created_at' => now(),
        ]);

        // Envoi réel (SMS / e-mail) à brancher plus tard. En local, le code est lisible dans les logs.
        if (app()->environment('local')) {
            Log::info("Code {$purpose} pour {$destination} : {$code}");
        }

        return true;
    }

    public function verifier(string $purpose, string $destination, string $code): ?VerificationCode
    {
        $enregistrement = VerificationCode::where('destination', $destination)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $enregistrement || ! $enregistrement->estUtilisable()) {
            return null;
        }

        if (! $enregistrement->correspond($code)) {
            $enregistrement->increment('attempts');

            return null;
        }

        $enregistrement->update(['consumed_at' => now()]);

        return $enregistrement;
    }
}