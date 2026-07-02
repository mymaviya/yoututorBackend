<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 10mm 13mm 22mm 13mm;
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

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "Times New Roman", serif;
            font-size: 14px;
            line-height: 1.12;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .paper-header {
            text-align: center;
            margin-bottom: 6px;
        }

        .paper-header h2 {
            margin: 0 0 5px;
            font-size: 21px;
            font-weight: bold;
            text-transform: uppercase;
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

        .general-instructions {
            margin: 6px 0 8px;
            font-size: 13px;
            line-height: 1.12;
        }

        .general-instructions ol,
        .general-instructions ul {
            margin: 3px 0 0 18px;
            padding-left: 10px;
        }

        .general-instructions li {
            margin-bottom: 1px;
        }

        .general-instructions p {
            margin: 0;
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

        .preview-question {
            margin-bottom: 5px;
            page-break-inside: avoid;
        }

        .question-table {
            width: 100%;
            border-collapse: collapse;
        }

        .question-number {
            width: 38px;
            vertical-align: top;
            font-weight: bold;
            white-space: nowrap;
            padding-top: 1px;
        }

        .question-body {
            vertical-align: top;
            line-height: 1.12;
            padding-right: 8px;
            font-size: 14px;
        }

        .question-body p {
            margin: 0 0 2px;
        }

        .marks-box {
            width: 72px;
            text-align: right;
            vertical-align: top;
            font-size: 12.5px;
            font-weight: bold;
            white-space: nowrap;
            padding-top: 1px;
        }

        .mcq-options {
            width: 100%;
            margin-top: 4px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .mcq-options td {
            vertical-align: top;
            padding: 2px 18px 2px 0;
            line-height: 1.12;
            font-size: 13.5px;
            word-break: normal;
        }

        .mcq-options.four-column td {
            width: 25%;
        }

        .mcq-options.three-column td {
            width: 33.33%;
        }

        .mcq-options.two-column td {
            width: 50%;
        }

        .option-label {
            font-weight: bold;
            white-space: nowrap;
        }

        .match-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .match-grid td {
            width: 50%;
            vertical-align: top;
            padding-right: 24px;
        }

        .match-row {
            margin-bottom: 3px;
            line-height: 1.15;
        }

        .question-image {
            max-width: 260px;
            margin-top: 5px;
            border: 1px solid #ddd;
        }

        .option-image {
            max-width: 110px;
            margin-top: 4px;
            border: 1px solid #ddd;
        }

        table,
        tr {
            page-break-inside: avoid;
        }



        .pdf-header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header-table td {
            vertical-align: middle;
        }

        .school-logo-cell {
            width: 68px;
            text-align: left;
        }

        .school-logo {
            max-width: 55px;
            max-height: 55px;
        }

        .school-info-cell {
            text-align: center;
            padding-right: 68px;
        }

        .school-address {
            font-size: 11.5px;
            line-height: 1.2;
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

    function pdfDurationLabel($minutes)
    {
    $minutes = (int) $minutes;

    if ($minutes <= 0) {
        return '-' ;
        }

        if ($minutes < 60) {
        return $minutes . ' Minutes' ;
        }

        $hours=intdiv($minutes, 60);
        $remaining=$minutes % 60;

        if ($remaining===0) {
        return $hours . ' Hrs.' ;
        }

        if ($remaining===30) {
        return $hours . '½ Hrs.' ;
        }

        return $hours . ' Hr ' . $remaining . ' Min.' ;
        }

        $schoolLogoSrc=null;

        if (!empty($schoolLogoValue)) {
        $rawLogo=(string) $schoolLogoValue;

        if (str_starts_with($rawLogo, 'http' ) || str_starts_with($rawLogo, 'data:' )) {
        $schoolLogoSrc=$rawLogo;
        } elseif (file_exists($rawLogo)) {
        $schoolLogoSrc=$rawLogo;
        } else {
        $cleanLogo=ltrim($rawLogo, '/' );

        $possibleLogoPaths=[
        public_path($cleanLogo),
        public_path('storage/' . $cleanLogo),
        storage_path('app/public/' . $cleanLogo),
        ];

        foreach ($possibleLogoPaths as $logoPath) {
        if ($logoPath && file_exists($logoPath)) {
        $schoolLogoSrc=$logoPath;
        break;
        }
        }
        }
        }
        @endphp

        <div class="first-page-header">
        <table class="school-header-table">
            <tr>
                <td class="school-header-logo-cell">
                    @if($schoolLogoSrc)
                    <img
                        src="{{ $schoolLogoSrc }}"
                        class="school-header-logo">
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
            </h4>

            <table class="paper-meta">
                <tr>
                    <td>
                        <strong>Subject:</strong>
                        {{ $paper->subject->name ?? '-' }}
                    </td>

                    <td>
                        <strong>Duration:</strong>
                        {{ pdfDurationLabel($paper->display_duration_minutes ?? $paper->duration_minutes ?? $paper->duration ?? 0) }}
                    </td>
                </tr>

                <tr>
                    <td>
                        <strong>Class:</strong>
                        {{ $paper->grade->name ?? '-' }}
                    </td>

                    <td>
                        <strong>Max Marks:</strong>
                        {{ $paper->total_marks ?? 0 }}
                    </td>
                </tr>
            </table>
        </div>

        <hr>

        @if ($paper->instructions)
        <div class="general-instructions">
            {!! $paper->instructions !!}
        </div>
        @endif

        @php
        $questionNo = 1;

        function pdfQuestionTypeLabel($type)
        {
        return [
        'mcq' => 'Multiple Choice Questions',
        'multiple_mcq' => 'Multiple Correct Questions',
        'true_false' => 'True / False',
        'fill_blank' => 'Fill in the Blanks',
        'short' => 'Short Answer Questions',
        'long' => 'Long Answer Questions',
        'match_column' => 'Match the Columns',
        'assertion_reason' => 'Assertion & Reason',
        'numerical' => 'Numerical Problems',
        ][$type] ?? ucwords(str_replace('_', ' ', $type));
        }

        function pdfMarksLabel($marks)
        {
        $marks = (float) $marks;

        return rtrim(rtrim(number_format($marks, 2), '0'), '.') . ' ' . ($marks > 1 ? 'Marks' : 'Mark');
        }

        function pdfOptionClass($options)
        {
        $count = count($options);
        $maxLength = 0;

        foreach ($options as $option) {
        $length = strlen(strip_tags($option->option_text ?? ''));

        if ($length > $maxLength) {
        $maxLength = $length;
        }
        }

        if ($count <= 2) {
            return 'two-column' ;
            }

            if ($count===3) {
            return 'three-column' ;
            }

            if ($count>= 4) {
            return $maxLength <= 22 ? 'four-column' : 'two-column' ;
                }

                return 'two-column' ;
                }

                function pdfOptionColumns($class)
                {
                return match ($class) { 'four-column'=> 4,
                'three-column' => 3,
                default => 2,
                };
                }
                @endphp

                @foreach ($paper->questions->groupBy('section') as $sectionName => $paperQuestions)
                @php
                $sectionTotal = $paperQuestions->sum('marks');
                @endphp

                <div class="section-heading">
                    {{ $sectionName ?: 'Section A' }}
                    ({{ rtrim(rtrim(number_format($sectionTotal, 2), '0'), '.') }}
                    {{ $sectionTotal > 1 ? 'Marks' : 'Mark' }})
                </div>

                @foreach ($paperQuestions->groupBy(fn ($pq) => $pq->question?->type?->slug ?? 'unknown') as $type => $typeQuestions)
                <div class="group-heading">
                    {{ pdfQuestionTypeLabel($type) }}
                </div>

                @foreach ($typeQuestions as $paperQuestion)
                @php
                $q = $paperQuestion->question;
                @endphp

                @if ($q)
                <div class="preview-question">
                    <table class="question-table">
                        <tr>
                            <td class="question-number">
                                Q{{ $questionNo++ }}.
                            </td>

                            <td class="question-body">
                                {!! $q->question ?? '' !!}

                                @if ($q->question_image)
                                <br>
                                <img src="{{ public_path($q->question_image) }}" class="question-image">
                                @endif

                                @if (in_array($q->type?->slug, ['mcq', 'multiple_mcq', 'true_false']) && $q->options->count())
                                @php
                                $optionClass = pdfOptionClass($q->options);
                                $cols = pdfOptionColumns($optionClass);
                                @endphp

                                <table class="mcq-options {{ $optionClass }}">
                                    <tr>
                                        @foreach ($q->options as $index => $option)
                                        <td>
                                            <span class="option-label">
                                                {{ chr(65 + $index) }}.
                                            </span>

                                            {!! $option->option_text !!}

                                            @if ($option->option_image)
                                            <br>
                                            <img src="{{ public_path($option->option_image) }}"
                                                class="option-image">
                                            @endif
                                        </td>

                                        @if (($index + 1) % $cols === 0 && !$loop->last)
                                    </tr>
                                    <tr>
                                        @endif
                                        @endforeach
                                    </tr>
                                </table>
                                @endif

                                @if ($q->type?->slug === 'match_column' && $q->matchPairs->count())
                                @php
                                $leftColumn = $q->matchPairs->shuffle()->values();
                                $rightColumn = $q->matchPairs->shuffle()->values();
                                @endphp

                                <table class="match-grid">
                                    <tr>
                                        <td>
                                            <strong>Column A</strong>

                                            @foreach ($leftColumn as $i => $pair)
                                            <div class="match-row">
                                                {{ $i + 1 }}.
                                                {{ $pair->left_text }}
                                            </div>
                                            @endforeach
                                        </td>

                                        <td>
                                            <strong>Column B</strong>

                                            @foreach ($rightColumn as $i => $pair)
                                            <div class="match-row">
                                                {{ chr(65 + $i) }}.
                                                {{ $pair->right_text }}
                                            </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                </table>
                                @endif
                            </td>

                            <td class="marks-box">
                                {{ pdfMarksLabel($paperQuestion->marks) }}
                            </td>
                        </tr>
                    </table>
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