<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Commerce\MailMailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MailClientConfigController extends Controller
{
    /**
     * Generate and download Apple Mail (.mobileconfig) Configuration Profile.
     * When opened on iOS (iPhone/iPad) or macOS, native Mail app is configured in 1 tap.
     */
    public function downloadAppleProfile(Request $request, string $mailboxId): Response
    {
        $mailbox = MailMailbox::with('team')->findOrFail($mailboxId);

        // Security / Multi-tenancy authorization check
        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $team = $mailbox->team;
        if (! $team || ! $user->teams->contains($team->id)) {
            abort(403, 'Unauthorized access to this mailbox configuration.');
        }

        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));
        $email = $mailbox->address;
        $displayName = $mailbox->name ?: $email;
        $profileUuid = (string) Str::uuid();
        $payloadUuid = (string) Str::uuid();
        $cleanAddress = preg_replace('/[^a-zA-Z0-9._-]/', '_', $email);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadDisplayName</key>
    <string>{$displayName} - Business Email</string>
    <key>PayloadDescription</key>
    <string>Configures Apple Mail for {$email} on {$mailHost}</string>
    <key>PayloadIdentifier</key>
    <string>com.stx.mail.profile.{$mailbox->id}</string>
    <key>PayloadOrganization</key>
    <string>STX Hosting</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>{$profileUuid}</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>PayloadType</key>
            <string>com.apple.mail.managed</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
            <key>PayloadIdentifier</key>
            <string>com.stx.mail.account.{$mailbox->id}</string>
            <key>PayloadUUID</key>
            <string>{$payloadUuid}</string>
            <key>PayloadDisplayName</key>
            <string>{$email}</string>
            <key>EmailAccountDescription</key>
            <string>{$email}</string>
            <key>EmailAccountName</key>
            <string>{$displayName}</string>
            <key>EmailAccountType</key>
            <string>EmailAccountTypeIMAP</string>
            <key>EmailAddress</key>
            <string>{$email}</string>
            <key>IncomingMailServerAuthentication</key>
            <string>EmailAuthPassword</string>
            <key>IncomingMailServerHostName</key>
            <string>{$mailHost}</string>
            <key>IncomingMailServerPortNumber</key>
            <integer>993</integer>
            <key>IncomingMailServerUseSSL</key>
            <true/>
            <key>IncomingMailServerUsername</key>
            <string>{$email}</string>
            <key>OutgoingMailServerAuthentication</key>
            <string>EmailAuthPassword</string>
            <key>OutgoingMailServerHostName</key>
            <string>{$mailHost}</string>
            <key>OutgoingMailServerPortNumber</key>
            <integer>587</integer>
            <key>OutgoingMailServerUseSSL</key>
            <true/>
            <key>OutgoingMailServerUsername</key>
            <string>{$email}</string>
            <key>OutgoingPasswordSameAsIncoming</key>
            <true/>
        </dict>
    </array>
</dict>
</plist>
XML;

        return response($xml, 200, [
            'Content-Type' => 'application/x-apple-aspen-config; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$cleanAddress}.mobileconfig\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Mozilla Thunderbird Autoconfig XML Endpoint.
     * Accessible via GET /mail/config-v1.1.xml or /.well-known/autoconfig/mail/config-v1.1.xml
     */
    public function thunderbirdAutoconfig(Request $request): Response
    {
        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));
        $requestedEmail = $request->query('emailaddress', '');
        $domain = str_contains($requestedEmail, '@') ? explode('@', $requestedEmail)[1] : 'ourdomain.com';

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<clientConfig version="1.1">
    <emailProvider id="stx-business-mail">
        <domain>{$domain}</domain>
        <displayName>Business Email</displayName>
        <displayShortName>Email</displayShortName>
        <incomingServer type="imap">
            <hostname>{$mailHost}</hostname>
            <port>993</port>
            <socketType>SSL</socketType>
            <authentication>password-cleartext</authentication>
            <username>%EMAILADDRESS%</username>
        </incomingServer>
        <outgoingServer type="smtp">
            <hostname>{$mailHost}</hostname>
            <port>587</port>
            <socketType>STARTTLS</socketType>
            <authentication>password-cleartext</authentication>
            <username>%EMAILADDRESS%</username>
        </outgoingServer>
    </emailProvider>
</clientConfig>
XML;

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    /**
     * Microsoft Outlook Autodiscover XML Endpoint.
     * Accessible via POST /autodiscover/autodiscover.xml or GET /autodiscover/autodiscover.xml
     */
    public function outlookAutodiscover(Request $request): Response
    {
        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));

        $inputXml = $request->getContent();
        $email = 'user@ourdomain.com';
        if (preg_match('/<EMailAddress>(.*?)<\/EMailAddress>/i', $inputXml, $matches)) {
            $email = trim($matches[1]);
        } elseif ($request->query('email')) {
            $email = trim((string) $request->query('email'));
        }

        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
    <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a">
        <Account>
            <AccountType>email</AccountType>
            <Action>settings</Action>
            <Protocol>
                <Type>IMAP</Type>
                <Server>{$mailHost}</Server>
                <Port>993</Port>
                <DomainRequired>off</DomainRequired>
                <LoginName>{$email}</LoginName>
                <SPA>off</SPA>
                <SSL>on</SSL>
                <AuthRequired>on</AuthRequired>
            </Protocol>
            <Protocol>
                <Type>SMTP</Type>
                <Server>{$mailHost}</Server>
                <Port>587</Port>
                <DomainRequired>off</DomainRequired>
                <LoginName>{$email}</LoginName>
                <SPA>off</SPA>
                <Encryption>TLS</Encryption>
                <AuthRequired>on</AuthRequired>
                <UsePOPAuth>on</UsePOPAuth>
                <SMTPLast>off</SMTPLast>
            </Protocol>
        </Account>
    </Response>
</Autodiscover>
XML;

        return response($xml, 200, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);
    }
}

