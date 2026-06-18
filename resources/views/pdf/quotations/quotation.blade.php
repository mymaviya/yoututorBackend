@php
$letterheadPath = public_path('proposal-assets/maviya-letterhead.png');
$letterheadData = file_exists($letterheadPath)
? 'data:image/png;base64,' . base64_encode(file_get_contents($letterheadPath))
: '';
@endphp

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $quotation->quotation_no }}</title>

<style>
@page { size: A4 portrait; margin: 0; }

* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, sans-serif;
    font-size: 10px;
    color: #222;
}

.letterhead-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 210mm;
    height: 297mm;
    z-index: -1000;
}

.page-section {
    padding-top: 5.5cm;
    padding-bottom: 2.5cm;
    padding-left: 1.5cm;
    padding-right: 1.5cm;
    width: 180mm;
    margin: 0 auto;
}

.page-break { page-break-after: always; }

.cover-content {
    text-align: center;
    padding-top: 1.2cm;
}

.document-title {
    font-size: 30px;
    font-weight: 900;
    color: #0b2f63;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.project-title {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 28px;
}

.client-box {
    width: 100%;
    max-width: 170mm;
    margin: 0 auto;
    border: 1px solid #ccc;
    padding: 18px;
    background: rgba(255, 255, 255, 0.88);
}

.client-box h2 {
    font-size: 18px;
    margin: 8px 0 15px;
}

h3 {
    color: #0b2f63;
    border-bottom: 1px solid #0b2f63;
    padding-bottom: 5px;
    margin: 0 0 9px;
    font-size: 14px;
}

.meta {
    width: 100%;
    margin-bottom: 14px;
}

.meta td {
    padding: 5px;
    font-size: 10px;
}

table { border-collapse: collapse; }

.items {
    width: 100%;
    max-width: 100%;
    table-layout: fixed;
}

.items th,
.items td {
    border: 1px solid #999;
    padding: 3px;
    vertical-align: top;
    line-height: 1.18;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.items th {
    background: #eef3fb;
    font-size: 11px;
    text-align: center;
}

.items td { font-size: 9px; }

.text-right { text-align: right; }
.text-center { text-align: center; }

.totals {
    width: 42%;
    margin-left: auto;
    margin-top: 8px;
}

.totals td {
    border: 1px solid #999;
    padding: 5px;
    font-size: 9px;
}

.grand {
    font-weight: bold;
    background: #eef3fb;
}

.terms {
    margin-top: 12px;
}

.terms p {
    margin: 4px 0;
}

.declaration {
    margin-top: 12px;
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

<img src="{{ $letterheadData }}" class="letterhead-bg">

<div class="page-section page-break">
    <div class="cover-content">
        <div class="document-title">Quotation</div>

        <div class="project-title">
            {{ $quotation->project_name }}
        </div>

        <div class="client-box">
            <p><strong>Prepared For:</strong></p>

            <h2>{{ $quotation->organization_name ?: $quotation->client_name }}</h2>

            <p><strong>Client Name:</strong> {{ $quotation->client_name }}</p>
            <p><strong>Quotation No:</strong> {{ $quotation->quotation_no }}</p>
            <p><strong>Date:</strong> {{ optional($quotation->quotation_date)->format('d-m-Y') }}</p>
            <p><strong>Valid Until:</strong> {{ optional($quotation->valid_until)->format('d-m-Y') }}</p>
        </div>
    </div>
</div>

<div class="page-section">
    <h3>Quotation Details</h3>

    <table class="meta">
        <tr>
            <td><strong>Quotation No:</strong> {{ $quotation->quotation_no }}</td>
            <td><strong>Date:</strong> {{ optional($quotation->quotation_date)->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <td><strong>Client:</strong> {{ $quotation->client_name }}</td>
            <td><strong>Organization:</strong> {{ $quotation->organization_name }}</td>
        </tr>
        <tr>
            <td><strong>Project:</strong> {{ $quotation->project_name }}</td>
            <td><strong>Valid Until:</strong> {{ optional($quotation->valid_until)->format('d-m-Y') }}</td>
        </tr>
    </table>

    <h3>Commercial Details</h3>

    <table class="items">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="23%">Item</th>
                <th width="39%">Description</th>
                <th width="6%">Qty</th>
                <th width="14%">Rate</th>
                <th width="14%">Total</th>
            </tr>
        </thead>

        <tbody>
            @foreach($quotation->items as $index => $item)
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

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">Rs. {{ number_format($quotation->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>GST {{ $quotation->gst_percentage }}%</td>
            <td class="text-right">Rs. {{ number_format($quotation->gst_amount, 2) }}</td>
        </tr>
        <tr class="grand">
            <td>Grand Total</td>
            <td class="text-right">Rs. {{ number_format($quotation->grand_total, 2) }}</td>
        </tr>
    </table>

    @if($quotation->terms)
    <div class="terms">
        <h3>Terms & Conditions</h3>
        <p>{{ $quotation->terms }}</p>
    </div>
    @endif

    <div class="declaration">
        This is a computer-generated quotation and does not require a physical signature or seal.<br>
        <strong>Maviya IT Services</strong><br>
        Professional Software & Technology Solutions
    </div>
</div>

</body>
</html>