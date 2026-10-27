<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\ParentalControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParentalControlController extends Controller
{
    use ParentApiHelpers;

    public function show(Request $request, string $profile): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $enfant = $this->tutelleConfirmee($parent, $profile)->profile;

        return response()->json([
            'controls' => $this->presenter(ParentalControl::where('profile_id', $enfant->id)->first()),
        ]);
    }

    public function update(Request $request, string $profile): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $enfant = $this->tutelleConfirmee($parent, $profile)->profile;

        $data = $request->validate([
            'daily_limit_minutes' => ['required', Rule::in(ParentalControl::LIMITES)],
            'quiet_hours' => ['required', Rule::in(array_keys(ParentalControl::HORAIRES))],
            'purchases_allowed' => ['required', 'boolean'],
        ]);

        [$debut, $fin] = ParentalControl::HORAIRES[$data['quiet_hours']];

        $controle = ParentalControl::updateOrCreate(
            ['profile_id' => $enfant->id],
            [
                'daily_limit_minutes' => $data['daily_limit_minutes'],
                'quiet_start' => $debut,
                'quiet_end' => $fin,
                'purchases_allowed' => (bool) $data['purchases_allowed'],
                'updated_by' => $parent->id,
            ]
        );

        return response()->json(['controls' => $this->presenter($controle)]);
    }

    // Sans enregistrement, on renvoie les valeurs par défaut de la maquette.
    private function presenter(?ParentalControl $controle): array
    {
        if (! $controle) {
            return [
                'daily_limit_minutes' => ParentalControl::DEFAUT_LIMITE,
                'quiet_hours' => ParentalControl::DEFAUT_HORAIRES,
                'purchases_allowed' => false,
            ];
        }

        return [
            'daily_limit_minutes' => $controle->daily_limit_minutes,
            'quiet_hours' => $controle->codeHoraires(),
            'purchases_allowed' => $controle->purchases_allowed,
        ];
    }
}