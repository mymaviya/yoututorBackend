<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BlueprintImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new QuestionTypeMastersSheet(),
            new QuestionTypeTemplatesSheet(),
            new QuestionTypeAssignmentsSheet(),
            new PaperBlueprintsSheet(),
            new BlueprintSectionsSheet(),
        ];
    }
}