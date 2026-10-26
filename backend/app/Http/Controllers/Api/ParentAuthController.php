<?php

namespace App\Http\Controllers\Api;

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
    public function __construct(private VerificationService $verification)
    {
    }

    public function demanderCode(Request $request): JsonResponse
    {
        $this->normaliser($request);

        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
        ]);

        $this->verification->envoyer('register', $data['channel'], $data['destination']);

        // Même réponse dans tous les cas : on ne révèle pas si un code vient d'être envoyé.
        return response()->json(['message' => "Si le contact est valide, un code vient d'être envoyé."], 202);
    }

    public function inscrire(Request $request): JsonResponse
    {
        $this->normaliser($request);

        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'destination' => $this->reglesDestination($request->input('channel')),
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'country_code' => ['required', 'size:2', Rule::exists('countries', 'code')->where('is_open', true)],
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'device.install_id' => ['required', 'string', 'min:16', 'max:100'],
            'device.label' => ['nullable', 'string', 'max:80'],
            'device.platform' => ['required', Rule::in(['android', 'ios'])],
            'device.app_version' => ['nullable', 'string', 'max:20'],
        ]);

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

            $installHash = hash_hmac('sha256', $data['device']['install_id'], config('app.key'));
            $device = Device::firstOrNew(['user_id' => $user->id, 'install_hash' => $installHash]);

            if ($device->revoked_at !== null) {
                abort(403, 'Cet appareil a été retiré de ce compte.');
            }

            if (! $device->exists && Device::where('user_id', $user->id)->actifs()->count() >= Device::MAX_PAR_COMPTE) {
                abort(403, 'Limite de ' . Device::MAX_PAR_COMPTE . ' appareils atteinte.');
            }

            $device->fill([
                'label' => $data['device']['label'] ?? null,
                'platform' => $data['device']['platform'],
                'app_version' => $data['device']['app_version'] ?? null,
                'last_seen_at' => now(),
            ])->save();

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