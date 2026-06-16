<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Expiry Reminder</title>
</head>
<body style="font-family:Arial, Helvetica, sans-serif;background:#f5f7fb;padding:30px;">

<div style="max-width:700px;margin:auto;background:#ffffff;border-radius:10px;padding:30px;">

    @if($daysRemaining <= 0)
        <h2 style="color:#d32f2f;">
            Subscription Expired
        </h2>
    @else
        <h2 style="color:#f57c00;">
            Subscription Expiry Reminder
        </h2>
    @endif

    <p>
        Dear {{ $subscription->contact_person }},
    </p>

    @if($daysRemaining <= 0)

        <p>
            Your YouTutor ERP subscription has expired.
        </p>

    @elseif($daysRemaining === 1)

        <p>
            Your YouTutor ERP subscription will expire tomorrow.
        </p>

    @else

        <p>
            Your YouTutor ERP subscription will expire in
            <strong>{{ $daysRemaining }} days</strong>.
        </p>

    @endif

    <table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td width="35%"><strong>School Name</strong></td>
            <td>{{ $subscription->school_name }}</td>
        </tr>

        <tr>
            <td><strong>Plan</strong></td>
            <td>{{ $subscription->plan?->name }}</td>
        </tr>

        <tr>
            <td><strong>Expiry Date</strong></td>
            <td>{{ $subscription->ends_at }}</td>
        </tr>

        <tr>
            <td><strong>Status</strong></td>
            <td>{{ ucfirst($subscription->status) }}</td>
        </tr>
    </table>

    <br>

    <div style="background:#fff3e0;padding:15px;border-radius:6px;">
        <strong>Action Required:</strong><br>
        Please renew your subscription before expiry to ensure uninterrupted access to YouTutor ERP services.
    </div>

    <br>

    <p>
        For renewal assistance, please contact our support team.
    </p>

    <p>
        Regards,<br>
        <strong>YouTutor ERP Team</strong>
    </p>

</div>

</body>
</html>