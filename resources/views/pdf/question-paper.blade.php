<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 16mm 13mm;
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
            padding: 2px 10px 2px 0;
            line-height: 1.12;
            font-size: 13.5px;
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

        .mcq-options.one-column td {
            width: 100%;
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
    </style>
</head>

<body>

    <div class="paper-header">
        <h2>{{ $paper->title ?? 'Question Paper' }}</h2>

        <table class="paper-meta">
            <tr>
                <td>
                    <strong>Subject:</strong>
                    {{ $paper->subject->name ?? '-' }}
                </td>

                <td>
                    <strong>Duration:</strong>
                    {{ $paper->duration ?? 0 }} Minutes
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
            $maxLength = 0;

            foreach ($options as $option) {
                $length = strlen(strip_tags($option->option_text ?? ''));

                if ($length > $maxLength) {
                    $maxLength = $length;
                }
            }

            if ($maxLength <= 18) {
                return 'four-column';
            }
            if ($maxLength <= 40) {
                return 'three-column';
            }
            if ($maxLength <= 80) {
                return 'two-column';
            }

            return 'one-column';
        }

        function pdfOptionColumns($class)
        {
            return match ($class) {
                'four-column' => 4,
                'three-column' => 3,
                'two-column' => 2,
                default => 1,
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

        @foreach ($paperQuestions->groupBy('question.type') as $type => $typeQuestions)
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

                                    @if (in_array($q->type, ['mcq', 'multiple_mcq', 'true_false']) && $q->options->count())
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

            @if ($q->type === 'match_column' && $q->matchPairs->count())
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

</body>

</html>
