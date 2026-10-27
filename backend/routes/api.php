<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
\Illuminate\Support\Facades\Route::prefix('v1')->group(function () {
    $auth = \App\Http\Controllers\Api\ParentAuthController::class;
    $enfants = \App\Http\Controllers\Api\ChildController::class;
    $tutelles = \App\Http\Controllers\Api\GuardianshipController::class;
    $acces = \App\Http\Controllers\Api\DeviceAccessController::class;
    $appareils = \App\Http\Controllers\Api\DeviceController::class;
    $controles = \App\Http\Controllers\Api\ParentalControlController::class;
    $pins = \App\Http\Controllers\Api\PinController::class;

    \Illuminate\Support\Facades\Route::post('auth/code', [$auth, 'demanderCode'])->middleware('throttle:5,1');
    \Illuminate\Support\Facades\Route::post('auth/register', [$auth, 'inscrire'])->middleware('throttle:10,1');
    \Illuminate\Support\Facades\Route::post('auth/login', [$auth, 'connecter'])->middleware('throttle:10,1');

        \Illuminate\Support\Facades\Route::middleware('auth:sanctum')->group(function () use ($auth, $enfants, $tutelles, $acces, $appareils, $controles, $pins) {
                \Illuminate\Support\Facades\Route::get('me', [$auth, 'moi']);
        \Illuminate\Support\Facades\Route::put('me/pin', [$pins, 'definirParent'])->middleware('throttle:5,1');

        \Illuminate\Support\Facades\Route::get('device/profiles', [$acces, 'profils']);
        \Illuminate\Support\Facades\Route::post('device/unlock', [$acces, 'deverrouiller'])->middleware('throttle:20,1');

        \Illuminate\Support\Facades\Route::get('devices', [$appareils, 'index']);
        \Illuminate\Support\Facades\Route::post('devices/{device}/revoke', [$appareils, 'retirer']);
        \Illuminate\Support\Facades\Route::post('devices/{device}/restore', [$appareils, 'reautoriser']);

        \Illuminate\Support\Facades\Route::get('children/{profile}/controls', [$controles, 'show']);
        \Illuminate\Support\Facades\Route::put('children/{profile}/controls', [$controles, 'update']);
        \Illuminate\Support\Facades\Route::post('auth/logout', [$auth, 'deconnecter']);

        \Illuminate\Support\Facades\Route::get('children', [$enfants, 'index']);
        \Illuminate\Support\Facades\Route::post('children', [$enfants, 'store']);
        \Illuminate\Support\Facades\Route::get('children/{profile}', [$enfants, 'show']);
        \Illuminate\Support\Facades\Route::post('children/{profile}/guardians', [$tutelles, 'inviter']);

        \Illuminate\Support\Facades\Route::get('invitations', [$tutelles, 'recues']);
        \Illuminate\Support\Facades\Route::post('invitations/{guardianship}/accept', [$tutelles, 'accepter']);
        \Illuminate\Support\Facades\Route::post('invitations/{guardianship}/decline', [$tutelles, 'refuser']);
    });
});