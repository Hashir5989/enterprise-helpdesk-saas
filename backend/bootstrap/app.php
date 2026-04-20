<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return \App\Support\ApiResponse::error(
                    message: 'Validation failed',
                    statusCode: 422,
                    errors: $e->errors()
                );
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return \App\Support\ApiResponse::error(
                    message: 'Unauthenticated',
                    statusCode: 401
                );
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return \App\Support\ApiResponse::error(
                    message: $e->getMessage() ?: 'Forbidden',
                    statusCode: 403
                );
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return \App\Support\ApiResponse::error(
                    message: 'Resource not found',
                    statusCode: 404
                );
            }
        });
    })->create();
