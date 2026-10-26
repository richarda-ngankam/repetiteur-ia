<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
\Illuminate\Support\Facades\Route::prefix('v1/auth')->group(function () {
    \Illuminate\Support\Facades\Route::post('code', [\App\Http\Controllers\Api\ParentAuthController::class, 'demanderCode'])->middleware('throttle:5,1');
    \Illuminate\Support\Facades\Route::post('register', [\App\Http\Controllers\Api\ParentAuthController::class, 'inscrire'])->middleware('throttle:10,1');
});
