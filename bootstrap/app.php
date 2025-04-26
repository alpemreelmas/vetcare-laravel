<?php

use App\Core\Helpers\ResponseHelper;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::error(
                    $e->getMessage(),
                    422,
                    $e->errors()
                );
            }
        });

        // Model Not Found (404)
        $exceptions->renderable(function (ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::error(
                    'Resource not found',
                    404
                );
            }
        });

        // Route Not Found (404)
        $exceptions->renderable(function (NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::error(
                    'Resource not found',
                    404
                );
            }
        });

        // Authentication Errors (401)
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::error(
                    'Unauthorized',
                    401
                );
            }
        });

        // Generic server errors (500) - optional fallback
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ResponseHelper::error(
                    $e->getMessage() ?: 'Something went wrong!',
                    method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500
                );
            }
        });
    })->create();
