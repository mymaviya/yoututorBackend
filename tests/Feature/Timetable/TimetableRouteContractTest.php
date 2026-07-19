<?php

namespace Tests\Feature\Timetable;

use Illuminate\Routing\Route;
use Tests\TestCase;

class TimetableRouteContractTest extends TestCase
{
    /**
     * @dataProvider timetableRouteProvider
     */
    public function test_timetable_api_route_contracts_are_registered(
        string $name,
        string $uri,
        array $methods
    ): void {
        $route = app('router')->getRoutes()->getByName($name);

        $this->assertInstanceOf(Route::class, $route, "Route [{$name}] is not registered.");
        $this->assertSame($uri, $route->uri(), "Route [{$name}] has an unexpected URI.");

        foreach ($methods as $method) {
            $this->assertContains(
                strtoupper($method),
                $route->methods(),
                "Route [{$name}] does not accept [{$method}]."
            );
        }

        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    public static function timetableRouteProvider(): array
    {
        return [
            'single preview' => [
                'timetable.generator.preview',
                'api/timetable-generator/preview',
                ['POST'],
            ],
            'single generation' => [
                'timetable.generator.generate',
                'api/timetable-generator/generate',
                ['POST'],
            ],
            'batch preview' => [
                'timetable.batch.generator.preview',
                'api/timetable-batch-generator/preview',
                ['POST'],
            ],
            'batch generation' => [
                'timetable.batch.generator.generate',
                'api/timetable-batch-generator/generate',
                ['POST'],
            ],
            'generation history' => [
                'timetable.generation.runs.index',
                'api/timetable-generation-runs',
                ['GET'],
            ],
            'generation conflicts' => [
                'timetable.generation.runs.conflicts',
                'api/timetable-generation-runs/{timetableGenerationRun}/conflicts',
                ['GET'],
            ],
            'generation retry' => [
                'timetable.generation.runs.retry',
                'api/timetable-generation-runs/{timetableGenerationRun}/retry',
                ['POST'],
            ],
            'generation cancellation' => [
                'timetable.generation.runs.cancel',
                'api/timetable-generation-runs/{timetableGenerationRun}/cancel',
                ['POST'],
            ],
            'editor grid' => [
                'weekly.timetables.editor.grid',
                'api/weekly-timetables/{weeklyTimetable}/grid',
                ['GET'],
            ],
            'editor entry create' => [
                'weekly.timetables.editor.entries.store',
                'api/weekly-timetables/{weeklyTimetable}/entries',
                ['POST'],
            ],
            'editor entry update' => [
                'weekly.timetables.editor.entries.update',
                'api/weekly-timetables/{weeklyTimetable}/entries/{timetableEntry}',
                ['PUT'],
            ],
            'editor entry delete' => [
                'weekly.timetables.editor.entries.destroy',
                'api/weekly-timetables/{weeklyTimetable}/entries/{timetableEntry}',
                ['DELETE'],
            ],
            'editor full grid save' => [
                'weekly.timetables.editor.grid.replace',
                'api/weekly-timetables/{weeklyTimetable}/grid',
                ['PUT'],
            ],
            'publish timetable' => [
                'weekly.timetables.publish',
                'api/weekly-timetables/{weeklyTimetable}/publish',
                ['POST'],
            ],
            'archive timetable' => [
                'weekly.timetables.archive',
                'api/weekly-timetables/{weeklyTimetable}/archive',
                ['POST'],
            ],
            'restore timetable' => [
                'weekly.timetables.restore',
                'api/weekly-timetables/{weeklyTimetable}/restore',
                ['POST'],
            ],
            'create timetable version' => [
                'weekly.timetables.versions.store',
                'api/weekly-timetables/{weeklyTimetable}/versions',
                ['POST'],
            ],
            'class report' => [
                'timetable.reports.classes.show',
                'api/timetable-reports/classes/{weeklyTimetable}',
                ['GET'],
            ],
            'class Excel report' => [
                'timetable.reports.classes.excel',
                'api/timetable-reports/classes/{weeklyTimetable}/excel',
                ['GET'],
            ],
            'class PDF report' => [
                'timetable.reports.classes.pdf',
                'api/timetable-reports/classes/{weeklyTimetable}/pdf',
                ['GET'],
            ],
            'teacher report' => [
                'timetable.reports.teachers.show',
                'api/timetable-reports/teachers/{teacher}',
                ['GET'],
            ],
            'room report' => [
                'timetable.reports.rooms.show',
                'api/timetable-reports/rooms/{timetableRoom}',
                ['GET'],
            ],
            'workload report' => [
                'timetable.reports.workload',
                'api/timetable-reports/workload',
                ['GET'],
            ],
            'live conflict report' => [
                'timetable.reports.conflicts',
                'api/timetable-reports/conflicts',
                ['GET'],
            ],
        ];
    }
}
