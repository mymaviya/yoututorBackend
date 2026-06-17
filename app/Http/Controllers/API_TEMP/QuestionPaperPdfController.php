<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuestionPaper;
use App\Models\PaperBlueprintSection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class QuestionPaperPdfController extends Controller
{
    public function download($id)
    {
        $paper = QuestionPaper::with([
            'grade',
            'subject',
            'questions' => function ($q) {
                $q->with([
                    'question.options',
                    'question.matchPairs',
                    'question.type'
                ])
                    ->orderBy('section')
                    ->orderBy('sort_order');
            }
        ])->findOrFail($id);

        foreach ($paper->questions as $paperQuestion) {
            $questionTypeId = $paperQuestion->question?->question_type_master_id;

            $blueprintRow = PaperBlueprintSection::where('section_name', $paperQuestion->section)
                ->where('question_type_master_id', $questionTypeId)
                ->where('marks_per_question', '>', 0)
                ->first();

            if ($blueprintRow) {
                $paperQuestion->marks = $blueprintRow->marks_per_question;
            }
        }

        $paper->total_marks = $paper->questions->sum('marks');

        $pdf = Pdf::loadView('pdf.question-paper', [
            'paper' => $paper
        ])->setPaper('a4', 'portrait');

        $fileName =
            $paper->grade->name . '-' .
            $paper->subject->name . '-' .
            $paper->title;

        return $pdf->download(
            str_replace(' ', '-', strtolower($fileName)) . '.pdf'
        );
    }
}
