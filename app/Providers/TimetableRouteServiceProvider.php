<?php

namespace App\Providers;

use App\Http\Middleware\CheckActiveSubscription;
use App\Http\Middleware\CheckRouteFeature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TimetableRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware([
            'api',
            'auth:sanctum',
            CheckActiveSubscription::class,
            CheckRouteFeature::class,
            'role:admin,superadmin,super_admin',
        ])
            ->prefix('api')
            ->group(function () {
                require base_path('routes/timetable_templates.php');
                require base_path('routes/timetable_rules.php');
            });
    }
}
