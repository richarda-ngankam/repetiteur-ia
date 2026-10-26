<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        // Code, nom affiché, indicatif, devise, fuseau horaire.
        $pays = [
            ['CM', 'Cameroun', '+237', 'XAF', 'Africa/Douala'],
            ['CI', "C\u{f4}te d'Ivoire", '+225', 'XOF', 'Africa/Abidjan'],
            ['SN', "S\u{e9}n\u{e9}gal", '+221', 'XOF', 'Africa/Dakar'],
            ['BJ', "B\u{e9}nin", '+229', 'XOF', 'Africa/Porto-Novo'],
            ['GA', 'Gabon', '+241', 'XAF', 'Africa/Libreville'],
            ['TG', 'Togo', '+228', 'XOF', 'Africa/Lome'],
            ['BF', 'Burkina Faso', '+226', 'XOF', 'Africa/Ouagadougou'],
            ['ML', 'Mali', '+223', 'XOF', 'Africa/Bamako'],
            ['CG', "R\u{e9}publique du Congo", '+242', 'XAF', 'Africa/Brazzaville'],
        ];

        $lignes = array_map(fn ($p) => [
            'code' => $p[0],
            'name' => $p[1],
            'phone_prefix' => $p[2],
            'currency' => $p[3],
            'default_locale' => 'fr',
            'timezone' => $p[4],
            'created_at' => now(),
            'updated_at' => now(),
        ], $pays);

        // config et is_open gardent leurs valeurs par défaut : l'ouverture d'un pays est une décision à part.
        DB::table('countries')->upsert(
            $lignes,
            ['code'],
            ['name', 'phone_prefix', 'currency', 'default_locale', 'timezone', 'updated_at']
        );
    }
}