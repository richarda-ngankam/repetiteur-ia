<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\Profile;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModuleUnTest extends TestCase
{
    private const CODE = '123456';

    // ---------- Outils ----------

    private function appareil(string $installId): array
    {
        return ['install_id' => $installId, 'label' => 'Test', 'platform' => 'android'];
    }

    // Le code SMS n'est journalisé qu'en environnement local : on pose un code connu en base.
    private function poserCode(string $purpose, string $destination, bool $expire = false): void
    {
        VerificationCode::create([
            'purpose' => $purpose,
            'channel' => 'sms',
            'destination' => $destination,
            'code_hash' => VerificationCode::hacher(self::CODE),
            'expires_at' => $expire ? now()->subMinute() : now()->addMinutes(10),
            'created_at' => $expire ? now()->subMinutes(11) : now(),
        ]);
    }

    private function corpsInscription(string $numero, string $nom, string $installId, string $code = self::CODE): array
    {
        return [
            'channel' => 'sms',
            'destination' => $numero,
            'code' => $code,
            'name' => $nom,
            'country_code' => 'CM',
            'accept_terms' => true,
            'accept_privacy' => true,
            'device' => $this->appareil($installId),
        ];
    }

    private function inscrire(string $numero, string $nom, string $installId): array
    {
        $this->poserCode('register', $numero);
        $reponse = $this->postJson('/api/v1/auth/register', $this->corpsInscription($numero, $nom, $installId));
        $reponse->assertSuccessful();

        return $reponse->json();
    }

    // Appel authentifié. Le garde est réinitialisé : Sanctum garde l'utilisateur en cache entre deux requêtes d'un même test.
    private function api(string $token, string $methode, string $chemin, array $corps = [])
    {
        $this->app['auth']->forgetGuards();
        $reponse = $this->withToken($token)->json($methode, '/api/v1/' . $chemin, $corps);
        $this->flushHeaders();

        return $reponse;
    }

    private function corpsEnfant(array $surcharge = []): array
    {
        return array_merge([
            'first_name' => 'Aicha',
            'birth_date' => now()->subYears(15)->toDateString(),
            'country_code' => 'CM',
            'system_code' => 'fr',
            'level_code' => '3eme',
            'track_code' => 'Generale',
            'pin' => '1234',
            'role' => 'payer',
            'parental_consent' => true,
        ], $surcharge);
    }

    // Marie crée Aïcha et invite Paul, qui s'inscrit et accepte. Retourne [jeton Marie, jeton Paul, id enfant].
    private function familleAvecDeuxParents(): array
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');
        $enfant = $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant())
            ->assertCreated()->json('child.id');

        $this->api($marie['token'], 'POST', "children/{$enfant}/guardians", [
            'channel' => 'sms',
            'destination' => '+237611111111',
            'name' => 'Paul Ngono',
            'role' => 'follower',
        ])->assertSuccessful();

        $paul = $this->inscrire('+237611111111', 'Paul Ngono', 'install-paul-00000001');
        $invitations = $this->api($paul['token'], 'GET', 'invitations')->assertOk()->json('invitations');
        $this->assertCount(1, $invitations);
        $this->api($paul['token'], 'POST', 'invitations/' . $invitations[0]['id'] . '/accept')->assertSuccessful();

        return [$marie['token'], $paul['token'], $enfant];
    }

    public static function agesRefuses(): array
    {
        return [[8], [18]];
    }

    public static function agesAcceptes(): array
    {
        return [[9], [17]];
    }

    // ---------- Authentification ----------

    public function test_inscription_puis_connexion(): void
    {
        $session = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');
        $this->assertNotEmpty($session['token']);
        $this->assertNotEmpty($session['device_token']);

        $this->poserCode('login', '+237600000001');
        $connexion = $this->postJson('/api/v1/auth/login', [
            'channel' => 'sms',
            'destination' => '+237600000001',
            'code' => self::CODE,
            'device' => $this->appareil('install-marie-0000001'),
        ])->assertOk();

        $this->api($connexion->json('token'), 'GET', 'me')
            ->assertOk()
            ->assertJsonPath('name', 'Marie Ngono');
    }

    public function test_code_faux_refuse(): void
    {
        $this->poserCode('register', '+237600000002');

        $this->postJson('/api/v1/auth/register', $this->corpsInscription('+237600000002', 'Jean', 'install-jean-00000001', '000000'))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_code_expire_refuse(): void
    {
        $this->poserCode('register', '+237600000003', expire: true);

        $this->postJson('/api/v1/auth/register', $this->corpsInscription('+237600000003', 'Luc', 'install-luc-000000001'))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_envoi_de_code_limite_par_numero(): void
    {
        $corps = ['purpose' => 'register', 'channel' => 'sms', 'destination' => '+237600000004'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/code', $corps)->assertSuccessful();
        }

        $this->postJson('/api/v1/auth/code', $corps)->assertStatus(429);
    }

    public function test_jeton_avec_expiration_configuree(): void
    {
        $this->assertNotNull(config('sanctum.expiration'));
    }

    // ---------- Enfants ----------

    #[DataProvider('agesAcceptes')]
    public function test_age_accepte(int $age): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');

        $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant([
            'birth_date' => now()->subYears($age)->toDateString(),
        ]))->assertCreated()->assertJsonPath('child.age', $age);
    }

    #[DataProvider('agesRefuses')]
    public function test_age_refuse(int $age): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');

        $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant([
            'birth_date' => now()->subYears($age)->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('birth_date');

        $this->assertSame(0, DB::table('profiles')->count());
    }

    public function test_consentement_parental_obligatoire(): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');

        $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant(['parental_consent' => false]))
            ->assertStatus(422)->assertJsonValidationErrors('parental_consent');

        $sans = $this->corpsEnfant();
        unset($sans['parental_consent']);
        $this->api($marie['token'], 'POST', 'children', $sans)
            ->assertStatus(422)->assertJsonValidationErrors('parental_consent');

        $this->assertSame(0, DB::table('profiles')->count());
        $this->assertSame(0, DB::table('consents')->where('type', 'parental')->count());
    }

    public function test_consentement_enregistre_avec_ip_hachee_en_hmac(): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');
        $id = $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant())
            ->assertCreated()->json('child.id');

        $ligne = DB::table('consents')->where('type', 'parental')->where('profile_id', $id)->first();

        $this->assertNotNull($ligne);
        $this->assertSame(64, strlen($ligne->ip_hash));
        $this->assertSame(Consent::hacherIp('127.0.0.1'), $ligne->ip_hash);
        $this->assertNotSame(hash('sha256', '127.0.0.1'), $ligne->ip_hash);
    }

    public function test_pin_enfant_hache_une_seule_fois(): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');
        $id = $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant(['pin' => '1234']))
            ->assertCreated()->json('child.id');

        $hash = DB::table('profiles')->where('id', $id)->value('pin_hash');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::check('1234', $hash));
        $this->assertFalse(Hash::check('0000', $hash));
    }

    // ---------- Responsables ----------

    public function test_invitation_acceptee_donne_deux_responsables(): void
    {
        [$marie, $paul, $enfant] = $this->familleAvecDeuxParents();

        $this->api($paul, 'GET', 'children')
            ->assertOk()
            ->assertJsonPath('children.0.first_name', 'Aicha')
            ->assertJsonPath('children.0.my_role', 'follower');

        $detail = $this->api($marie, 'GET', "children/{$enfant}")->assertOk();
        $this->assertCount(2, $detail->json('child.guardians'));
        $this->assertSame(['confirmed', 'confirmed'], array_column($detail->json('child.guardians'), 'status'));
    }

    public function test_troisieme_responsable_refuse(): void
    {
        [$marie, , $enfant] = $this->familleAvecDeuxParents();

        $this->api($marie, 'POST', "children/{$enfant}/guardians", [
            'channel' => 'sms',
            'destination' => '+237622222222',
            'name' => 'Tiers Test',
            'role' => 'follower',
        ])->assertStatus(422);
    }

    public function test_seul_le_parent_createur_peut_inviter(): void
    {
        [, $paul, $enfant] = $this->familleAvecDeuxParents();

        $this->api($paul, 'POST', "children/{$enfant}/guardians", [
            'channel' => 'sms',
            'destination' => '+237622222222',
            'name' => 'Tiers Test',
            'role' => 'follower',
        ])->assertForbidden();
    }

    // ---------- Isolation ----------

    public function test_un_parent_ne_voit_pas_l_enfant_d_un_autre(): void
    {
        $marie = $this->inscrire('+237600000001', 'Marie Ngono', 'install-marie-0000001');
        $enfant = $this->api($marie['token'], 'POST', 'children', $this->corpsEnfant())
            ->assertCreated()->json('child.id');

        $julie = $this->inscrire('+237677777777', 'Julie Etoa', 'install-julie-0000001');

        $this->api($julie['token'], 'GET', "children/{$enfant}")->assertNotFound();
        $this->api($julie['token'], 'GET', 'children')->assertOk()->assertJsonCount(0, 'children');
    }

    // ---------- Erreurs en production ----------

    public function test_erreur_serveur_sans_trace_en_production(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/test-boom', fn () => throw new \RuntimeException('secret interne'));

        $this->getJson('/api/v1/test-boom')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Erreur interne du serveur.']);
    }

    public function test_fiche_introuvable_reste_un_404_en_production(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/test-absent', fn () => throw (new ModelNotFoundException())->setModel(Profile::class));

        $this->getJson('/api/v1/test-absent')->assertNotFound();
    }
}