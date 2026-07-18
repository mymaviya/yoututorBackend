<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Teacher Timetable Updated</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 30px;
        }

        .card {
            max-width: 700px;
            margin: auto;
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #ddd;
        }

        h2 {
            margin-top: 0;
            color: #1976d2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        td:first-child {
            width: 180px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            color: #888;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="card">

    <h2>Teacher Timetable Updated</h2>

    <p>
        The timetable has been updated successfully.
    </p>

    <table>

        <tr>
            <td>Teacher</td>
            <td>{{ $teacher?->name }}</td>
        </tr>

        @if($substituteTeacher)
        <tr>
            <td>Substitute Teacher</td>
            <td>{{ $substituteTeacher->name }}</td>
        </tr>
        @endif

        <tr>
            <td>Subject</td>
            <td>{{ $subject?->name }}</td>
        </tr>

        <tr>
            <td>Grade</td>
            <td>{{ $grade?->name }}</td>
        </tr>

        <tr>
            <td>Section</td>
            <td>{{ $section?->name }}</td>
        </tr>

        <tr>
            <td>Stream</td>
            <td>{{ $stream?->name }}</td>
        </tr>

        <tr>
            <td>Weekday</td>
            <td>{{ $entry->weekday }}</td>
        </tr>

        <tr>
            <td>Period</td>
            <td>{{ $bell?->title }}</td>
        </tr>

        <tr>
            <td>Room</td>
            <td>{{ $entry->room_no }}</td>
        </tr>

        <tr>
            <td>Updated At</td>
            <td>{{ now()->format('d M Y h:i A') }}</td>
        </tr>

    </table>

    <div class="footer">
        This is an automated email from <strong>YouTutor ERP</strong>.
    </div>

</div>

</body>
</html>