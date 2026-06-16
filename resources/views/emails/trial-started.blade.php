<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Trial Started</title>
</head>
<body style="font-family:Arial, Helvetica, sans-serif;background:#f5f7fb;padding:30px;">

<div style="max-width:700px;margin:auto;background:#ffffff;border-radius:10px;padding:30px;">

    <h2 style="color:#1976d2;margin-top:0;">
        Your 15 Days Free Trial Has Started
    </h2>

    <p>
        Dear {{ $subscription->contact_person ?? 'Customer' }},
    </p>

    <p>
        Your YouTutor ERP free trial has been started successfully.
    </p>

    <table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td width="35%"><strong>School Name</strong></td>
            <td>{{ $subscription->school_name }}</td>
        </tr>

        <tr>
            <td><strong>Plan</strong></td>
            <td>{{ $subscription->plan?->name ?? 'Free Demo' }}</td>
        </tr>

        <tr>
            <td><strong>Status</strong></td>
            <td>{{ ucfirst($subscription->status) }}</td>
        </tr>

        <tr>
            <td><strong>Trial Start Date</strong></td>
            <td>{{ $subscription->starts_at }}</td>
        </tr>

        <tr>
            <td><strong>Trial End Date</strong></td>
            <td>{{ $subscription->ends_at }}</td>
        </tr>

        <tr>
            <td><strong>License Key</strong></td>
            <td>{{ $subscription->licenseKey?->license_key ?? 'Will be shared shortly' }}</td>
        </tr>
    </table>

    <br>

    <div style="background:#e3f2fd;padding:15px;border-radius:6px;">
        During your trial, you can explore question bank, blueprint, paper generator,
        teacher task management, approval workflow and reports.
    </div>

    <br>

    <p>
        Regards,<br>
        <strong>YouTutor ERP Team</strong>
    </p>

</div>

</body>
</html>