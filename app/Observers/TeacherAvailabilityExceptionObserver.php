<?php

namespace App\Observers;

use App\Models\TeacherAvailabilityException;
use App\Services\AcademicPlanning\AutoSubstitutionGeneratorService;

class TeacherAvailabilityExceptionObserver
{
    public function created(TeacherAvailabilityException $exception): void
    {
        app(AutoSubstitutionGeneratorService::class)
            ->generateFromAvailabilityException($exception);
    }

    public function updated(TeacherAvailabilityException $exception): void
    {
        app(AutoSubstitutionGeneratorService::class)
            ->regenerate($exception);
    }

    public function deleted(TeacherAvailabilityException $exception): void
    {
        app(AutoSubstitutionGeneratorService::class)
            ->cancel($exception);
    }
}