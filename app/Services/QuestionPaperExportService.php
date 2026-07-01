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
            'schoolName' => $school['name'] ?? 'Siddharth Public School',
            'schoolAddress' => $school['address'] ?? 'School Address',
            'schoolPhone' => $school['phone'] ?? null,
            'schoolEmail' => $school['email'] ?? null,
            'schoolLogo' => $school['logo_path'] ?? null,
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $font = $fontMetrics->getFont('Times-Roman', 'normal');

        $width = $canvas->get_width();
        $height = $canvas->get_height();

        $schoolName = $school['name'] ?? 'Siddharth Public School';

        $canvas->page_text(
            36,
            $height - 30,
            $schoolName,
            $font,
            9,
            [0, 0, 0]
        );

        $canvas->page_text(
            $width - 110,
            $height - 30,
            'Page {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );

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
}