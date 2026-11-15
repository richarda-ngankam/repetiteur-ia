<?php

namespace Tests;

use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // La table countries doit contenir CM ouvert, sinon toute inscription est refusée.
    protected bool $seed = true;

    protected string $seeder = CountrySeeder::class;

    private const BASE_DE_TEST = 'repetiteur_test';

    // Exécuté AVANT migrate:fresh : on refuse de toucher à une autre base.
    protected function beforeRefreshingDatabase(): void
    {
        $base = DB::connection()->getDatabaseName();

        if ($base !== self::BASE_DE_TEST) {
            throw new \RuntimeException(
                "Tests interrompus : la base active est « {$base} », pas « " . self::BASE_DE_TEST . ' ».'
            );
        }
    }
}