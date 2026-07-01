<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34mm 13mm 20mm 13mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", serif; font-size: 14px; line-height: 1.18; color: #000; margin: 0; padding: 0; }
        .fixed-header { position: fixed; top: -27mm; left: 0; right: 0; height: 24mm; border-bottom: 1px solid #000; padding-bottom: 4px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .logo-cell { width: 70px; vertical-align: middle; text-align: left; }
        .school-logo { max-width: 58px; max-height: 58px; }
        .school-name { text-align: center; font-size: 18px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .school-address { text-align: center; font-size: 11.5px; line-height: 1.15; }
        .fixed-footer { position: fixed; left: 0; right: 0; bottom: -14mm; height: 10mm; border-top: 1px solid #000; padding-top: 3px; font-size: 11px; }
        .footer-school { float: left; width: 65%; font-weight: bold; }
        .footer-page { float: right; width: 30%; text-align: right; }
        .footer-page:after { content: "Page " counter(page) " / " counter(pages); }
        .paper-header { text-align: center; margin-bottom: 6px; }
        .paper-header h2 { margin: 0 0 5px; font-size: 21px; font-weight: bold; text-transform: uppercase; }
        .paper-meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .paper-meta td { font-size: 13px; padding: 2px 0; }
        .paper-meta td:last-child { text-align: right; }
        hr { border: none; border-top: 1px solid #000; margin: 6px 0; }
        .section-heading { margin: 10px 0 4px; padding-bottom: 3px; font-weight: bold; font-size: 16px; text-align: center; border-bottom: 1px solid #000; }
        .group-heading { margin: 6px 0 4px; padding-bottom: 2px; font-size: 14px; font-weight: bold; border-bottom: 1px dashed #888; }
        .answer-row { margin-bottom: 7px; page-break-inside: avoid; }
        .answer-q { font-weight: bold; }
        .answer-label { font-weight: bold; }
        .match-answer { margin-left: 34px; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="fixed-header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if (!empty($school['logo_path']))
                        <img src="{{ $school['logo_path'] }}" class="school-logo">
                    @endif
                </td>
                <td>
                    <div class="school-name">{{ $school['name'] ?? 'School Name' }}</div>
                    <div class="school-address">
                        {{ $school['address'] ?? '' }}
                        @if (!empty($school['phone'])) | Phone: {{ $school['phone'] }} @endif
                        @if (!empty($school['email'])) | Email: {{ $school['email'] }} @endif
                    </div>
                </td>
                <td class="logo-cell"></td>
            </tr>
        </table>
    </div>

    <div class="fixed-footer">
        <div class="footer-school">{{ $school['name'] ?? 'School Name' }}</div>
        <div class="footer-page"></div>
    </div>

    <div class="paper-header">
        <h2>{{ $paper->title ?? 'Question Paper' }} - Answer Key</h2>
        <table class="paper-meta">
            <tr>
                <td><strong>Subject:</strong> {{ $paper->subject->name ?? '-' }}</td>
                <td><strong>Duration:</strong> {{ $paper->duration_minutes ?? $paper->duration ?? 0 }} Minutes</td>
            </tr>
            <tr>
                <td><strong>Class:</strong> {{ $paper->grade->name ?? '-' }}</td>
                <td><strong>Max Marks:</strong> {{ $paper->total_marks ?? 0 }}</td>
            </tr>
        </table>
    </div>

    <hr>

    @php
        $questionNo = 1;
        if (! function_exists('answerTypeLabel')) {
            function answerTypeLabel($type) {
                return [
                    'mcq' => 'Multiple Choice Questions',
                    'multiple_mcq' => 'Multiple Correct Questions',
                    'true_false' => 'True / False',
                    'fill_blank' => 'Fill in the Blanks',
                    'short' => 'Short Answer Questions',
                    'short_answer' => 'Short Answer Questions',
                    'long' => 'Long Answer Questions',
                    'long_answer' => 'Long Answer Questions',
                    'match_column' => 'Match the Columns',
                    'assertion_reason' => 'Assertion & Reason',
                    'numerical' => 'Numerical Problems',
                ][$type] ?? ucwords(str_replace('_', ' ', (string) $type));
            }
        }
        if (! function_exists('answerPairLeft')) {
            function answerPairLeft($pair) { return $pair->left_value ?? $pair->left_text ?? $pair->left ?? ''; }
        }
        if (! function_exists('answerPairRight')) {
            function answerPairRight($pair) { return $pair->right_value ?? $pair->right_text ?? $pair->right ?? ''; }
        }
        if (! function_exists('answerTextForQuestion')) {
            function answerTextForQuestion($q) {
                if ($q->options && $q->options->count()) {
                    $correct = [];
                    foreach ($q->options->values() as $i => $option) {
                        if ($option->is_correct) {
                            $correct[] = chr(65 + $i) . '. ' . strip_tags($option->option_text ?? '');
                        }
                    }
                    if (! empty($correct)) return implode('; ', $correct);
                }
                return strip_tags($q->answer ?? '-');
            }
        }
    @endphp

    @foreach ($paper->questions->groupBy('section') as $sectionName => $paperQuestions)
        <div class="section-heading">{{ $sectionName ?: 'Section A' }}</div>

        @foreach ($paperQuestions->groupBy(fn ($pq) => $pq->question?->type?->slug ?? 'unknown') as $type => $typeQuestions)
            <div class="group-heading">{{ answerTypeLabel($type) }}</div>

            @foreach ($typeQuestions as $paperQuestion)
                @php $q = $paperQuestion->question; @endphp
                @if ($q)
                    <div class="answer-row">
                        <span class="answer-q">Q{{ $questionNo++ }}.</span>
                        <span class="answer-label">Answer:</span>
                        {{ answerTextForQuestion($q) }}

                        @if ($q->type?->slug === 'match_column' && $q->matchPairs->count())
                            <div class="match-answer">
                                @foreach ($q->matchPairs as $pair)
                                    <div>{{ answerPairLeft($pair) }} - {{ answerPairRight($pair) }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if ($q->explanation)
                            <div><span class="answer-label">Explanation:</span> {!! $q->explanation !!}</div>
                        @endif
                    </div>
                @endif
            @endforeach
        @endforeach
    @endforeach
</body>
</html>
