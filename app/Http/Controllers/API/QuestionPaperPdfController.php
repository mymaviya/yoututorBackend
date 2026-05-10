<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QuestionPaper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuestionPaperPdfController extends Controller
{
    public function download($id)
    {
        $paper = QuestionPaper::with([
            'grade',
            'subject',
            'questions.question.options'
        ])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.question-paper', [
            'paper' => $paper
        ])->setPaper('a4', 'portrait');

        $fileName = $paper->grade->name.'-'.$paper->subject->name.'-'.$paper->title;

        return $pdf->download(
            str_replace(' ', '-', strtolower($fileName)) . '.pdf'
        );
    }
}
