<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add CORS middleware to all API routes
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Register premium access middleware alias
        $middleware->alias([
            'premium' => \App\Http\Middleware\CheckPremium::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return a JSON 401 for unauthenticated API requests
        // instead of trying to redirect to the non-existent 'login' route.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please provide a valid Bearer token.',
                ], 401);
            }
        });
    })
    ->create();
