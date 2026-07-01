<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprintSection;
use App\Models\QuestionPaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Services\QuestionPaperExportService;

class QuestionPaperPdfController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function ensurePaperAccess(QuestionPaper $paper)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ((int) $paper->subscription_id !== (int) auth()->user()?->subscription_id) {
            return response()->json([
                'message' => 'You are not allowed to access this question paper.',
            ], 403);
        }

        return null;
    }

    private function loadPaper($id): QuestionPaper
    {
        $paper = QuestionPaper::with([
            'grade',
            'stream',
            'subject',
            'examName',
            'blueprint',
            'subscription',
            'questions' => function ($q) {
                $q->with([
                    'question.options',
                    'question.matchPairs',
                    'question.lesson',
                    'question.type',
                ])->orderBy('section')->orderBy('sort_order');
            },
        ])->findOrFail($id);

        foreach ($paper->questions as $paperQuestion) {
            $questionTypeId = $paperQuestion->question?->question_type_master_id;

            $blueprintRow = PaperBlueprintSection::query()
                ->when($paper->paper_blueprint_id, fn($query) => $query->where('paper_blueprint_id', $paper->paper_blueprint_id))
                ->where('section_name', $paperQuestion->section)
                ->where('question_type_master_id', $questionTypeId)
                ->where('marks_per_question', '>', 0)
                ->first();

            if ($blueprintRow) {
                $paperQuestion->marks = $blueprintRow->marks_per_question;
            }
        }

        $paper->total_marks = $paper->questions->sum('marks');

        return $paper;
    }

    private function schoolHeaderDetails(QuestionPaper $paper): array
    {
        $subscription = $paper->subscription ?? auth()->user()?->subscription;

        $logo = $subscription->logo
            ?? $subscription->logo_path
            ?? $subscription->school_logo
            ?? config('app.school_logo')
            ?? env('SCHOOL_LOGO');

        $logoPath = null;

        if ($logo) {
            $logo = ltrim((string) $logo, '/');

            $possiblePaths = [
                public_path($logo),
                public_path('storage/' . $logo),
                storage_path('app/public/' . $logo),
            ];

            foreach ($possiblePaths as $path) {
                if ($path && file_exists($path)) {
                    $logoPath = $path;
                    break;
                }
            }
        }

        return [
            'name' => $subscription->school_name
                ?? $subscription->name
                ?? config('app.school_name')
                ?? env('SCHOOL_NAME', 'SCHOOL NAME'),
            'address' => $subscription->address
                ?? $subscription->school_address
                ?? config('app.school_address')
                ?? env('SCHOOL_ADDRESS', 'School Address'),
            'phone' => $subscription->phone
                ?? $subscription->contact
                ?? $subscription->mobile
                ?? config('app.school_phone')
                ?? env('SCHOOL_PHONE'),
            'email' => $subscription->email
                ?? config('app.school_email')
                ?? env('SCHOOL_EMAIL'),
            'logo_path' => $logoPath,
        ];
    }

    public function download($id, QuestionPaperExportService $exportService)
    {
        return $this->exportPdf($id, $exportService);
    }

    public function exportPdf($id, QuestionPaperExportService $exportService)
    {
        $paper = $this->loadPaper($id);

        if ($response = $this->ensurePaperAccess($paper)) {
            return $response;
        }

        $school = $this->schoolHeaderDetails($paper);

        return $exportService->downloadQuestionPaperPdf($paper, $school);
    }

    public function answerKeyPdf($id)
    {
        $paper = $this->loadPaper($id);

        if ($response = $this->ensurePaperAccess($paper)) {
            return $response;
        }

        $pdf = Pdf::loadView('pdf.answer-key', [
            'paper' => $paper,
            'school' => $this->schoolHeaderDetails($paper),
            'exportMode' => 'pdf',
        ])->setPaper('a4', 'portrait');

        $fileName = Str::slug(($paper->grade?->name ?? 'paper') . '-' . ($paper->subject?->name ?? 'subject') . '-' . ($paper->title ?? 'question-paper') . '-answer-key') . '.pdf';

        return $pdf->download($fileName);
    }
}
