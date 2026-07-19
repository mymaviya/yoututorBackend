<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] ?? 'Timetable Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { margin-bottom: 12px; color: #555; }
        .summary { margin-bottom: 12px; }
        .summary span { margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 5px; vertical-align: top; }
        th { background: #eee; font-weight: bold; }
        .muted { color: #777; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] ?? 'Timetable Report' }}</h1>

    @if(!empty($report['timetable']))
        <div class="meta">
            Version {{ $report['timetable']->version ?? 1 }} ·
            Status: {{ ucfirst($report['timetable']->status ?? 'draft') }}
        </div>
    @endif

    @if(!empty($report['summary']))
        <div class="summary">
            <span>Total periods: {{ $report['summary']['total_periods'] ?? 0 }}</span>
            <span>Teaching days: {{ $report['summary']['teaching_days'] ?? 0 }}</span>
            <span>Subjects: {{ $report['summary']['subjects'] ?? 0 }}</span>
            <span>Teachers: {{ $report['summary']['teachers'] ?? 0 }}</span>
        </div>
    @endif

    <table>
        <thead>
        <tr>
            <th>Day</th>
            <th>Period</th>
            <th>Time</th>
            <th>Class</th>
            <th>Subject</th>
            <th>Teacher</th>
            <th>Room</th>
            <th>Group</th>
            <th>Remarks</th>
        </tr>
        </thead>
        <tbody>
        @forelse($report['rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['day_name'] ?? '' }}</td>
                <td>{{ $row['period_title'] ?? ($row['period_number'] ?? '') }}</td>
                <td>{{ $row['time'] ?? '' }}</td>
                <td>{{ $row['class_name'] ?? '' }}</td>
                <td>{{ $row['subject'] ?? '' }}</td>
                <td>
                    {{ $row['teacher'] ?? '' }}
                    @if(!empty($row['is_substitution']))
                        <span class="muted">(Substitute)</span>
                    @endif
                </td>
                <td>{{ $row['room'] ?? '' }}</td>
                <td>{{ $row['student_group'] ?? ($row['parallel_group'] ?? '') }}</td>
                <td>{{ $row['remarks'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No timetable entries found.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
