<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Commerce\MailMailbox;
use App\Services\Auth\MailboxAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailClientProfileController extends Controller
{
    /**
     * Download Apple Configuration Profile (.mobileconfig) for 1-click iOS & macOS Mail setup.
     */
    public function appleProfile(
        string $mailboxId,
        Request $request,
        MailboxAuthorizationService $authService
    ): Response {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $mailbox = MailMailbox::findOrFail($mailboxId);
        $authService->authorizeOrFail($user, $mailbox, 'view');

        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com'));
        $address = $mailbox->address;
        $displayName = $mailbox->name ?: 'STX Mail';
        $uuid = $mailbox->id;

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadDescription</key>
    <string>STX Enterprises Mail Configuration for {$address}</string>
    <key>PayloadDisplayName</key>
    <string>STX Mail ({$address})</string>
    <key>PayloadIdentifier</key>
    <string>com.stxenterprises.mail.{$uuid}</string>
    <key>PayloadOrganization</key>
    <string>STX Enterprises</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>{$uuid}</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>EmailAccountDescription</key>
            <string>STX Hosting ({$address})</string>
            <key>EmailAccountName</key>
            <string>{$displayName}</string>
            <key>EmailAccountType</key>
            <string>EmailTypeIMAP</string>
            <key>EmailAddress</key>
            <string>{$address}</string>
            <key>IncomingMailServerAuthentication</key>
            <string>EmailAuthPassword</string>
            <key>IncomingMailServerHostName</key>
            <string>{$mailHost}</string>
            <key>IncomingMailServerPortNumber</key>
            <integer>993</integer>
            <key>IncomingMailServerUseSSL</key>
            <true/>
            <key>IncomingMailServerUsername</key>
            <string>{$address}</string>
            <key>OutgoingMailServerAuthentication</key>
            <string>EmailAuthPassword</string>
            <key>OutgoingMailServerHostName</key>
            <string>{$mailHost}</string>
            <key>OutgoingMailServerPortNumber</key>
            <integer>587</integer>
            <key>OutgoingMailServerUseSSL</key>
            <false/>
            <key>OutgoingMailServerUsername</key>
            <string>{$address}</string>
            <key>OutgoingPasswordSameAsIncomingPassword</key>
            <true/>
            <key>PayloadDescription</key>
            <string>Configures IMAP and SMTP accounts.</string>
            <key>PayloadDisplayName</key>
            <string>STX Email Account</string>
            <key>PayloadIdentifier</key>
            <string>com.stxenterprises.email.{$uuid}</string>
            <key>PayloadType</key>
            <string>com.apple.mail.managed</string>
            <key>PayloadUUID</key>
            <string>{$uuid}-account</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
        </dict>
    </array>
</dict>
</plist>
XML;

        $safeName = str_replace(['@', '.'], '-', $address);

        return response($xml, 200, [
            'Content-Type' => 'application/x-apple-aspen-config',
            'Content-Disposition' => "attachment; filename=\"STX-Mail-{$safeName}.mobileconfig\"",
        ]);
    }

    /**
     * Standard RFC Thunderbird autoconfig XML.
     */
    public function thunderbirdAutoconfig(Request $request): Response
    {
        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com'));

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<clientConfig version="1.1">
    <emailProvider id="stxenterprises.com">
        <domain>stxenterprises.com</domain>
        <displayName>STX Enterprises Cloud Mail</displayName>
        <displayShortName>STX Mail</displayShortName>
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
            'Content-Type' => 'application/xml',
        ]);
    }
}
