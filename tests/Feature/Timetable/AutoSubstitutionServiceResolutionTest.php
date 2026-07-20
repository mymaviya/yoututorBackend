<?php

namespace Tests\Feature\Timetable;

use App\Observers\TeacherAvailabilityExceptionObserver;
use App\Services\AcademicPlanning\AutoSubstitutionGeneratorService;
use Tests\TestCase;

class AutoSubstitutionServiceResolutionTest extends TestCase
{
    public function test_automatic_substitution_service_is_resolvable(): void
    {
        $service = app(AutoSubstitutionGeneratorService::class);

        $this->assertInstanceOf(AutoSubstitutionGeneratorService::class, $service);
    }

    public function test_teacher_availability_exception_observer_is_resolvable(): void
    {
        $observer = app(TeacherAvailabilityExceptionObserver::class);

        $this->assertInstanceOf(TeacherAvailabilityExceptionObserver::class, $observer);
    }
}
