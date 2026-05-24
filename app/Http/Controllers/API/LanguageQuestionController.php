<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LanguageQuestion;

class LanguageQuestionController extends Controller
{
    public function group(Request $request)
    {
        $ids = explode(',', $request->ids);

        return LanguageQuestion::with([
            'grade',
            'subject',
            'lesson',
            'questionType',
        ])
            ->whereIn('id', $ids)
            ->get();
    }
}
