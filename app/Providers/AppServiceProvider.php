<?php

namespace App\Providers;

use App\Models\TeacherAvailabilityException;
use App\Models\TimetableEntry;
use App\Observers\TeacherAvailabilityExceptionObserver;
use App\Observers\TimetableEntryObserver;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TeacherAvailabilityException::observe(TeacherAvailabilityExceptionObserver::class);
        TimetableEntry::observe(TimetableEntryObserver::class);

        /*
         * Weekly teacher availability and date-specific availability exceptions
         * previously shared route names such as teacher.availability.store.
         * Laravel requires every named route to be unique before route caching.
         * Normalize only the exception routes after all routes are registered;
         * their URLs and controller actions remain unchanged.
         */
        $this->app->booted(function (): void {
            $routeNames = [
                'GET|api/teacher-availability-exceptions/dashboard' => 'teacher.availability.exceptions.dashboard',
                'GET|api/teacher-availability-exceptions' => 'teacher.availability.exceptions.index',
                'POST|api/teacher-availability-exceptions' => 'teacher.availability.exceptions.store',
                'PATCH|api/teacher-availability-exceptions/{teacherAvailabilityException}/move' => 'teacher.availability.exceptions.move',
                'PUT|api/teacher-availability-exceptions/{teacherAvailabilityException}' => 'teacher.availability.exceptions.update',
                'DELETE|api/teacher-availability-exceptions/{teacherAvailabilityException}' => 'teacher.availability.exceptions.destroy',
            ];

            foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
                if (!$route instanceof Route) {
                    continue;
                }

                $method = collect($route->methods())
                    ->first(fn (string $method): bool => $method !== 'HEAD');

                if (!$method) {
                    continue;
                }

                $key = $method . '|' . $route->uri();
                $name = $routeNames[$key] ?? null;

                if (!$name) {
                    continue;
                }

                $action = $route->getAction();
                $action['as'] = $name;
                $route->setAction($action);
            }
        });
    }
}
