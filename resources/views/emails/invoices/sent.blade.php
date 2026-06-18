<p>Dear {{ $invoice->client_name }},</p>

<p>Greetings from <strong>Maviya IT Services</strong>.</p>

<p>
    Please find attached the invoice for
    <strong>{{ $invoice->project_name }}</strong>.
</p>

<p>
    Total Amount: <strong>Rs. {{ number_format($invoice->grand_total, 2) }}</strong><br>
    Paid Amount: <strong>Rs. {{ number_format($invoice->paid_amount, 2) }}</strong><br>
    Balance Amount: <strong>Rs. {{ number_format($invoice->balance_amount, 2) }}</strong>
</p>

<p>Regards,<br>
<strong>Maviya IT Services</strong><br>
Mobile: +91 9648209795<br>
Email: contact@yoututor.in</p>