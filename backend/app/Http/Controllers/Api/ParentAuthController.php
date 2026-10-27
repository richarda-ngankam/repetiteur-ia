<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ParentApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use App\Models\Device;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParentAuthController extends Controller
{
    use ParentApiHelpers;

    public function __construct(private VerificationService $verification)
    {
    }

    public function demanderCode(Request $request): JsonResponse
    {
        $this->normaliser($request);

        $data = $request->validate([
            'purpose' => ['sometimes', Rule::in(['register', 'login'])],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
        ]);

        $purpose = $data['purpose'] ?? 'register';

        // Pour une connexion, on n'envoie un code que si un compte vérifié existe (pas de SMS inutile).
        if ($purpose === 'register' || $this->compteVerifie($data['channel'], $data['destination'])) {
            $this->verification->envoyer($purpose, $data['channel'], $data['destination']);
        }

        // Même réponse dans tous les cas : on ne révèle ni l'existence d'un compte ni l'envoi d'un code.
        return response()->json(['message' => "Si le contact est valide, un code vient d'être envoyé."], 202);
    }

    public function inscrire(Request $request): JsonResponse
    {
        $this->normaliser($request);

        $data = $request->validate(array_merge([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'country_code' => ['required', 'size:2', Rule::exists('countries', 'code')->where('is_open', true)],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], $this->reglesAppareil()));

        if (! $this->verification->verifier('register', $data['destination'], $data['code'])) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $colonne = $data['channel'] === 'email' ? 'email' : 'phone';
        $colonneVerif = $data['channel'] === 'email' ? 'email_verified_at' : 'phone_verified_at';

        $resultat = DB::transaction(function () use ($data, $request, $colonne, $colonneVerif) {
            $user = User::where($colonne, $data['destination'])->lockForUpdate()->first();

            // Un compte déjà vérifié existe : c'est une connexion, pas une inscription.
            if ($user && ($user->type !== 'parent' || $user->{$colonneVerif} !== null)) {
                abort(409, 'Un compte existe déjà avec ce contact. Connecte-toi.');
            }

            // Un compte non vérifié peut exister : le parent a été invité par son enfant ou par l'autre parent.
            $user ??= new User([$colonne => $data['destination'], 'type' => 'parent']);
            $user->name = $data['name'];
            $user->country_code = $data['country_code'];
            $user->{$colonneVerif} = now();
            $user->last_login_at = now();
            $user->save();

            foreach (['terms', 'privacy'] as $type) {
                Consent::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'profile_id' => null,
                        'type' => $type,
                        'document_version' => config('legal.version'),
                    ],
                    [
                        'country_code' => $user->country_code,
                        'granted_at' => now(),
                        'ip_hash' => Consent::hacherIp((string) $request->ip()),
                    ]
                );
            }

            $device = $this->enregistrerAppareil($user, $data['device']);

            return [
                'user' => $user,
                'token' => $user->createToken('appareil-' . $device->id, ['parent'])->plainTextToken,
            ];
        });

        return response()->json([
            'user_id' => $resultat['user']->id,
            'name' => $resultat['user']->name,
            'token' => $resultat['token'],
        ], 201);
    }

    public function connecter(Request $request): JsonResponse
    {
        $this->normaliser($request);

        $data = $request->validate(array_merge([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
            'code' => ['required', 'digits:6'],
        ], $this->reglesAppareil()));

        if (! $this->verification->verifier('login', $data['destination'], $data['code'])) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        // Le code prouve que la personne possède le contact : on peut dire qu'aucun compte n'existe.
        $user = $this->compteVerifie($data['channel'], $data['destination']);

        if (! $user) {
            return response()->json(['message' => 'Aucun compte avec ce contact. Inscris-toi.'], 404);
        }

        if ($user->status === 'suspended') {
            return response()->json(['message' => 'Ce compte est suspendu.'], 403);
        }

        $token = DB::transaction(function () use ($user, $data) {
            $device = $this->enregistrerAppareil($user, $data['device']);

            $user->forceFill(['last_login_at' => now()])->save();

            return $user->createToken('appareil-' . $device->id, [$user->type === 'parent' ? 'parent' : 'student'])->plainTextToken;
        });

        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'token' => $token,
        ]);
    }

    public function moi(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'type' => $user->type,
            'name' => $user->name,
            'country_code' => $user->country_code,
            'locale' => $user->locale,
            'status' => $user->status,
        ]);
    }

    public function deconnecter(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function compteVerifie(string $channel, string $destination): ?User
    {
        return User::where($channel === 'email' ? 'email' : 'phone', $destination)
            ->whereNotNull($channel === 'email' ? 'email_verified_at' : 'phone_verified_at')
            ->first();
    }

    private function reglesAppareil(): array
    {
        return [
            'device.install_id' => ['required', 'string', 'min:16', 'max:100'],
            'device.label' => ['nullable', 'string', 'max:80'],
            'device.platform' => ['required', Rule::in(['android', 'ios'])],
            'device.app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    // Trois appareils actifs au maximum par compte ; un appareil retiré ne revient pas tout seul.
    private function enregistrerAppareil(User $user, array $appareil): Device
    {
        $installHash = hash_hmac('sha256', $appareil['install_id'], config('app.key'));
        $device = Device::firstOrNew(['user_id' => $user->id, 'install_hash' => $installHash]);

        if ($device->revoked_at !== null) {
            abort(403, 'Cet appareil a été retiré de ce compte.');
        }

        if (! $device->exists && Device::where('user_id', $user->id)->actifs()->count() >= Device::MAX_PAR_COMPTE) {
            abort(403, 'Limite de ' . Device::MAX_PAR_COMPTE . ' appareils atteinte.');
        }

        $device->fill([
            'label' => $appareil['label'] ?? null,
            'platform' => $appareil['platform'],
            'app_version' => $appareil['app_version'] ?? null,
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }
}