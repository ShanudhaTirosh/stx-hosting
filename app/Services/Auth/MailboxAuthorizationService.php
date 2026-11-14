<?php

namespace App\Services\Auth;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Commerce\MailboxAuditLog;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailboxPermission;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Support\Facades\Session;

class MailboxAuthorizationService
{
    /**
     * Check if user is authorized for an action on a mailbox.
     */
    public function authorize(User $user, MailMailbox $mailbox, string $action = 'read_only'): bool
    {
        // 1. Platform owner has unrestricted access
        if (RbacService::isPlatformOwner($user)) {
            return true;
        }

        // 2. Client team admin for customer-owned mailboxes
        if (! $mailbox->is_platform_managed && $user->isAdminOfTeam($mailbox->team_id)) {
            return true;
        }

        // 3. Platform staff permission verification
        $permission = MailboxPermission::where('mailbox_id', $mailbox->id)
            ->where('user_id', $user->id)
            ->active()
            ->first();

        if (! $permission) {
            return false;
        }

        return match ($action) {
            'read_only', 'view' => $permission->canRead(),
            'read_write', 'reply', 'compose' => $permission->canWrite(),
            'send_as', 'send' => $permission->canSend(),
            'manage', 'settings' => $permission->canManage(),
            default => false,
        };
    }

    /**
     * Check if a sensitive mailbox requires 2FA step-up confirmation before revealing details or webmail.
     */
    public function requiresStepUp(MailMailbox $mailbox, User $user): bool
    {
        if (! $mailbox->is_sensitive) {
            return false;
        }

        // Check if session has recent verified 2FA step-up within 15 minutes
        $verifiedAt = Session::get("mailbox_step_up_{$mailbox->id}");

        return empty($verifiedAt) || now()->diffInMinutes($verifiedAt) > 15;
    }

    /**
     * Confirm 2FA step-up verification for a sensitive mailbox.
     */
    public function confirmStepUp(MailMailbox $mailbox, User $user, string $code): bool
    {
        $verifier = app(TwoFactorVerificationService::class);
        $result = $verifier->verify($user, $code);

        if ($result['valid']) {
            Session::put("mailbox_step_up_{$mailbox->id}", now());

            MailboxAuditLog::record(
                mailboxId: $mailbox->id,
                actorId: $user->id,
                action: 'step_up_2fa_confirmed',
                details: ['message' => '2FA confirmed for sensitive mailbox access']
            );

            return true;
        }

        return false;
    }

    /**
     * Asserts permission and throws 403 on failure.
     */
    public function authorizeOrFail(User $user, MailMailbox $mailbox, string $action = 'read_only'): void
    {
        if (! $this->authorize($user, $mailbox, $action)) {
            abort(403, "You do not have {$action} permissions for mailbox {$mailbox->address}.");
        }

        if ($this->requiresStepUp($mailbox, $user)) {
            throw new PlatformRestrictionException("Step-up 2FA verification required to access sensitive mailbox {$mailbox->address}.");
        }
    }
}
