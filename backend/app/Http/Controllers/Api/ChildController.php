<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Guardianship;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChildController extends Controller
{
    use ParentApiHelpers;

    private const AGE_MIN = 9;
    private const AGE_ADULTE = 18;

    // Liste des enfants du parent connecté.
    public function index(Request $request): JsonResponse
    {
        $parent = $this->parentConnecte($request);

        $enfants = Guardianship::with('profile')
            ->where('guardian_id', $parent->id)
            ->confirmees()
            ->get()
            ->sortBy(fn ($tutelle) => $tutelle->profile->first_name)
            ->map(fn ($tutelle) => $this->resume($tutelle))
            ->values();

        return response()->json(['children' => $enfants]);
    }

    // Création d'un enfant (9 à 17 ans) par un parent connecté : le parent est responsable confirmé.
    public function store(Request $request): JsonResponse
    {
        $parent = $this->parentConnecte($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:60'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'country_code' => ['required', 'size:2', Rule::exists('countries', 'code')->where('is_open', true)],
            'system_code' => ['required', Rule::in(['fr', 'en'])],
            'level_code' => ['required', 'string', 'max:30'],
            'track_code' => ['nullable', 'string', 'max:30'],
            'pin' => ['required', 'digits:4'],
            'role' => ['required', Rule::in(['payer', 'follower'])],
        ]);

        $age = Carbon::parse($data['birth_date'])->age;

        if ($age < self::AGE_MIN) {
            throw ValidationException::withMessages(['birth_date' => ["L'enfant doit avoir au moins " . self::AGE_MIN . ' ans.']]);
        }

        if ($age >= self::AGE_ADULTE) {
            throw ValidationException::withMessages(['birth_date' => ['À partir de ' . self::AGE_ADULTE . ' ans, la personne crée son propre compte.']]);
        }

        $tutelle = DB::transaction(function () use ($data, $parent) {
            // L'enfant n'a pas de compte (user_id vide) : il ouvre son profil avec le PIN sur un appareil autorisé.
            $profile = Profile::create([
                'user_id' => null,
                'created_by' => $parent->id,
                'first_name' => $data['first_name'],
                'birth_date' => $data['birth_date'],
                'country_code' => $data['country_code'],
                'system_code' => $data['system_code'],
                'level_code' => $data['level_code'],
                'track_code' => $data['track_code'] ?? null,
                'pin_hash' => $data['pin'],
            ]);

            $tutelle = Guardianship::create([
                'profile_id' => $profile->id,
                'guardian_id' => $parent->id,
                'relation' => 'parent',
                'role' => $data['role'],
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $tutelle->setRelation('profile', $profile);

            return $tutelle;
        });

        return response()->json(['child' => $this->resume($tutelle)], 201);
    }

    // Fiche d'un enfant avec ses responsables. L'évolution (séances, scores) viendra avec le module 2.
    public function show(Request $request, string $profile): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $tutelle = $this->tutelleConfirmee($parent, $profile);
        $enfant = $tutelle->profile;

        $responsables = Guardianship::with('guardian')
            ->where('profile_id', $enfant->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->map(fn ($t) => [
                'name' => $t->guardian->name,
                'relation' => $t->relation,
                'role' => $t->role,
                'status' => $t->status,
                'is_me' => $t->guardian_id === $parent->id,
            ])
            ->values();

        return response()->json([
            'child' => $this->resume($tutelle) + [
                'birth_date' => $enfant->birth_date->toDateString(),
                'guardians' => $responsables,
            ],
        ]);
    }

    private function resume(Guardianship $tutelle): array
    {
        $profile = $tutelle->profile;

        return [
            'id' => $profile->id,
            'first_name' => $profile->first_name,
            'age' => $profile->age(),
            'country_code' => $profile->country_code,
            'system_code' => $profile->system_code,
            'level_code' => $profile->level_code,
            'track_code' => $profile->track_code,
            'my_role' => $tutelle->role,
        ];
    }
}