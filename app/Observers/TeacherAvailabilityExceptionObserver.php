<?php

namespace App\Observers;

use App\Models\TeacherAvailabilityException;
use App\Services\AcademicPlanning\AutoSubstitutionGeneratorService;

class TeacherAvailabilityExceptionObserver
{
    public function __construct(
        protected AutoSubstitutionGeneratorService $generator
    ) {}

    public function created(TeacherAvailabilityException $exception): void
    {
        $this->generator->generateFromAvailabilityException($exception);
    }

    public function updated(TeacherAvailabilityException $exception): void
    {
        $this->generator->regenerate($exception);
    }

    public function deleted(TeacherAvailabilityException $exception): void
    {
        $this->generator->cancel($exception);
    }
}
