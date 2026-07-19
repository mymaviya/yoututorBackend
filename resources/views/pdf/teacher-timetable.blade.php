<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <style>
        @page {
            margin: 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        h2,
        h4 {
            margin: 0;
            padding: 0;
            text-align: center;
        }

        h4 {
            margin-top: 4px;
            font-weight: normal;
        }

        .meta,
        .summary {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .meta td,
        .summary td {
            border: 1px solid #bbb;
            padding: 5px 7px;
            text-align: left;
        }

        .summary td {
            text-align: center;
        }

        .summary strong {
            display: block;
            font-size: 11px;
        }

        .timetable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            table-layout: fixed;
        }

        .timetable th,
        .timetable td {
            border: 1px solid #444;
            padding: 5px;
            text-align: center;
            vertical-align: top;
            word-wrap: break-word;
        }

        .timetable th {
            background: #efefef;
        }

        .period {
            width: 11%;
        }

        .subject {
            font-weight: bold;
            font-size: 9.5px;
        }

        .details {
            margin-top: 2px;
            font-size: 8px;
        }

        .room {
            margin-top: 2px;
            font-size: 8px;
            color: #555;
        }

        .substitution {
            margin-top: 3px;
            font-size: 8px;
            color: #b71c1c;
            font-weight: bold;
        }

        .empty {
            color: #aaa;
        }

        .footer {
            margin-top: 10px;
            text-align: right;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>

<body>
    @php
        $days = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];

        $mode = $mode ?? (isset($teacher) ? 'teacher' : 'class');
        $filters = $filters ?? [];
        $summary = $summary ?? [];

        $entryCollection = collect($entries);

        $entriesBySlot = $entryCollection->keyBy(
            fn ($entry) => $entry->weekday . '-' . $entry->school_bell_id
        );

        $firstEntry = $entryCollection->first();

        $className = collect([
            $firstEntry?->grade?->name,
            $firstEntry?->section?->name,
            $firstEntry?->stream?->name,
        ])->filter()->implode(' - ');

        $title = $mode === 'teacher'
            ? 'Teacher Timetable'
            : 'Class Timetable';

        $subtitle = $mode === 'teacher'
            ? ($teacher?->name ?? 'Selected Teacher')
            : ($className ?: 'Selected Class');
    @endphp

    <h2>{{ $title }}</h2>
    <h4>{{ $subtitle }}</h4>

    <table class="meta">
        <tr>
            <td>
                <strong>Mode:</strong>
                {{ ucfirst($mode) }}
            </td>

            <td>
                <strong>Generated:</strong>
                {{ now()->format('d M Y, h:i A') }}
            </td>

            @if (! empty($filters['academic_year_id']))
                <td>
                    <strong>Academic Year ID:</strong>
                    {{ $filters['academic_year_id'] }}
                </td>
            @endif
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td>
                <strong>{{ $summary['weekly_periods'] ?? $entryCollection->count() }}</strong>
                Weekly Periods
            </td>

            <td>
                <strong>{{ $summary['free_periods'] ?? 0 }}</strong>
                Free Periods
            </td>

            <td>
                <strong>{{ $summary['subjects'] ?? 0 }}</strong>
                Subjects
            </td>

            <td>
                <strong>{{ $summary['substitutions'] ?? 0 }}</strong>
                Substitutions
            </td>
        </tr>
    </table>

    <table class="timetable">
        <thead>
            <tr>
                <th class="period">Period</th>

                @foreach ($days as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse ($bells as $bell)
                <tr>
                    <td class="period">
                        <strong>{{ $bell->title }}</strong>

                        @if ($bell->start_time || $bell->end_time)
                            <br>
                            {{ $bell->start_time ?? '' }}
                            @if ($bell->start_time && $bell->end_time)
                                -
                            @endif
                            {{ $bell->end_time ?? '' }}
                        @endif
                    </td>

                    @foreach ($days as $day)
                        @php
                            $entry = $entriesBySlot->get($day . '-' . $bell->id);
                            $isSubstitution = (bool) optional(
                                $entry?->timetableEntry
                            )->is_substitution;
                            $substituteTeacher = $entry?->timetableEntry
                                ?->substituteTeacher?->name;
                        @endphp

                        <td>
                            @if ($entry)
                                <div class="subject">
                                    {{ $entry->subject?->name ?? '-' }}
                                </div>

                                @if ($mode === 'teacher')
                                    <div class="details">
                                        {{ collect([
                                            $entry->grade?->name,
                                            $entry->section?->name,
                                            $entry->stream?->name,
                                        ])->filter()->implode(' - ') }}
                                    </div>
                                @else
                                    <div class="details">
                                        {{ $entry->teacher?->name ?? '-' }}
                                    </div>
                                @endif

                                @if ($entry->room_no)
                                    <div class="room">
                                        Room: {{ $entry->room_no }}
                                    </div>
                                @endif

                                @if ($isSubstitution)
                                    <div class="substitution">
                                        Substitution
                                        @if ($substituteTeacher)
                                            — {{ $substituteTeacher }}
                                        @endif
                                    </div>
                                @endif
                            @else
                                <span class="empty">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        No active teaching periods are configured.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated by YouTutor ERP
    </div>
</body>
</html>