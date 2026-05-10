<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h2 {
            margin: 0;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px 0;
        }

        .section {
            margin-top: 20px;
        }

        .section-title {
            text-align: center;
            font-weight: bold;
            border: 1px solid #000;
            padding: 6px;
            background: #f2f2f2;
        }

        .instructions {
            font-style: italic;
            margin: 8px 0;
        }

        .question {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .question-row {
            width: 100%;
        }

        .q-no {
            width: 35px;
            vertical-align: top;
            font-weight: bold;
        }

        .q-body {
            vertical-align: top;
        }

        .marks {
            width: 70px;
            text-align: right;
            vertical-align: top;
            font-weight: bold;
        }

        .mcq-options {
            margin-top: 8px;
            width: 100%;
        }

        .option {
            display: inline-block;
            width: 48%;
            margin-bottom: 6px;
            vertical-align: top;
        }

        .option.short {
            width: 24%;
        }

        img {
            max-width: 250px;
            margin-top: 6px;
        }

        p {
            margin: 0 0 5px;
        }
    </style>
</head>

<body>

<div class="header">
    <h2>{{ $paper->title }}</h2>
    <div>{{ $paper->exam_type }}</div>
</div>

<div class="meta">
    <div>Class: {{ $paper->grade->name ?? '' }}</div>
    <div>Subject: {{ $paper->subject->name ?? '' }}</div>
    <div>Time: {{ $paper->duration }} Minutes</div>
    <div>Marks: {{ $paper->total_marks }}</div>
</div>

@if($paper->instructions)
    <p><strong>General Instructions:</strong></p>
    <div>{!! $paper->instructions !!}</div>
@endif

@php
    $grouped = $paper->questions->groupBy('section');
@endphp

@foreach($grouped as $section => $items)

    <div class="section">
        <div class="section-title">
            {{ $section ?: 'Section' }}
        </div>

        @if($items->first()->instructions)
            <div class="instructions">
                {{ $items->first()->instructions }}
            </div>
        @endif

        @foreach($items as $index => $item)
            @php
                $q = $item->question;
            @endphp

            <div class="question">
                <table class="question-row">
                    <tr>
                        <td class="q-no">
                            Q{{ $index + 1 }}.
                        </td>

                        <td class="q-body">
                            {!! $q->question !!}

                            @if($q->question_image)
                                <br>
                                <img src="{{ public_path('storage/' . $q->question_image) }}">
                            @endif

                            @if(in_array($q->type, ['mcq', 'multiple_mcq', 'true_false']) && $q->options)
                                <div class="mcq-options">
                                    @foreach($q->options as $optIndex => $opt)
                                        @php
                                            $text = strip_tags($opt->option_text ?? '');
                                            $class = strlen($text) <= 20 ? 'short' : '';
                                        @endphp

                                        <div class="option {{ $class }}">
                                            <strong>{{ chr(65 + $optIndex) }}.</strong>
                                            {!! $opt->option_text !!}

                                            @if($opt->option_image)
                                                <br>
                                                <img src="{{ public_path('storage/' . $opt->option_image) }}">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        <td class="marks">
                            {{ $item->marks }} Marks
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

@endforeach

</body>
</html>
