<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
</head>
<body style="font-family:Arial, Helvetica, sans-serif;background:#f5f7fb;padding:30px;">

<div style="max-width:700px;margin:auto;background:#ffffff;border-radius:10px;padding:30px;">

    <h2 style="color:#2e7d32;margin-top:0;">
        Payment Received Successfully
    </h2>

    <p>
        Dear {{ $transaction->subscription?->contact_person ?? 'Customer' }},
    </p>

    <p>
        Thank you for your payment. We have successfully received your payment for YouTutor ERP.
    </p>

    <table width="100%" cellpadding="10" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <td width="35%"><strong>School Name</strong></td>
            <td>{{ $transaction->subscription?->school_name }}</td>
        </tr>

        <tr>
            <td><strong>Transaction ID</strong></td>
            <td>{{ $transaction->id }}</td>
        </tr>

        <tr>
            <td><strong>Razorpay Payment ID</strong></td>
            <td>{{ $transaction->razorpay_payment_id ?? 'Pending' }}</td>
        </tr>

        <tr>
            <td><strong>Amount Paid</strong></td>
            <td>₹{{ number_format($transaction->amount, 2) }}</td>
        </tr>

        <tr>
            <td><strong>Status</strong></td>
            <td>{{ ucfirst($transaction->status) }}</td>
        </tr>

        <tr>
            <td><strong>Payment Date</strong></td>
            <td>{{ $transaction->created_at?->format('d M Y h:i A') }}</td>
        </tr>
    </table>

    <br>

    <div style="background:#e8f5e9;padding:15px;border-radius:6px;">
        <strong>Important:</strong><br>
        Please keep this email as your payment receipt for future reference.
    </div>

    <br>

    <p>
        If you have any questions regarding your payment, please contact our support team.
    </p>

    <p>
        Regards,<br>
        <strong>YouTutor ERP Team</strong>
    </p>

</div>

</body>
</html>