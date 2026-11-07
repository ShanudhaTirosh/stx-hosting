<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th { text-align: left; border-bottom: 2px solid #1a1a1a; padding: 6px 4px; font-size: 11px; text-transform: uppercase; }
        td { border-bottom: 1px solid #ddd; padding: 8px 4px; }
        .right { text-align: right; }
        .total td { border-bottom: none; font-weight: bold; font-size: 14px; }
        .badge { display: inline-block; padding: 2px 8px; border: 1px solid #1a1a1a; font-size: 11px; text-transform: uppercase; }
    </style>
</head>
<body>
    <table style="margin-top:0">
        <tr>
            <td style="border:0;padding:0">
                <h1>{{ $business['name'] ?? 'Invoice' }}</h1>
                <div class="muted">{{ $business['address'] ?? '' }}</div>
                <div class="muted">{{ $business['email'] ?? '' }}</div>
            </td>
            <td style="border:0;padding:0" class="right">
                <h1>INVOICE</h1>
                <div>{{ $invoice->invoice_number }}</div>
                <div class="muted">Issued {{ $invoice->created_at?->format('d M Y') }}</div>
                <div class="muted">Due {{ $invoice->due_at?->format('d M Y') }}</div>
                <div style="margin-top:6px"><span class="badge">{{ str_replace('_', ' ', $invoice->status) }}</span></div>
            </td>
        </tr>
    </table>

    <p style="margin-top:24px"><strong>Billed to</strong><br>
        {{ $invoice->user?->name }}<br>
        <span class="muted">{{ $invoice->user?->email }}</span>
    </p>

    <table>
        <thead>
            <tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_price_cents / 100, 2) }}</td>
                    <td class="right">{{ number_format($item->total_price_cents / 100, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="right">Total ({{ $invoice->currency }})</td>
                <td class="right">{{ number_format($invoice->total_cents / 100, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->paid_at)
        <p class="muted" style="margin-top:20px">Paid on {{ $invoice->paid_at->format('d M Y') }}. Thank you.</p>
    @endif
</body>
</html>
