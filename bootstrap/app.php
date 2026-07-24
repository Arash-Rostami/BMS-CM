<?php

use App\Http\Middleware\DatabaseConnectionMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/cache.blade.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(DatabaseConnectionMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])->create();
