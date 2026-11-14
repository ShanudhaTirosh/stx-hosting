<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commerce\MailboxAuditLog;
use App\Models\Commerce\MailMailbox;
use App\Services\Auth\MailboxAuthorizationService;
use App\Services\Commerce\WildDuckProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MailboxSsoController extends Controller
{
    /**
     * Launch single-sign-on (SSO) session into Webmail without exposing the password.
     */
    public function launch(
        string $id,
        Request $request,
        MailboxAuthorizationService $authService,
        WildDuckProvisioningService $wildduck
    ): RedirectResponse {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $mailbox = MailMailbox::findOrFail($id);

        // 1. Authorize access
        $authService->authorizeOrFail($user, $mailbox, 'view');

        // 2. Fetch or issue single-use token from WildDuck
        $decryptedPassword = $mailbox->getDecryptedPassword() ?: 'secret';
        $tokenData = $wildduck->createUserToken($mailbox->wildduck_user_id, $mailbox->address, $decryptedPassword);

        // 3. Record audit trail
        MailboxAuditLog::record(
            mailboxId: $mailbox->id,
            actorId: $user->id,
            action: 'webmail_opened',
            details: [
                'address' => $mailbox->address,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        // 4. Redirect to Webmail SSO receiver
        $webmailUrl = rtrim((string) env('WEBMAIL_URL', 'https://webmail.'.config('mail.mail_hostname', 'stxenterprises.com')), '/');

        return redirect()->away("{$webmailUrl}/auth/sso?token={$tokenData['token']}&address=".urlencode($mailbox->address));
    }
}
