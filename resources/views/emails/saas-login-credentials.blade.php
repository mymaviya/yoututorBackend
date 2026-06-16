<!DOCTYPE html>
<html>
<body style="font-family:Arial;background:#f5f7fb;padding:30px;">
<div style="max-width:700px;margin:auto;background:#fff;border-radius:10px;padding:30px;">
    <h2 style="color:#1976d2;">Your YouTutor ERP Account is Ready</h2>

    <p>Dear {{ $user->name }},</p>

    <p>Your login profile has been created for <strong>{{ $subscription->school_name }}</strong>.</p>

    <table width="100%" cellpadding="10">
        <tr>
            <td><strong>Login URL</strong></td>
            <td>{{ config('app.frontend_url', config('app.url')) }}/login</td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td>{{ $user->email }}</td>
        </tr>
        <tr>
            <td><strong>Password</strong></td>
            <td>{{ $plainPassword }}</td>
        </tr>
        <tr>
            <td><strong>Subscription Plan</strong></td>
            <td>{{ $subscription->plan?->name ?? 'Trial Plan' }}</td>
        </tr>
        <tr>
            <td><strong>Valid Till</strong></td>
            <td>{{ $subscription->ends_at }}</td>
        </tr>
    </table>

    <p>Please login and change your password after first login.</p>

    <p>Regards,<br><strong>YouTutor ERP Team</strong></p>
</div>
</body>
</html>