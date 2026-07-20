<?php

namespace Tests\Unit\Timetable;

use Tests\TestCase;

class SubjectPeriodAllocationValidationContractTest extends TestCase
{
    public function test_shared_academic_reference_tables_are_not_tenant_scoped(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Controllers/Api/Admin/SubjectPeriodAllocationController.php')
        );

        $this->assertIsString($source);

        $this->assertStringContainsString(
            "'grade_id' => ['required', 'integer', \$this->sharedExists('grades')]",
            $source
        );
        $this->assertStringContainsString(
            "'section_id' => ['nullable', 'integer', \$this->sharedExists('sections')]",
            $source
        );
        $this->assertStringContainsString(
            "'stream_id' => ['nullable', 'integer', \$this->sharedExists('streams')]",
            $source
        );

        $this->assertStringNotContainsString("ownedExists('grades'", $source);
        $this->assertStringNotContainsString("ownedExists('sections'", $source);
        $this->assertStringNotContainsString("ownedExists('streams'", $source);
    }

    public function test_tenant_owned_tables_remain_subscription_scoped(): void
    {
        $source = file_get_contents(
            base_path('app/Http/Controllers/Api/Admin/SubjectPeriodAllocationController.php')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString("ownedExists('academic_years'", $source);
        $this->assertStringContainsString("ownedExists('subjects'", $source);
        $this->assertStringContainsString("ownedExists('users'", $source);
    }
}
