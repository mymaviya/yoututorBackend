<?php

namespace App\Services;

use App\Models\QuestionPaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class QuestionPaperExportService
{
    public function downloadQuestionPaperPdf(QuestionPaper $paper, array $school)
    {
        $pdf = Pdf::loadView('pdf.question-paper', [
            'paper' => $paper,
            'school' => $school,
            'schoolName' => $school['name'] ?? 'Siddharth Public School',
            'schoolAddress' => $school['address'] ?? 'School Address',
            'schoolPhone' => $school['phone'] ?? null,
            'schoolEmail' => $school['email'] ?? null,
            'schoolLogo' => $school['logo_path'] ?? null,
            'academicSession' => $school['academic_session'] ?? null,
            'exportMode' => 'pdf',
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $this->addPdfFooter($dompdf, $school['name'] ?? 'Siddharth Public School');

        $fileName = Str::slug(
            ($paper->grade?->name ?? 'paper') . '-' .
            ($paper->subject?->name ?? 'subject') . '-' .
            ($paper->examName?->name ?? $paper->exam_type ?? 'question-paper')
        ) . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function downloadAnswerKeyPdf(QuestionPaper $paper, array $school)
    {
        $pdf = Pdf::loadView('pdf.answer-key', [
            'paper' => $paper,
            'school' => $school,
            'schoolName' => $school['name'] ?? 'Siddharth Public School',
            'schoolAddress' => $school['address'] ?? 'School Address',
            'schoolPhone' => $school['phone'] ?? null,
            'schoolEmail' => $school['email'] ?? null,
            'schoolLogo' => $school['logo_path'] ?? null,
            'academicSession' => $school['academic_session'] ?? null,
            'exportMode' => 'pdf',
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $this->addPdfFooter($dompdf, $school['name'] ?? 'Siddharth Public School');

        $fileName = Str::slug(
            ($paper->grade?->name ?? 'paper') . '-' .
            ($paper->subject?->name ?? 'subject') . '-' .
            ($paper->examName?->name ?? $paper->exam_type ?? 'question-paper') .
            '-answer-key'
        ) . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function addPdfFooter($dompdf, string $schoolName): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('Times-Roman', 'normal');

        $width = $canvas->get_width();
        $height = $canvas->get_height();

        $yLine = $height - 42;
        $yText = $height - 32;

        $canvas->line(36, $yLine, $width - 36, $yLine, [0, 0, 0], 0.5);

        $canvas->page_text(
            36,
            $yText,
            $schoolName,
            $font,
            9,
            [0, 0, 0]
        );

        $canvas->page_text(
            $width - 115,
            $yText,
            'Page {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );
    }
}
