<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\TimetableReportExport;
use App\Http\Controllers\Controller;
use App\Models\TimetableRoom;
use App\Models\User;
use App\Models\WeeklyTimetable;
use App\Services\AcademicPlanning\TimetableReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TimetableReportController extends Controller
{
    public function __construct(
        protected TimetableReportService $service
    ) {}

    public function classReport(
        Request $request,
        WeeklyTimetable $weeklyTimetable
    ): JsonResponse {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'data' => $this->service->classReport($weeklyTimetable),
        ]);
    }

    public function classExcel(
        Request $request,
        WeeklyTimetable $weeklyTimetable
    ): BinaryFileResponse {
        $this->ensureOwned($request, $weeklyTimetable);
        $report = $this->service->classReport($weeklyTimetable);

        return Excel::download(
            new TimetableReportExport($report['rows']),
            $this->fileName($report['title'], 'xlsx')
        );
    }

    public function classPdf(
        Request $request,
        WeeklyTimetable $weeklyTimetable
    ): Response {
        $this->ensureOwned($request, $weeklyTimetable);
        $report = $this->service->classReport($weeklyTimetable);

        return Pdf::loadView('reports.timetable', compact('report'))
            ->setPaper('a4', 'landscape')
            ->download($this->fileName($report['title'], 'pdf'));
    }

    public function teacher(Request $request, User $teacher): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $teacher->subscription_id === $subscriptionId, 404);

        $academicYearId = $this->academicYearId($request, $subscriptionId);

        return response()->json([
            'success' => true,
            'data' => $this->service->teacherReport(
                $subscriptionId,
                (int) $teacher->id,
                $academicYearId
            ),
        ]);
    }

    public function teacherExcel(Request $request, User $teacher): BinaryFileResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $teacher->subscription_id === $subscriptionId, 404);
        $report = $this->service->teacherReport(
            $subscriptionId,
            (int) $teacher->id,
            $this->academicYearId($request, $subscriptionId)
        );

        return Excel::download(
            new TimetableReportExport($report['rows']),
            $this->fileName($report['title'], 'xlsx')
        );
    }

    public function teacherPdf(Request $request, User $teacher): Response
    {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $teacher->subscription_id === $subscriptionId, 404);
        $report = $this->service->teacherReport(
            $subscriptionId,
            (int) $teacher->id,
            $this->academicYearId($request, $subscriptionId)
        );

        return Pdf::loadView('reports.timetable', compact('report'))
            ->setPaper('a4', 'landscape')
            ->download($this->fileName($report['title'], 'pdf'));
    }

    public function room(Request $request, TimetableRoom $timetableRoom): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $timetableRoom->subscription_id === $subscriptionId, 404);

        return response()->json([
            'success' => true,
            'data' => $this->service->roomReport(
                $subscriptionId,
                (int) $timetableRoom->id,
                $this->academicYearId($request, $subscriptionId)
            ),
        ]);
    }

    public function roomExcel(
        Request $request,
        TimetableRoom $timetableRoom
    ): BinaryFileResponse {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $timetableRoom->subscription_id === $subscriptionId, 404);
        $report = $this->service->roomReport(
            $subscriptionId,
            (int) $timetableRoom->id,
            $this->academicYearId($request, $subscriptionId)
        );

        return Excel::download(
            new TimetableReportExport($report['rows']),
            $this->fileName($report['title'], 'xlsx')
        );
    }

    public function roomPdf(
        Request $request,
        TimetableRoom $timetableRoom
    ): Response {
        $subscriptionId = $this->subscriptionId($request);
        abort_unless((int) $timetableRoom->subscription_id === $subscriptionId, 404);
        $report = $this->service->roomReport(
            $subscriptionId,
            (int) $timetableRoom->id,
            $this->academicYearId($request, $subscriptionId)
        );

        return Pdf::loadView('reports.timetable', compact('report'))
            ->setPaper('a4', 'landscape')
            ->download($this->fileName($report['title'], 'pdf'));
    }

    public function workload(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->service->workload(
                $subscriptionId,
                $this->academicYearId($request, $subscriptionId)
            ),
        ]);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->service->conflictReport(
                $subscriptionId,
                $this->academicYearId($request, $subscriptionId)
            ),
        ]);
    }

    private function academicYearId(Request $request, int $subscriptionId): ?int
    {
        $data = $request->validate([
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
        ]);

        return isset($data['academic_year_id'])
            ? (int) $data['academic_year_id']
            : null;
    }

    private function ensureOwned(Request $request, WeeklyTimetable $timetable): void
    {
        abort_unless(
            (int) $timetable->subscription_id === $this->subscriptionId($request),
            404
        );
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }

    private function fileName(string $title, string $extension): string
    {
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($title)) ?: 'timetable-report';

        return strtolower(trim($base, '-')) . '.' . $extension;
    }
}
