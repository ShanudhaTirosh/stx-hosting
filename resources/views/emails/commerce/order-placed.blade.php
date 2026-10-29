<x-emails.stx-layout 
    eyebrow="Order Confirmation & Invoice"
    title="Your Order Has Been Created (#{{ $order->id }})"
    ctaText="View Invoice & Upload Slip"
    :ctaUrl="url('/portal/billing')"
>
    <p>Hi {{ $notifiable->name ?? 'Valued Customer' }},</p>
    
    <p>Thank you for placing an order with <strong>STX Enterprises</strong>! Your commercial invoice <strong>{{ $invoice->invoice_number }}</strong> has been generated and is ready for payment.</p>

    <div style="background-color: #f7f8fa; border: 1px solid #eef1f4; padding: 18px 20px; border-radius: 4px; margin: 20px 0; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.8;">
        <div><strong>Invoice Reference:</strong> {{ $invoice->invoice_number }}</div>
        <div><strong>Total Amount:</strong> <span style="color: #146a8a; font-weight: 700; font-size: 14px;">{{ $amountFormatted }}</span></div>
        <div><strong>Issue Date:</strong> {{ $invoice->created_at->format('d M Y') }}</div>
        <div><strong>Payment Status:</strong> <span style="color: #7146eb; font-weight: 700;">Awaiting Slip Upload</span></div>
    </div>

    <p><strong>Bank Deposit / Transfer Instructions:</strong></p>
    <div style="background-color: #07060d; color: #ffffff; padding: 18px 20px; border-radius: 4px; margin: 16px 0; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; line-height: 1.9;">
        <div style="color: #9aa3af; text-transform: uppercase; font-size: 9.5px; letter-spacing: 0.1em; margin-bottom: 4px;">Bank Account Details</div>
        <div>Bank Name: <strong>Commercial Bank / Sampath Bank</strong></div>
        <div>Account Name: <strong>STX HOSTING (PVT) LTD (Shanudha Tirosh)</strong></div>
        <div>Account Number: <strong style="color: #20c6e0; font-size: 13px;">8012 3456 7890</strong></div>
        <div>Branch: <strong>Colombo Main Branch</strong></div>
    </div>

    <p>Once your transfer is complete, please upload your deposit receipt or transaction screenshot in your portal. Our operations team verifies receipts and provisions your server within our <strong>24 to 72 hour SLA</strong>.</p>
</x-emails.stx-layout>

