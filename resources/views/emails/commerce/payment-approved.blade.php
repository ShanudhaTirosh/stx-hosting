<x-emails.stx-layout 
    eyebrow="Payment Verification"
    title="Payment Approved & Queued for Provisioning"
    ctaText="Track Service Status"
    :ctaUrl="url('/portal/services')"
>
    <p>Hi {{ $notifiable->name ?? 'Valued Customer' }},</p>

    <p>Great news! Your payment slip for Invoice <strong>{{ $invoice->invoice_number }}</strong> has been reviewed and verified by our operations desk.</p>

    <div style="background-color: #f7f8fa; border: 1px solid #eef1f4; padding: 18px 20px; border-radius: 4px; margin: 20px 0; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.8;">
        <div><strong>Invoice Reference:</strong> {{ $invoice->invoice_number }}</div>
        <div><strong>Status:</strong> <span style="color: #27ae60; font-weight: 700;">PAID &amp; VERIFIED</span></div>
        <div><strong>Amount Paid:</strong> {{ $invoice->currency }} {{ number_format($invoice->total_cents / 100, 2) }}</div>
    </div>

    <p>Your order is now being fulfilled by our engineering staff. We are acquiring and configuring your cloud instance. You will receive a notification the moment your server goes live with your IP address and connection details.</p>

    <p>Thank you for partnering with STX Enterprises!</p>
</x-emails.stx-layout>
