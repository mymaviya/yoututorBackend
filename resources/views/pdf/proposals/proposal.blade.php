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
    <title>{{ $proposal->proposal_no }}</title>

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

        .page-break {
            page-break-after: always;
        }

        .cover-content {
            text-align: center;
            padding-top: 1.2cm;
        }

        .proposal-title {
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

        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section p {
            line-height: 1.4;
            margin: 0 0 6px;
        }

        ul {
            margin-top: 4px;
            margin-bottom: 4px;
        }

        li {
            margin-bottom: 2px;
        }

        table {
            border-collapse: collapse;
        }

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
            font-size: 12px;
            text-align: center;
        }

        .items td {
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

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

        .payment-terms {
            margin-top: 12px;
        }

        .payment-terms p {
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
            <div class="proposal-title">Project Proposal</div>

            <div class="project-title">
                {{ $proposal->project_name }}
            </div>

            <div class="client-box">
                <p><strong>Prepared For:</strong></p>

                <h2>{{ $proposal->organization_name ?: $proposal->client_name }}</h2>

                <p><strong>Client Name:</strong> {{ $proposal->client_name }}</p>
                <p><strong>Proposal No:</strong> {{ $proposal->proposal_no }}</p>
                <p><strong>Date:</strong> {{ $proposal->created_at?->format('d-m-Y') }}</p>
            </div>
        </div>
    </div>

    <div class="page-section page-break">
        @foreach($proposal->sections as $section)
        @if(
        $section->is_visible &&
        $section->section_key !== 'cover_page' &&
        $section->section_key !== 'commercials'
        )
        <div class="section">
            <h3>{{ $section->title }}</h3>
            {!! $section->content !!}
        </div>
        @endif
        @endforeach
    </div>

    <div class="page-section">
        @if($proposal->items->count())
        <div class="section">
            <h3>Commercial Details</h3>

            <table class="items">
                <thead>
                    <tr>
                        <th width="4%">#</th>
                        <th width="23%">Module</th>
                        <th width="39%">Description</th>
                        <th width="6%">Qty</th>
                        <th width="14%">Rate</th>
                        <th width="14%">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($proposal->items as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $item->module_name }}</td>
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
                    <td class="text-right">Rs. {{ number_format($proposal->subtotal, 2) }}</td>
                </tr>

                <tr>
                    <td>GST {{ $proposal->gst_percentage }}%</td>
                    <td class="text-right">Rs. {{ number_format($proposal->gst_amount, 2) }}</td>
                </tr>

                <tr class="grand">
                    <td>Grand Total</td>
                    <td class="text-right">Rs. {{ number_format($proposal->grand_total, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td colspan="2">{{ amountToWords($proposal->grand_total) }}</td>
                </tr>
            </table>
        </div>
        @endif

        @if($proposal->payment_terms)
        <div class="payment-terms">
            <h3>Payment Terms</h3>
            <p>{{ $proposal->payment_terms }}</p>
        </div>
        @endif

        <div class="declaration">
            This is a computer-generated proposal and does not require a physical signature or seal.<br>
            <strong>Maviya IT Services</strong><br>
            Professional Software & Technology Solutions
        </div>
    </div>

</body>

</html>