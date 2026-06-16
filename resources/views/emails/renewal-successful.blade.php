<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Renewal Successful</title>
</head>
<body style="font-family:Arial, Helvetica, sans-serif;background:#f5f7fb;padding:30px;">

<div style="max-width:700px;margin:auto;background:#ffffff;border-radius:10px;padding:30px;">

    <h2 style="color:#2e7d32;">
        Subscription Renewal Successful
    </h2>

    <p>
        Dear {{ $renewal->subscription->contact_person }},
    </p>

    <p>
        Your YouTutor ERP subscription has been renewed successfully.
    </p>

    <table width="100%" cellpadding="10" cellspacing="0">
        <tr>
            <td width="35%"><strong>School Name</strong></td>
            <td>{{ $renewal->subscription->school_name }}</td>
        </tr>

        <tr>
            <td><strong>Renewal Type</strong></td>
            <td>{{ ucwords(str_replace('_',' ', $renewal->renewal_type)) }}</td>
        </tr>

        <tr>
            <td><strong>Plan</strong></td>
            <td>{{ $renewal->plan?->name }}</td>
        </tr>

        <tr>
            <td><strong>Old Expiry</strong></td>
            <td>{{ $renewal->old_end_date }}</td>
        </tr>

        <tr>
            <td><strong>New Expiry</strong></td>
            <td>{{ $renewal->new_end_date }}</td>
        </tr>

        <tr>
            <td><strong>Amount</strong></td>
            <td>₹{{ number_format($renewal->renewal_amount, 2) }}</td>
        </tr>
    </table>

    <br>

    <div style="background:#e8f5e9;padding:15px;border-radius:6px;">
        Your subscription is now active and all services will continue without interruption.
    </div>

    <br>

    <p>
        Regards,<br>
        <strong>YouTutor ERP Team</strong>
    </p>

</div>

</body>
</html>