<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Activated</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background:#f5f5f5; padding:30px;">

<div style="max-width:700px;margin:auto;background:#ffffff;border-radius:8px;padding:30px;">

    <h2 style="color:#1976d2;">
        Subscription Activated
    </h2>

    <p>
        Dear {{ $subscription->contact_person }},
    </p>

    <p>
        Congratulations! Your YouTutor ERP subscription has been activated successfully.
    </p>

    <table cellpadding="8" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <tr>
            <td><strong>School Name</strong></td>
            <td>{{ $subscription->school_name }}</td>
        </tr>

        <tr>
            <td><strong>Plan</strong></td>
            <td>{{ $subscription->plan?->name }}</td>
        </tr>

        <tr>
            <td><strong>Subscription Status</strong></td>
            <td>{{ ucfirst($subscription->status) }}</td>
        </tr>

        <tr>
            <td><strong>Start Date</strong></td>
            <td>{{ $subscription->starts_at }}</td>
        </tr>

        <tr>
            <td><strong>Expiry Date</strong></td>
            <td>{{ $subscription->ends_at }}</td>
        </tr>
    </table>

    <br>

    <p>
        You can now start using all features included in your subscription plan.
    </p>

    <p>
        If you require assistance, our support team will be happy to help.
    </p>

    <br>

    <p>
        Regards,<br>
        <strong>YouTutor ERP Team</strong>
    </p>

</div>

</body>
</html>