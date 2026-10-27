<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Guardianship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceAccessController extends Controller
{
    use ParentApiHelpers;

    private const ESSAIS_MAX = 5;
    private const BLOCAGE_MINUTES = 15;

    // Profils proposés sur ce téléphone : les enfants du compte, puis le parent.
    public function profils(Request $request): JsonResponse
    {
        $user = $this->utilisateurAppareil($request);
        $this->appareilCourant($user);

        $enfants = Guardianship::with('profile')
            ->where('guardian_id', $user->id)
            ->confirmees()
            ->get()
            ->sortBy(fn ($t) => $t->profile->first_name)
            ->map(fn ($t) => [
                'type' => 'child',
                'id' => $t->profile->id,
                'name' => $t->profile->first_name,
                'level_code' => $t->profile->level_code,
            ])
            ->values()
            ->all();

        $parent = ['type' => 'parent', 'id' => 'parent', 'name' => $user->name];

        return response()->json(['profiles' => array_merge($enfants, [$parent])]);
    }

    // Vérifie le PIN d'un profil et remet un jeton propre à ce profil.
    public function deverrouiller(Request $request): JsonResponse
    {
        $user = $this->utilisateurAppareil($request);
        $device = $this->appareilCourant($user);

        $data = $request->validate([
            'profile' => ['required', 'string', 'max:60'],   // « parent » ou l'identifiant d'un enfant
            'pin' => ['required', 'digits:4'],
        ]);

        $enfant = $data['profile'] === 'parent'
            ? null
            : $this->tutelleConfirmee($user, $data['profile'])->profile;

        return DB::transaction(function () use ($data, $user, $device, $enfant) {
            // Verrou : deux essais simultanés ne peuvent pas contourner le compteur.
            $cible = $enfant
                ? Profile::whereKey($enfant->id)->lockForUpdate()->first()
                : User::whereKey($user->id)->lockForUpdate()->first();

            if ($cible->pin_hash === null) {
                return response()->json(['message' => 'Aucun PIN défini pour ce profil.'], 422);
            }

            if ($cible->pin_locked_until?->isFuture()) {
                return $this->reponseBloque($cible);
            }

            if (! Hash::check($data['pin'], $cible->pin_hash)) {
                $cible->pin_failed_attempts++;

                if ($cible->pin_failed_attempts >= self::ESSAIS_MAX) {
                    $cible->pin_failed_attempts = 0;
                    $cible->pin_locked_until = now()->addMinutes(self::BLOCAGE_MINUTES);
                    $cible->save();

                    return $this->reponseBloque($cible);
                }

                $cible->save();

                return response()->json([
                    'message' => 'PIN incorrect.',
                    'attempts_left' => self::ESSAIS_MAX - $cible->pin_failed_attempts,
                ], 422);
            }

            $cible->forceFill(['pin_failed_attempts' => 0, 'pin_locked_until' => null])->save();

            // Un seul jeton par profil et par appareil : il remplace le précédent.
            $nom = ($enfant ? 'profile:' : 'parent:') . $device->id;
            $cible->tokens()->where('name', $nom)->delete();
            $jeton = $cible->createToken($nom, [$enfant ? 'child' : 'parent'])->plainTextToken;

            $device->forceFill(['last_seen_at' => now()])->save();

            return response()->json([
                'profile' => $enfant
                    ? ['type' => 'child', 'id' => $cible->id, 'name' => $cible->first_name]
                    : ['type' => 'parent', 'id' => 'parent', 'name' => $cible->name],
                'token' => $jeton,
            ]);
        });
    }

    // Seul le jeton « appareil » est accepté ici.
    private function utilisateurAppareil(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->tokenCan('device'), 403, 'Appareil non autorisé.');

        return $user;
    }

    // Retrouve l'appareil par le nom du jeton (« device:{id} ») et refuse un appareil retiré.
    private function appareilCourant(User $user): Device
    {
        $id = Str::after($user->currentAccessToken()->name, 'device:');

        $device = Str::isUuid($id)
            ? Device::where('id', $id)->where('user_id', $user->id)->first()
            : null;

        abort_unless($device && $device->revoked_at === null, 403, "Cet appareil n'est plus autorisé.");

        return $device;
    }

    private function reponseBloque($cible): JsonResponse
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($cible->pin_locked_until, false) / 60));

        return response()->json([
            'message' => "Trop d'essais. Réessaie dans {$minutes} minute(s).",
            'retry_in_minutes' => $minutes,
        ], 423);
    }
}