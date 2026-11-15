<?php

namespace App\Livewire\Admin\Mail;

use App\Models\Commerce\MailAppPassword;
use App\Models\Commerce\MailboxAuditLog;
use App\Models\Commerce\MailboxCollaboration;
use App\Models\Commerce\MailboxPermission;
use App\Models\Commerce\MailMailbox;
use App\Services\Auth\MailboxAuthorizationService;
use App\Services\Commerce\WildDuckProvisioningService;
use App\Services\RbacService;
use Livewire\Component;

class MyMailboxes extends Component
{
    // Credentials & App Passwords Modal
    public bool $showCredentialsModal = false;

    public ?string $selectedMailboxId = null;

    public ?string $selectedMailboxAddress = null;

    public string $newAppPasswordLabel = '';

    public ?string $justGeneratedAppPassword = null;

    public string $activeSnippet = 'laravel'; // laravel, python, nodejs, wordpress

    // 2FA Step-up Modal for Sensitive Mailboxes
    public bool $showStepUpModal = false;

    public ?string $stepUpMailboxId = null;

    public string $stepUpCode = '';

    public ?string $stepUpAction = null; // 'webmail', 'credentials'

    public function boot(): void
    {
        abort_unless(RbacService::isStaff(auth()->user()), 403, 'Only platform staff administrators can access this portal.');
    }

    public function openCredentials(string $mailboxId, MailboxAuthorizationService $authService): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $user = auth()->user();

        // Check if sensitive step-up needed
        if ($authService->requiresStepUp($mailbox, $user)) {
            $this->stepUpMailboxId = $mailbox->id;
            $this->stepUpCode = '';
            $this->stepUpAction = 'credentials';
            $this->showStepUpModal = true;

            return;
        }

        $this->selectedMailboxId = $mailbox->id;
        $this->selectedMailboxAddress = $mailbox->address;
        $this->newAppPasswordLabel = '';
        $this->justGeneratedAppPassword = null;
        $this->showCredentialsModal = true;

        MailboxAuditLog::record($mailbox->id, $user->id, 'credentials_viewed');
    }

    public function launchWebmail(string $mailboxId, MailboxAuthorizationService $authService): mixed
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $user = auth()->user();

        if ($authService->requiresStepUp($mailbox, $user)) {
            $this->stepUpMailboxId = $mailbox->id;
            $this->stepUpCode = '';
            $this->stepUpAction = 'webmail';
            $this->showStepUpModal = true;

            return null;
        }

        return redirect()->route('admin.mailboxes.webmail-sso', $mailbox->id);
    }

    public function verifyStepUp(MailboxAuthorizationService $authService): mixed
    {
        $this->validate([
            'stepUpCode' => 'required|string|size:6',
        ]);

        $mailbox = MailMailbox::findOrFail($this->stepUpMailboxId);
        $user = auth()->user();

        if ($authService->confirmStepUp($mailbox, $user, $this->stepUpCode)) {
            $this->showStepUpModal = false;
            $this->dispatch('success', '2FA confirmed.');

            if ($this->stepUpAction === 'webmail') {
                return redirect()->route('admin.mailboxes.webmail-sso', $mailbox->id);
            }

            $this->openCredentials($mailbox->id, $authService);

            return null;
        }

        $this->addError('stepUpCode', 'Invalid verification code. Please check your email.');

        return null;
    }

    public function generateAppPassword(): void
    {
        $this->validate([
            'newAppPasswordLabel' => 'required|string|max:100',
        ]);

        $mailbox = MailMailbox::findOrFail($this->selectedMailboxId);
        $user = auth()->user();

        $res = MailAppPassword::generate($mailbox, $user, $this->newAppPasswordLabel, null);
        $this->justGeneratedAppPassword = $res['plain_password'];
        $this->newAppPasswordLabel = '';

        MailboxAuditLog::record($mailbox->id, $user->id, 'app_password_generated', null, ['label' => $res['model']->label]);
        $this->dispatch('success', 'App Password created! Use this in your mail program.');
    }

    public function revokeAppPassword(int $appPasswordId): void
    {
        $appPass = MailAppPassword::where('id', $appPasswordId)->where('user_id', auth()->id())->firstOrFail();
        $appPass->update(['is_revoked' => true]);

        MailboxAuditLog::record($appPass->mailbox_id, auth()->id(), 'app_password_revoked', null, ['label' => $appPass->label]);
        $this->dispatch('success', "App password '{$appPass->label}' revoked.");
    }

    public function render(WildDuckProvisioningService $wildduck)
    {
        $user = auth()->user();

        // 1. Fetch mailboxes granted to this user
        $grantedPermissions = MailboxPermission::with('mailbox.domain')
            ->active()
            ->where('user_id', $user->id)
            ->get();

        $mailboxIds = $grantedPermissions->pluck('mailbox_id')->all();

        // If platform owner, also include all platform mailboxes
        if (RbacService::isPlatformOwner($user)) {
            $mailboxes = MailMailbox::where('is_platform_managed', true)->where('is_active', true)->get();
        } else {
            $mailboxes = MailMailbox::whereIn('id', $mailboxIds)->where('is_active', true)->get();
        }

        // 2. Fetch assigned collaborations for this admin
        $assignedThreads = MailboxCollaboration::whereIn('mailbox_id', $mailboxIds)
            ->where('assigned_to_user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('updated_at')
            ->get();

        // 3. User's active app passwords
        $userAppPasswords = collect();
        if ($this->selectedMailboxId) {
            $userAppPasswords = MailAppPassword::where('mailbox_id', $this->selectedMailboxId)
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }

        return view('livewire.admin.mail.my-mailboxes', [
            'mailboxes' => $mailboxes,
            'grantedPermissions' => $grantedPermissions->keyBy('mailbox_id'),
            'assignedThreads' => $assignedThreads,
            'userAppPasswords' => $userAppPasswords,
        ])->layout('layouts.app', [
            'title' => 'My Assigned Mailboxes — STX Staff',
        ]);
    }
}
