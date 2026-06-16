<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            //
        ]);
    })
    ->withMiddleware(function ($middleware) {

        $middleware->alias([
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'active.subscription' => \App\Http\Middleware\CheckActiveSubscription::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'plan.feature' => \App\Http\Middleware\CheckPlanFeature::class,
            'route.feature' => \App\Http\Middleware\CheckRouteFeature::class,
        ]);
    })
    ->withCommands([
        app_path('Console/Commands'),
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
