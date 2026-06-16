<!DOCTYPE html>
<html>
<body style="font-family:Arial;background:#f5f7fb;padding:30px;">
<div style="max-width:700px;margin:auto;background:#fff;border-radius:10px;padding:30px;">
    <h2 style="color:#1976d2;">Demo Enquiry Received</h2>

    <p>Dear {{ $demoEnquiry->contact_person ?? 'Customer' }},</p>

    <p>Thank you for your interest in YouTutor ERP. We have received your demo enquiry successfully.</p>

    <table width="100%" cellpadding="10">
        <tr>
            <td><strong>School Name</strong></td>
            <td>{{ $demoEnquiry->school_name }}</td>
        </tr>
        <tr>
            <td><strong>Mobile</strong></td>
            <td>{{ $demoEnquiry->mobile }}</td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td>{{ $demoEnquiry->email }}</td>
        </tr>
    </table>

    <p>Our team will contact you shortly for the next steps.</p>

    <p>Regards,<br><strong>YouTutor ERP Team</strong></p>
</div>
</body>
</html>