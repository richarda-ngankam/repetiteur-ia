<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Guardianship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait ParentApiHelpers
{
    // Le jeton doit appartenir à un parent (capacité « parent »).
    private function parentConnecte(Request $request): User
    {
        $user = $request->user();

        abort_unless($user && $user->type === 'parent' && $user->tokenCan('parent'), 403, 'Accès réservé aux parents.');

        return $user;
    }

    // Un parent ne voit que les enfants dont la tutelle est confirmée ; sinon 404 (on ne révèle rien).
    private function tutelleConfirmee(User $parent, string $profileId): Guardianship
    {
        abort_unless(Str::isUuid($profileId), 404, 'Enfant introuvable.');

        $tutelle = Guardianship::with('profile')
            ->where('guardian_id', $parent->id)
            ->where('profile_id', $profileId)
            ->confirmees()
            ->first();

        abort_unless($tutelle, 404, 'Enfant introuvable.');

        return $tutelle;
    }

    private function normaliser(Request $request): void
    {
        $destination = trim((string) $request->input('destination'));

        $destination = $request->input('channel') === 'email'
            ? mb_strtolower($destination)
            : preg_replace('/[\s.\-()]/', '', $destination);

        $request->merge(['destination' => $destination]);
    }

    private function reglesDestination(?string $channel): array
    {
        return $channel === 'email'
            ? ['required', 'email', 'max:255']
            : ['required', 'regex:/^\+[1-9]\d{7,14}$/'];
    }
}