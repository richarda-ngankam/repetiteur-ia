<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // En production : aucune trace, un message épuré pour les vraies erreurs serveur.
        // Les erreurs « normales » (404, 403, 401, 422, 429...) gardent leur traitement habituel.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (config('app.debug') || ! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $gerees = [
                HttpExceptionInterface::class,
                ValidationException::class,
                AuthenticationException::class,
                AuthorizationException::class,
                ModelNotFoundException::class,
                RecordsNotFoundException::class,
                TokenMismatchException::class,
            ];

            foreach ($gerees as $classe) {
                if ($e instanceof $classe) {
                    return null;
                }
            }

            return response()->json(['message' => 'Erreur interne du serveur.'], 500);
        });
    })->create();