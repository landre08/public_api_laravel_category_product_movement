<?php

use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\MaintenanceModeMiddleware;
use App\Services\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function() {
            // Route::middleware('api')
            //     ->prefix('api/v1')
            //     ->group(base_path('routes/api_v1.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->api(prepend: [
            CorrelationIdMiddleware::class
        ]);

        $middleware->api(prepend: [
            MaintenanceModeMiddleware::class
        ]);

        $middleware->api(prepend: [
            ThrottleRequests::class.':api'
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function(ThrottleRequestsException $e, $request) {
            return ApiResponse::error('Too many requests', 429);
        });

        $exceptions->render(function(ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(code: 422, errors: $e->errors());
            }
        });

        $exceptions->render(function(\Exception $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                        message: 'An unexpected error occurred.',
                        code: 500,
                        errors: [$e->getMessage()]
                    );
            }
        });
    })->create();
