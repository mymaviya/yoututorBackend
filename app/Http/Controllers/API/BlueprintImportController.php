<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\PaperBlueprintImport;
use App\Imports\QuestionTypeTemplateImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Exports\BlueprintImportTemplateExport;
use Maatwebsite\Excel\Facades\Excel;

class BlueprintImportController extends Controller
{
    public function importQuestionTypeTemplate(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $path = $request->file('file')->store('imports');

        try {
            $fullPath = Storage::path($path);

            $import = new QuestionTypeTemplateImport();

            $result = $import->import($fullPath);

            return response()->json([
                'message' => 'Question type template import completed.',
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
        } finally {
            Storage::delete($path);
        }
    }

    public function importPaperBlueprint(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $path = $request->file('file')->store('imports');

        try {
            $fullPath = Storage::path($path);

            $import = new PaperBlueprintImport();

            $result = $import->import($fullPath);

            return response()->json([
                'message' => 'Paper blueprint import completed.',
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ]);
        } finally {
            Storage::delete($path);
        }
    }

    public function importAll(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $path = $request->file('file')->store('imports');

        try {
            $fullPath = Storage::path($path);

            $questionTypeImport = new QuestionTypeTemplateImport();
            $questionTypeResult = $questionTypeImport->import($fullPath);

            $blueprintImport = new PaperBlueprintImport();
            $blueprintResult = $blueprintImport->import($fullPath);

            return response()->json([
                'message' => 'Blueprint Excel import completed.',
                'question_type_import' => $questionTypeResult,
                'paper_blueprint_import' => $blueprintResult,
            ]);
        } finally {
            Storage::delete($path);
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new BlueprintImportTemplateExport(),
            'blueprint_import_template.xlsx'
        );
    }
}
