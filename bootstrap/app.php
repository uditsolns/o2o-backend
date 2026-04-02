<?php

use App\Http\Middleware\BindTenantScope;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('api', BindTenantScope::class);
        $middleware->alias(['onboarded' => EnsureOnboarded::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (Throwable $e, $request) {
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'message' => "{$model} not found.",
                ], 404);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }

            if ($e instanceof HttpException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ], $e->getStatusCode());
            }

            // Unhandled — mask in production
            $debug = config('app.debug');

            return response()->json([
                'message' => $debug ? $e->getMessage() : 'An unexpected error occurred.',
                ...($debug ? ['trace' => collect($e->getTrace())->take(5)->all()] : []),
            ], 500);
        });
    })
    ->create();
