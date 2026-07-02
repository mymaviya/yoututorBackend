<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 10mm 13mm 22mm 13mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Times New Roman", serif;
            font-size: 14px;
            line-height: 1.18;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .first-page-header {
            border-bottom: 1px solid #000;
            padding-bottom: 4px;
            margin-bottom: 4px;
            background: #ffffff;
            color: #000000;
        }

        .school-header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .school-header-logo-cell {
            width: 72px;
            text-align: left;
            vertical-align: middle;
        }

        .school-header-spacer-cell {
            width: 72px;
        }

        .school-header-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .school-header-info {
            text-align: center;
            vertical-align: middle;
        }

        .school-name {
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.05;
        }

        .school-details {
            font-size: 11px;
            line-height: 1.15;
            margin-top: 2px;
        }

        .paper-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .paper-header h4 {
            margin: 3px 0 8px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: none;
        }

        .paper-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .paper-meta td {
            font-size: 13px;
            padding: 2px 0;
        }

        .paper-meta td:last-child {
            text-align: right;
        }

        hr {
            border: none;
            border-top: 1px solid #000;
            margin: 6px 0;
        }

        .section-heading {
            margin: 10px 0 4px;
            padding-bottom: 3px;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            border-bottom: 1px solid #000;
        }

        .group-heading {
            margin: 6px 0 4px;
            padding-bottom: 2px;
            font-size: 14px;
            font-weight: bold;
            border-bottom: 1px dashed #888;
        }

        .answer-row {
            margin-bottom: 7px;
            page-break-inside: avoid;
        }

        .answer-q,
        .answer-label {
            font-weight: bold;
        }

        .match-answer {
            margin-left: 34px;
            margin-top: 3px;
        }

        .word-footer {
            border-top: 1px solid #000;
            margin-top: 20px;
            padding-top: 5px;
            font-size: 11px;
            width: 100%;
        }

        .word-footer-left {
            float: left;
            width: 65%;
            font-weight: bold;
        }

        .word-footer-right {
            float: right;
            width: 30%;
            text-align: right;
        }
    </style>
</head>

<body>
    @php
        $schoolNameValue = $schoolName ?? ($school['name'] ?? 'Siddharth Public School');
        $schoolAddressValue = $schoolAddress ?? ($school['address'] ?? 'School Address');
        $schoolPhoneValue = $schoolPhone ?? ($school['phone'] ?? null);
        $schoolEmailValue = $schoolEmail ?? ($school['email'] ?? null);
        $schoolLogoValue = $schoolLogo ?? ($school['logo_path'] ?? null);
        $academicSessionValue = $academicSession ?? ($school['academic_session'] ?? null);

        function answerDurationLabel($minutes)
        {
            $minutes = (int) $minutes;

            if ($minutes <= 0) {
                return '-';
            }

            if ($minutes < 60) {
                return $minutes . ' Minutes';
            }

            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;

            if ($remaining === 0) {
                return $hours . ' Hrs.';
            }

            if ($remaining === 30) {
                return $hours . '½ Hrs.';
            }

            return $hours . ' Hr ' . $remaining . ' Min.';
        }

        $schoolLogoSrc = null;

        if (!empty($schoolLogoValue)) {
            $rawLogo = (string) $schoolLogoValue;

            if (str_starts_with($rawLogo, 'http') || str_starts_with($rawLogo, 'data:')) {
                $schoolLogoSrc = $rawLogo;
            } elseif (file_exists($rawLogo)) {
                $schoolLogoSrc = $rawLogo;
            } else {
                $cleanLogo = ltrim($rawLogo, '/');

                $possibleLogoPaths = [
                    public_path($cleanLogo),
                    public_path('storage/' . $cleanLogo),
                    storage_path('app/public/' . $cleanLogo),
                ];

                foreach ($possibleLogoPaths as $logoPath) {
                    if ($logoPath && file_exists($logoPath)) {
                        $schoolLogoSrc = $logoPath;
                        break;
                    }
                }
            }
        }

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
            function answerPairLeft($pair) {
                return $pair->left_value ?? $pair->left_text ?? $pair->left ?? '';
            }
        }

        if (! function_exists('answerPairRight')) {
            function answerPairRight($pair) {
                return $pair->right_value ?? $pair->right_text ?? $pair->right ?? '';
            }
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

                    if (! empty($correct)) {
                        return implode('; ', $correct);
                    }
                }

                return strip_tags($q->answer ?? '-');
            }
        }

        $questionNo = 1;
    @endphp

    <div class="first-page-header">
        <table class="school-header-table">
            <tr>
                <td class="school-header-logo-cell">
                    @if($schoolLogoSrc)
                        <img src="{{ $schoolLogoSrc }}" class="school-header-logo">
                    @endif
                </td>

                <td class="school-header-info">
                    <div class="school-name">
                        {{ $schoolNameValue }}
                    </div>

                    <div class="school-details">
                        {{ $schoolAddressValue }}
                        @if(!empty($schoolPhoneValue))
                            | Phone: {{ $schoolPhoneValue }}
                        @endif
                        @if(!empty($schoolEmailValue))
                            | Email: {{ $schoolEmailValue }}
                        @endif
                    </div>
                </td>

                <td class="school-header-spacer-cell"></td>
            </tr>
        </table>
    </div>

    <div class="paper-header">
        <h4>
            {{ strtoupper($paper->examName?->name ?? $paper->exam_type ?? 'QUESTION PAPER') }}
            @if(!empty($paper->examName?->session))
                | (Session {{ $paper->examName->session }})
            @elseif(!empty($paper->academic_session))
                | (Session {{ $paper->academic_session }})
            @elseif(!empty($paper->session))
                | (Session {{ $paper->session }})
            @else
                | (Session {{ $academicSessionValue ?? '2026-27' }})
            @endif
            - ANSWER KEY
        </h4>

        <table class="paper-meta">
            <tr>
                <td><strong>Subject:</strong> {{ $paper->subject->name ?? '-' }}</td>
                <td><strong>Duration:</strong> {{ answerDurationLabel($paper->display_duration_minutes ?? $paper->duration_minutes ?? $paper->duration ?? 0) }}</td>
            </tr>
            <tr>
                <td><strong>Class:</strong> {{ $paper->grade->name ?? '-' }}</td>
                <td><strong>Max Marks:</strong> {{ $paper->total_marks ?? 0 }}</td>
            </tr>
        </table>
    </div>

    <hr>

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

    @if(($exportMode ?? 'pdf') === 'html')
        <div class="word-footer">
            <div class="word-footer-left">{{ $schoolNameValue }}</div>
            <div class="word-footer-right">Page </div>
            <div style="clear: both;"></div>
        </div>
    @endif
</body>
</html>
