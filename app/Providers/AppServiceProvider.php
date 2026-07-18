<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\TeacherAvailabilityException;
use App\Observers\TeacherAvailabilityExceptionObserver;
use App\Models\TimetableEntry;
use App\Observers\TimetableEntryObserver;

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
    }
}
