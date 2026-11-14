<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }} — STX Enterprises</title>
    <style>
        @page {
            margin: 18mm 16mm 18mm 16mm;
            size: A4 portrait;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #20242b;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Top Header */
        .header-table {
            margin-bottom: 24px;
        }

        .brand-logo-img {
            height: 40px;
            width: auto;
            display: block;
        }

        .brand-sub {
            margin-top: 5px;
            font-weight: bold;
            font-size: 8px;
            letter-spacing: 3px;
            color: #07060d;
            border-top: 2px solid #07060d;
            padding-top: 4px;
            display: inline-block;
        }

        .inv-title {
            text-align: right;
        }

        .inv-label {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #07060d;
            margin: 0;
            line-height: 1.1;
        }

        .inv-number {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12px;
            font-weight: bold;
            color: #4a32a8;
            margin-top: 4px;
        }

        /* Accent Ribbon Bar */
        .ribbon-table {
            margin-bottom: 24px;
            height: 5px;
        }

        .ribbon-teal {
            background-color: #20c6e0;
            width: 35%;
            height: 5px;
        }

        .ribbon-dark {
            background-color: #07060d;
            width: 65%;
            height: 5px;
        }

        /* Metadata 3-Column Section */
        .meta-table {
            margin-bottom: 28px;
        }

        .meta-table td {
            vertical-align: top;
            width: 33.33%;
            padding-right: 15px;
        }

        .meta-heading {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #146a8a;
            margin-bottom: 6px;
        }

        .meta-body {
            font-size: 11px;
            color: #3a4048;
            line-height: 1.6;
        }

        .meta-body strong {
            color: #07060d;
        }

        /* Line Items Table */
        .items-table {
            margin-bottom: 20px;
        }

        .items-table thead th {
            background-color: #07060d;
            color: #ffffff;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 8px 10px;
            text-align: left;
        }

        .items-table thead th.center {
            text-align: center;
        }

        .items-table thead th.right {
            text-align: right;
        }

        .items-table tbody td {
            padding: 10px;
            font-size: 11px;
            border-bottom: 1px solid #eef1f4;
            color: #20242b;
        }

        .items-table tbody tr.zebra {
            background-color: #f7f8fa;
        }

        .items-table tbody td.desc strong {
            display: block;
            color: #07060d;
            font-size: 11.5px;
        }

        .items-table tbody td.desc span {
            color: #5b6472;
            font-size: 10px;
        }

        .items-table tbody td.center {
            text-align: center;
        }

        .items-table tbody td.right {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }

        /* Totals Block */
        .totals-wrapper {
            margin-top: 10px;
            margin-bottom: 30px;
        }

        .totals-table {
            width: 280px;
            float: right;
        }

        .totals-table td {
            padding: 4px 0;
            font-size: 11px;
            color: #5b6472;
        }

        .totals-table td.amount {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
            color: #20242b;
        }

        .totals-table tr.total-row td {
            border-top: 2px solid #07060d;
            padding-top: 8px;
            font-size: 13px;
            font-weight: bold;
            color: #07060d;
        }

        .totals-table tr.total-row td.amount {
            color: #146a8a;
            font-size: 14px;
        }

        .clear {
            clear: both;
        }

        /* Bottom Section: Notes & Status */
        .bottom-table {
            margin-top: 20px;
            border-top: 1px solid #d8dce1;
            padding-top: 16px;
        }

        .bottom-table td {
            vertical-align: top;
        }

        .notes-text {
            font-size: 10px;
            color: #5b6472;
            line-height: 1.6;
            padding-right: 20px;
        }

        .status-badge {
            display: inline-block;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 12px;
            border-radius: 12px;
            border: 1.5px solid #4a32a8;
            color: #4a32a8;
            text-align: center;
        }

        .status-badge.paid {
            border-color: #10b981;
            color: #10b981;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="brand-logo">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" class="brand-logo-img" alt="STX Enterprises Logo">
                    @endif
                    <div class="brand-sub">DIGITAL EMPIRE STUDIO</div>
                </div>
            </td>
            <td class="inv-title" style="width: 50%; vertical-align: middle;">
                <div class="inv-label">INVOICE</div>
                <div class="inv-number">No. {{ $invoice->invoice_number }}</div>
            </td>
        </tr>
    </table>

    <!-- Gradient Accent Ribbon -->
    <table class="ribbon-table">
        <tr>
            <td class="ribbon-teal"></td>
            <td class="ribbon-dark"></td>
        </tr>
    </table>

    <!-- 3-Column Meta Info -->
    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-heading">Billed to</div>
                <div class="meta-body">
                    <strong>{{ $invoice->user?->name ?: ($invoice->team?->name ?: 'Customer') }}</strong><br>
                    {{ $invoice->user?->email ?: $invoice->team?->billing_email }}<br>
                    {{ $invoice->team?->address ?: 'Western Province, Sri Lanka' }}
                </div>
            </td>
            <td>
                <div class="meta-heading">Invoice Details</div>
                <div class="meta-body">
                    Issue date: <strong>{{ $invoice->created_at?->format('d M Y') }}</strong><br>
                    Due date: <strong>{{ $invoice->due_at ? $invoice->due_at->format('d M Y') : $invoice->created_at?->addDays(14)->format('d M Y') }}</strong><br>
                    Terms: <strong>Net 14</strong>
                </div>
            </td>
            <td>
                <div class="meta-heading">Pay to</div>
                <div class="meta-body">
                    <strong>STX Enterprises</strong> (Shanudha Tirosh)<br>
                    Negombo, Western Province, Sri Lanka<br>
                    hello@stxenterprises.com<br>
                    www.stxenterprises.com
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 54%;">Description</th>
                <th class="center" style="width: 12%;">Qty</th>
                <th class="right" style="width: 16%;">Rate</th>
                <th class="right" style="width: 18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $index => $item)
                <tr class="{{ $index % 2 === 1 ? 'zebra' : '' }}">
                    <td class="desc">
                        <strong>{{ $item->description }}</strong>
                        @if ($item->notes)
                            <span>{{ $item->notes }}</span>
                        @endif
                    </td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_price_cents / 100, 2) }} {{ $invoice->currency }}</td>
                    <td class="right">{{ number_format($item->total_price_cents / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr>
                    <td class="desc">
                        <strong>STX Hosting Cloud Infrastructure Service</strong>
                        <span>Subscription Services</span>
                    </td>
                    <td class="center">1</td>
                    <td class="right">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
                    <td class="right">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td>Subtotal</td>
                <td class="amount">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td>Tax (0%)</td>
                <td class="amount">0.00 {{ $invoice->currency }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Due</td>
                <td class="amount">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </table>
        <div class="clear"></div>
    </div>

    <!-- Notes & Status Section -->
    <table class="bottom-table">
        <tr>
            <td style="width: 70%;" class="notes-text">
                <div class="meta-heading" style="margin-bottom: 4px;">Notes &amp; Payment Details</div>
                Thank you for working with STX Enterprises. Payment is due within 14 days of the issue date. Bank transfer and deposit receipts can be uploaded directly to your client dashboard at www.stxenterprises.com. For questions regarding this invoice, reply to hello@stxenterprises.com.
                @if ($invoice->paid_at)
                    <div style="margin-top: 6px; color: #10b981; font-weight: bold;">
                        ✓ Payment confirmed on {{ $invoice->paid_at->format('d M Y') }}. Thank you!
                    </div>
                @endif
            </td>
            <td style="width: 30%; text-align: right; vertical-align: middle;">
                <div class="meta-heading" style="margin-bottom: 6px;">Status</div>
                <div class="status-badge {{ $invoice->status === 'paid' ? 'paid' : '' }}">
                    {{ $invoice->status === 'paid' ? 'PAID' : str_replace('_', ' ', strtoupper($invoice->status)) }}
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
