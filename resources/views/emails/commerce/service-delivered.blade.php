<x-emails.stx-layout 
    eyebrow="Service Activation"
    title="Your Cloud VPS Is Live &amp; Ready"
    ctaText="Access Portal &amp; View Root Password"
    :ctaUrl="url('/portal/services/' . $service->uuid)"
>
    <p>Hi {{ $notifiable->name ?? 'Valued Customer' }},</p>

    <p>We are pleased to inform you that your cloud VPS instance (<strong>{{ $service->hostname }}</strong>) has been provisioned and is fully operational!</p>

    <div style="background-color: #07060d; color: #ffffff; padding: 20px 22px; border-radius: 4px; margin: 20px 0; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.9;">
        <div style="color: #20c6e0; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; font-size: 10px; letter-spacing: 0.12em;">Server Access Credentials</div>
        <div>IPv4 Address: <strong style="color: #20c6e0;">{{ $service->ip_address }}</strong></div>
        @if (!empty($service->network_config['ipv6']))
            <div>IPv6 Address: <strong>{{ $service->network_config['ipv6'] }}</strong></div>
        @endif
        <div>SSH Port: <strong>{{ $sshPort }}</strong></div>
        <div>Operating System: <strong>{{ $service->os_image }}</strong></div>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #2a2e37;">
            <span style="color: #9aa3af;">SSH Command:</span><br>
            <code style="background-color: #1b1e24; color: #20c6e0; padding: 4px 8px; border-radius: 2px; display: inline-block; margin-top: 4px;">ssh root@{{ $service->ip_address }}{{ ($sshPort != 22) ? " -p {$sshPort}" : "" }}</code>
        </div>
    </div>

    <p><strong>Security Notice:</strong> Your temporary root password is encrypted at rest. To reveal and copy your password, visit your Customer Portal below and confirm your account password.</p>

    <p>If you need assistance configuring your server, setting up reverse DNS (PTR), or installing applications, our support desk is standing by 24/7.</p>
</x-emails.stx-layout>

