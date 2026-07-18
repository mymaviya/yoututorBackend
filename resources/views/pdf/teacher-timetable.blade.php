<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
        }

        h2,
        h4 {
            margin: 0;
            padding: 0;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 6px;
            text-align: center;
            vertical-align: top;
        }

        th {
            background: #efefef;
        }

        .subject {
            font-weight: bold;
        }

        .room {
            font-size: 9px;
            color: #666;
        }

        .substitution {
            color: #d32f2f;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <h2>Teacher Timetable</h2>

    @isset($teacher)
        <h4>{{ $teacher->name }}</h4>
    @endisset

    <table>

        <thead>

            <tr>

                <th width="12%">
                    Period
                </th>

                @foreach ([
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ] as $day)
                    <th>
                        {{ $day }}
                    </th>
                @endforeach

            </tr>

        </thead>

        <tbody>

            @foreach ($bells as $bell)

                <tr>

                    <td>

                        <strong>
                            {{ $bell->title }}
                        </strong>

                        <br>

                        {{ $bell->start_time }}
                        -
                        {{ $bell->end_time }}

                    </td>

                    @foreach ([
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ] as $day)

                        @php

                            $entry = collect($entries)->first(
                                fn($e) => $e->weekday == $day && $e->school_bell_id == $bell->id,
                            );

                        @endphp

                        <td>

                            @if ($entry)

                                <div class="subject">

                                    {{ $entry->subject->name ?? '-' }}

                                </div>

                                <div>

                                    {{ $entry->grade->name ?? '' }}

                                    {{ $entry->section->name ?? '' }}

                                </div>

                                <div class="room">

                                    Room:
                                    {{ $entry->room_no }}

                                </div>

                                @if (optional($entry->timetableEntry)->is_substitution)

                                    <div class="substitution">

                                        Substitute

                                    </div>

                                @endif
                            @endif

                        </td>

                    @endforeach

                </tr>

            @endforeach

        </tbody>

    </table>

</body>

</html>