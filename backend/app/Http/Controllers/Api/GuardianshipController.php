<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Guardianship;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardianshipController extends Controller
{
    use ParentApiHelpers;

    private const MAX_RESPONSABLES = 2;

    // Le parent créateur invite un second responsable par téléphone ou e-mail.
    public function inviter(Request $request, string $profile): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $this->normaliser($request);

        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'role' => ['required', Rule::in(['payer', 'follower'])],
            'relation' => ['sometimes', Rule::in(['parent', 'tutor'])],
        ]);

        $enfant = $this->tutelleConfirmee($parent, $profile)->profile;

        abort_unless($enfant->created_by === $parent->id, 403, 'Seul le parent qui a créé le profil peut ajouter un responsable.');

        $colonne = $data['channel'] === 'email' ? 'email' : 'phone';

        if ($parent->{$colonne} === $data['destination']) {
            throw ValidationException::withMessages(['destination' => ['Tu es déjà responsable de cet enfant.']]);
        }

        $tutelle = DB::transaction(function () use ($data, $enfant, $colonne, $parent) {
            // Verrou sur le profil : deux invitations simultanées ne peuvent pas dépasser la limite.
            Profile::where('id', $enfant->id)->lockForUpdate()->first();

            $actifs = Guardianship::where('profile_id', $enfant->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            if ($actifs >= self::MAX_RESPONSABLES) {
                throw ValidationException::withMessages(['destination' => ['Maximum ' . self::MAX_RESPONSABLES . ' responsables par enfant.']]);
            }

            $invite = User::where($colonne, $data['destination'])->first();

            if ($invite && $invite->type !== 'parent') {
                throw ValidationException::withMessages(['destination' => ['Ce contact ne peut pas être ajouté comme responsable.']]);
            }

            // Contact inconnu : on crée un compte non vérifié, que la personne complétera en s'inscrivant.
            $invite ??= User::create([
                'type' => 'parent',
                $colonne => $data['destination'],
                'name' => $data['name'],
                'country_code' => $parent->country_code,
            ]);

            $existante = Guardianship::where('profile_id', $enfant->id)->where('guardian_id', $invite->id)->first();

            if ($existante && $existante->status !== 'revoked') {
                throw ValidationException::withMessages(['destination' => ['Ce responsable est déjà associé à cet enfant.']]);
            }

            $attributs = [
                'relation' => $data['relation'] ?? 'parent',
                'role' => $data['role'],
                'status' => 'pending',
                'confirmed_at' => null,
                'revoked_at' => null,
            ];

            if ($existante) {
                $existante->update($attributs);

                return $existante;
            }

            return Guardianship::create($attributs + ['profile_id' => $enfant->id, 'guardian_id' => $invite->id]);
        });

        // Envoi réel (SMS / e-mail) à brancher plus tard. En local, l'invitation est lisible dans les logs.
        if (app()->environment('local')) {
            Log::info("Invitation responsable pour {$data['destination']} : {$parent->name} invite à suivre {$enfant->first_name}");
        }

        return response()->json([
            'invitation' => [
                'id' => $tutelle->id,
                'status' => $tutelle->status,
                'role' => $tutelle->role,
                'relation' => $tutelle->relation,
            ],
        ], 201);
    }

    // Invitations reçues par le parent connecté.
    public function recues(Request $request): JsonResponse
    {
        $parent = $this->parentConnecte($request);

        $invitations = Guardianship::with('profile.creator')
            ->where('guardian_id', $parent->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'child_first_name' => $t->profile->first_name,
                'invited_by' => $t->profile->creator?->name,
                'role' => $t->role,
                'relation' => $t->relation,
            ])
            ->values();

        return response()->json(['invitations' => $invitations]);
    }

    public function accepter(Request $request, string $guardianship): JsonResponse
    {
        $tutelle = $this->invitationEnAttente($this->parentConnecte($request), $guardianship);

        $tutelle->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        return response()->json(['message' => 'Invitation acceptée.']);
    }

    public function refuser(Request $request, string $guardianship): JsonResponse
    {
        $tutelle = $this->invitationEnAttente($this->parentConnecte($request), $guardianship);

        $tutelle->update(['status' => 'revoked', 'revoked_at' => now()]);

        return response()->json(['message' => 'Invitation refusée.']);
    }

    private function invitationEnAttente(User $parent, string $id): Guardianship
    {
        abort_unless(Str::isUuid($id), 404, 'Invitation introuvable.');

        $tutelle = Guardianship::where('id', $id)
            ->where('guardian_id', $parent->id)
            ->where('status', 'pending')
            ->first();

        abort_unless($tutelle, 404, 'Invitation introuvable.');

        return $tutelle;
    }
}