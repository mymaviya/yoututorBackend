<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_no }}</title>

<style>
@page {
    size: A4 portrait;
    margin: 0;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, sans-serif;
    font-size: 10px;
    color: #222;
}

.page-section {
    padding: 1.5cm;
    width: 180mm;
    margin: 0 auto;
}

.company-header {
    border-bottom: 2px solid #0b2f63;
    padding-bottom: 12px;
    margin-bottom: 18px;
}

.company-name {
    font-size: 24px;
    font-weight: 900;
    color: #0b2f63;
    text-transform: uppercase;
}

.company-line {
    margin-top: 3px;
    line-height: 1.4;
}

.invoice-top {
    width: 100%;
    margin-bottom: 18px;
}

.invoice-title {
    font-size: 28px;
    font-weight: 900;
    color: #0b2f63;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.status-badge {
    display: inline-block;
    padding: 8px 18px;
    border-radius: 20px;
    background: #dcfce7;
    color: #15803d;
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-draft {
    background: #f1f5f9;
    color: #475569;
}

.status-sent,
.status-pending {
    background: #fff7ed;
    color: #c2410c;
}

.status-partially_paid {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-paid {
    background: #dcfce7;
    color: #15803d;
}

.status-cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.info-grid {
    width: 100%;
    margin-top: 14px;
    margin-bottom: 18px;
}

.info-box {
    border: 1px solid #d8d8d8;
    padding: 12px;
    vertical-align: top;
    background: #ffffff;
}

.info-title {
    font-size: 12px;
    font-weight: bold;
    color: #0b2f63;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.info-box p {
    margin: 3px 0;
    line-height: 1.4;
}

table {
    border-collapse: collapse;
}

.items {
    width: 100%;
    table-layout: fixed;
    margin-top: 10px;
}

.items th,
.items td {
    border: 1px solid #999;
    padding: 5px;
    vertical-align: top;
    line-height: 1.25;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.items th {
    background: #eef3fb;
    font-size: 9px;
    text-align: center;
    color: #111;
}

.items td {
    font-size: 9px;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.summary-wrapper {
    width: 100%;
    margin-top: 14px;
}

.totals {
    width: 46%;
    margin-left: auto;
}

.totals td {
    border: 1px solid #999;
    padding: 6px;
    font-size: 9px;
}

.grand {
    font-weight: bold;
    background: #eef3fb;
}

.amount-due {
    background: #0b2f63;
    color: #fff;
    font-weight: bold;
}

.notes,
.payment,
.payment-history {
    margin-top: 16px;
}

.notes h3,
.payment h3,
.payment-history h3 {
    color: #0b2f63;
    border-bottom: 1px solid #0b2f63;
    padding-bottom: 5px;
    margin: 0 0 8px;
    font-size: 13px;
}

.notes p,
.payment p {
    margin: 4px 0;
    line-height: 1.45;
}

.history-table {
    width: 100%;
    table-layout: fixed;
}

.history-table th,
.history-table td {
    border: 1px solid #999;
    padding: 5px;
    font-size: 8.5px;
    vertical-align: top;
    word-wrap: break-word;
}

.history-table th {
    background: #eef3fb;
}

.declaration {
    margin-top: 18px;
    padding-top: 8px;
    border-top: 1px solid #bbb;
    text-align: center;
    font-size: 9px;
    color: #555;
    line-height: 1.4;
}
</style>
</head>

<body>

<div class="page-section">

    <div class="company-header">
        <div class="company-name">
            {{ $settings['company_name'] ?? 'Maviya IT Services' }}
        </div>

        <div class="company-line">
            {{ $settings['company_tagline'] ?? 'Software Development | ERP | CRM | Website | Mobile App' }}
        </div>

        <div class="company-line">
            {{ $settings['company_address'] ?? '' }}
        </div>

        <div class="company-line">
            Mobile: {{ $settings['company_phone'] ?? '+91 9648209795' }}
            |
            Email: {{ $settings['company_email'] ?? 'contact@yoututor.in' }}
        </div>

        <div class="company-line">
            GSTIN: {{ $settings['gst_number'] ?? 'N/A' }}
            |
            PAN: {{ $settings['pan_no'] ?? 'N/A' }}
        </div>
    </div>

    <table class="invoice-top">
        <tr>
            <td>
                <div class="invoice-title">Tax Invoice</div>
                <div><strong>Invoice No:</strong> {{ $invoice->invoice_no }}</div>
                <div><strong>Invoice Date:</strong> {{ optional($invoice->invoice_date)->format('d-m-Y') }}</div>
                <div><strong>Due Date:</strong> {{ optional($invoice->due_date)->format('d-m-Y') }}</div>
            </td>

            <td class="text-right">
                <span class="status-badge status-{{ $invoice->status }}">
                    {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
                </span>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td class="info-box" width="50%">
                <div class="info-title">Billed To</div>
                <p><strong>{{ $invoice->organization_name ?: $invoice->client_name }}</strong></p>
                <p>{{ $invoice->client_name }}</p>
                <p>{{ $invoice->project_name }}</p>
            </td>

            <td class="info-box" width="50%">
                <div class="info-title">Invoice Summary</div>
                <p><strong>Project:</strong> {{ $invoice->project_name }}</p>
                <p><strong>GST:</strong> {{ $invoice->gst_percentage }}%</p>
                <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</p>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="22%">Item</th>
                <th width="39%">Description</th>
                <th width="7%">Qty</th>
                <th width="14%">Rate</th>
                <th width="14%">Amount</th>
            </tr>
        </thead>

        <tbody>
            @foreach($invoice->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format($item->quantity, 2) }}</td>
                <td class="text-right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right">Rs. {{ number_format($item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-wrapper">
        <table class="totals">
            <tr>
                <td>Total excl. GST</td>
                <td class="text-right">Rs. {{ number_format($invoice->subtotal, 2) }}</td>
            </tr>

            <tr>
                <td>GST @ {{ $invoice->gst_percentage }}%</td>
                <td class="text-right">Rs. {{ number_format($invoice->gst_amount, 2) }}</td>
            </tr>

            <tr class="grand">
                <td>Total</td>
                <td class="text-right">Rs. {{ number_format($invoice->grand_total, 2) }}</td>
            </tr>

            <tr>
                <td>Payments</td>
                <td class="text-right">Rs. {{ number_format($invoice->paid_amount, 2) }}</td>
            </tr>

            <tr class="amount-due">
                <td>Amount Due</td>
                <td class="text-right">Rs. {{ number_format($invoice->balance_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($invoice->payments && $invoice->payments->count())
    <div class="payment-history">
        <h3>Payment History</h3>

        <table class="history-table">
            <thead>
                <tr>
                    <th width="14%">Date</th>
                    <th width="16%">Mode</th>
                    <th width="24%">Reference</th>
                    <th width="18%">Bank</th>
                    <th width="18%">Amount</th>
                    <th width="10%">By</th>
                </tr>
            </thead>

            <tbody>
                @foreach($invoice->payments as $payment)
                <tr>
                    <td>{{ optional($payment->payment_date)->format('d-m-Y') ?: $payment->payment_date }}</td>
                    <td>{{ $payment->payment_mode }}</td>
                    <td>{{ $payment->reference_no ?: '-' }}</td>
                    <td>{{ $payment->bank_name ?: '-' }}</td>
                    <td class="text-right">Rs. {{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->receiver?->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="payment">
        <h3>Bank Details for Payment</h3>

        <p><strong>Account Name:</strong> {{ $settings['bank_account_name'] ?? 'Maviya IT Services' }}</p>
        <p><strong>Bank Name:</strong> {{ $settings['bank_name'] ?? 'N/A' }}</p>
        <p><strong>Account Number:</strong> {{ $settings['bank_account_no'] ?? 'N/A' }}</p>
        <p><strong>IFSC Code:</strong> {{ $settings['bank_ifsc'] ?? 'N/A' }}</p>
        <p><strong>UPI ID:</strong> {{ $settings['upi_id'] ?? 'N/A' }}</p>
    </div>

    @if($invoice->notes)
    <div class="notes">
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="declaration">
        This is a computer-generated invoice and does not require a physical signature or seal.<br>
        <strong>{{ $settings['company_name'] ?? 'Maviya IT Services' }}</strong><br>
        Professional Software & Technology Solutions
    </div>

</div>

</body>
</html>