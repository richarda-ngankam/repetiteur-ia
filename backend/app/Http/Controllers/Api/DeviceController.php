<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceController extends Controller
{
    use ParentApiHelpers;

    // Les appareils du parent (« Autoriser ce téléphone » dans la maquette).
    public function index(Request $request): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $courant = $this->idAppareilCourant($request);

        $appareils = Device::where('user_id', $parent->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'label' => $d->label,
                'platform' => $d->platform,
                'app_version' => $d->app_version,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                'authorized' => $d->revoked_at === null,
                'is_current' => $d->id === $courant,
            ])
            ->values();

        return response()->json([
            'devices' => $appareils,
            'max_devices' => Device::MAX_PAR_COMPTE,
        ]);
    }

    public function retirer(Request $request, string $device): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $appareil = $this->appareilDuParent($parent->id, $device);

        $appareil->update(['revoked_at' => now()]);

        // Plus aucun jeton ne doit fonctionner depuis cet appareil.
        PersonalAccessToken::whereIn('name', [
            'parent:' . $appareil->id,
            'device:' . $appareil->id,
            'profile:' . $appareil->id,
        ])->delete();

        return response()->json(['message' => 'Appareil retiré.']);
    }

    public function reautoriser(Request $request, string $device): JsonResponse
    {
        $parent = $this->parentConnecte($request);
        $appareil = $this->appareilDuParent($parent->id, $device);

        if ($appareil->revoked_at !== null
            && Device::where('user_id', $parent->id)->actifs()->count() >= Device::MAX_PAR_COMPTE) {
            return response()->json([
                'message' => 'Limite de ' . Device::MAX_PAR_COMPTE . " appareils atteinte. Retire un appareil d'abord.",
            ], 422);
        }

        $appareil->update(['revoked_at' => null]);

        return response()->json(['message' => 'Appareil réautorisé. Il devra se reconnecter.']);
    }

    // L'appareil qui fait la requête, retrouvé par le nom du jeton parent (« parent:{id} »).
    private function idAppareilCourant(Request $request): ?string
    {
        $nom = $request->user()->currentAccessToken()?->name ?? '';

        return str_starts_with($nom, 'parent:') ? Str::after($nom, 'parent:') : null;
    }

    private function appareilDuParent(string $parentId, string $id): Device
    {
        abort_unless(Str::isUuid($id), 404, 'Appareil introuvable.');

        $appareil = Device::where('id', $id)->where('user_id', $parentId)->first();

        abort_unless($appareil, 404, 'Appareil introuvable.');

        return $appareil;
    }
}