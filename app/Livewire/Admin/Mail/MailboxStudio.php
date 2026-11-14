<?php

namespace App\Livewire\Admin\Mail;

use App\Models\Commerce\MailAppPassword;
use App\Models\Commerce\MailboxAuditLog;
use App\Models\Commerce\MailboxPermission;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailSystemMapping;
use App\Models\Team;
use App\Models\User;
use App\Services\Commerce\WildDuckProvisioningService;
use App\Services\RbacService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Component;

class MailboxStudio extends Component
{
    public string $activeTab = 'mailboxes'; // mailboxes, senders, permissions, audit, telemetry

    // Filter & Search
    public string $search = '';
    public string $purposeFilter = 'all'; // all, system_sender, shared_inbox, personal_mailbox

    // Modal Controls
    public bool $showCreateModal = false;
    public bool $isBulkMode = false;
    public string $createDomain = '';
    public string $createUsername = '';
    public string $createDisplayName = '';
    public string $createPassword = '';
    public ?int $createQuotaMb = 5120;
    public string $createPurpose = 'shared_inbox';
    public bool $createIsSensitive = false;
    public string $bulkInput = ''; // Multi-line: username,displayName,quotaMb,purpose

    // Settings Modal
    public bool $showSettingsModal = false;
    public ?string $editingMailboxId = null;
    public string $editDisplayName = '';
    public ?int $editQuotaMb = 5120;
    public string $editPurpose = 'shared_inbox';
    public bool $editIsSensitive = false;
    public bool $editAutoReplyEnabled = false;
    public string $editAutoReplySubject = '';
    public string $editAutoReplyBody = '';
    public ?string $editAutoReplyStart = null;
    public ?string $editAutoReplyEnd = null;
    public string $editForwardingTargets = ''; // comma or newline separated
    public string $editSignature = '';

    // Credentials & App Passwords Modal
    public bool $showCredentialsModal = false;
    public ?string $credentialsMailboxId = null;
    public ?string $credentialsAddress = null;
    public ?string $revealedPassword = null;
    public string $newAppPasswordLabel = '';
    public ?string $newAppPasswordExpiresAt = null;
    public ?string $justGeneratedAppPassword = null;
    public string $activeSnippet = 'laravel'; // laravel, python, nodejs, wordpress
    public ?string $portTestResult = null;
    public bool $isTestingPorts = false;

    // Admin Grant Modal
    public bool $showGrantModal = false;
    public ?string $grantMailboxId = null;
    public ?int $grantUserId = null;
    public string $grantLevel = 'read_only'; // read_only, read_write, send_as, manage
    public string $grantFolders = ''; // comma separated
    public bool $grantSensitiveReadOnly = true;
    public ?string $grantExpiresAt = null;

    // System Senders Mapping Tab
    public array $senderMappings = [];

    public function boot(): void
    {
        abort_unless(RbacService::isPlatformOwner(auth()->user()), 403, 'Only the platform owner can access Mailbox Studio.');
    }

    public function mount(): void
    {
        $this->loadSenderMappings();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['mailboxes', 'senders', 'permissions', 'audit', 'telemetry'], true) ? $tab : 'mailboxes';
    }

    public function loadSenderMappings(): void
    {
        $categories = ['default', 'system', 'billing', 'deployments', 'support', 'security'];
        $mappings = MailSystemMapping::all()->keyBy('email_type');

        $this->senderMappings = [];
        foreach ($categories as $cat) {
            $m = $mappings->get($cat);
            $this->senderMappings[$cat] = [
                'mailbox_id' => $m?->mailbox_id ?? '',
                'from_name' => $m?->from_name ?? config('mail.from.name', 'STX Platform'),
                'reply_to' => $m?->reply_to_address ?? '',
                'fallback_to_relay' => $m ? (bool) $m->fallback_to_relay : true,
            ];
        }
    }

    public function saveSenderMappings(): void
    {
        foreach ($this->senderMappings as $category => $data) {
            if (! empty($data['mailbox_id'])) {
                MailSystemMapping::updateOrCreate(
                    ['email_type' => $category],
                    [
                        'mailbox_id' => $data['mailbox_id'],
                        'from_name' => trim($data['from_name']),
                        'reply_to_address' => trim($data['reply_to']) ?: null,
                        'fallback_to_relay' => (bool) ($data['fallback_to_relay'] ?? true),
                    ]
                );
            } else {
                MailSystemMapping::where('email_type', $category)->delete();
            }
        }

        MailboxAuditLog::record(
            mailboxId: null,
            actorId: auth()->id(),
            action: 'sender_mappings_updated',
            details: ['categories' => array_keys($this->senderMappings)]
        );

        $this->dispatch('success', 'Sender email mappings updated successfully.');
    }

    public function openCreateModal(): void
    {
        $firstDomain = MailDomain::where('is_verified', true)->first()?->domain ?? config('mail.mail_hostname', 'stxenterprises.com');
        $this->createDomain = $firstDomain;
        $this->createUsername = '';
        $this->createDisplayName = '';
        $this->createPassword = Str::random(16).'!Aa1';
        $this->createQuotaMb = 5120;
        $this->createPurpose = 'shared_inbox';
        $this->createIsSensitive = false;
        $this->isBulkMode = false;
        $this->bulkInput = '';
        $this->showCreateModal = true;
    }

    public function saveCreateMailbox(WildDuckProvisioningService $wildduck): void
    {
        if ($this->isBulkMode) {
            $this->validate([
                'createDomain' => 'required|string',
                'bulkInput' => 'required|string',
            ]);

            $lines = explode("\n", trim($this->bulkInput));
            $createdCount = 0;

            foreach ($lines as $line) {
                $parts = array_map('trim', explode(',', $line));
                if (empty($parts[0])) {
                    continue;
                }

                $username = strtolower($parts[0]);
                $displayName = $parts[1] ?? ucfirst($username);
                $quota = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : 5120;
                $purpose = $parts[3] ?? 'shared_inbox';
                $password = Str::random(16).'!Aa1';

                try {
                    $res = $wildduck->createMailbox($this->createDomain, $username, $displayName, $password, $quota);
                    $domainModel = MailDomain::firstOrCreate(
                        ['domain' => $this->createDomain],
                        ['team_id' => 0, 'is_verified' => true]
                    );

                    $mailbox = MailMailbox::create([
                        'domain_id' => $domainModel->id,
                        'team_id' => 0,
                        'wildduck_user_id' => $res['wildduck_user_id'],
                        'address' => $res['address'],
                        'name' => $displayName,
                        'quota_mb' => $quota,
                        'purpose' => $purpose,
                        'is_platform_managed' => true,
                        'is_active' => true,
                    ]);
                    $mailbox->setPasswordEncrypted($password)->save();

                    MailboxAuditLog::record($mailbox->id, auth()->id(), 'mailbox_created', null, ['address' => $mailbox->address, 'bulk' => true]);
                    $createdCount++;
                } catch (\Throwable) {}
            }

            $this->showCreateModal = false;
            $this->dispatch('success', "Bulk creation complete: {$createdCount} mailboxes created.");
            return;
        }

        $this->validate([
            'createDomain' => 'required|string',
            'createUsername' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/',
            'createDisplayName' => 'required|string|max:100',
            'createPassword' => 'required|string|min:8',
            'createQuotaMb' => 'nullable|integer|min:100',
        ]);

        try {
            $res = $wildduck->createMailbox(
                $this->createDomain,
                $this->createUsername,
                $this->createDisplayName,
                $this->createPassword,
                $this->createQuotaMb
            );

            $domainModel = MailDomain::firstOrCreate(
                ['domain' => $this->createDomain],
                ['team_id' => 0, 'is_verified' => true]
            );

            $mailbox = MailMailbox::create([
                'domain_id' => $domainModel->id,
                'team_id' => 0,
                'wildduck_user_id' => $res['wildduck_user_id'],
                'address' => $res['address'],
                'name' => $this->createDisplayName,
                'quota_mb' => $this->createQuotaMb,
                'purpose' => $this->createPurpose,
                'is_platform_managed' => true,
                'is_sensitive' => $this->createIsSensitive,
                'is_active' => true,
            ]);
            $mailbox->setPasswordEncrypted($this->createPassword)->save();

            MailboxAuditLog::record($mailbox->id, auth()->id(), 'mailbox_created', null, ['address' => $mailbox->address]);

            $this->showCreateModal = false;
            $this->dispatch('success', "Mailbox {$mailbox->address} created successfully!");
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Could not create mailbox: '.$e->getMessage());
        }
    }

    public function openSettingsModal(string $mailboxId): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $this->editingMailboxId = $mailbox->id;
        $this->editDisplayName = $mailbox->name;
        $this->editQuotaMb = $mailbox->quota_mb;
        $this->editPurpose = $mailbox->purpose ?? 'shared_inbox';
        $this->editIsSensitive = (bool) $mailbox->is_sensitive;
        $this->editAutoReplyEnabled = (bool) $mailbox->auto_reply_enabled;
        $this->editAutoReplySubject = (string) $mailbox->auto_reply_subject;
        $this->editAutoReplyBody = (string) $mailbox->auto_reply_body;
        $this->editAutoReplyStart = $mailbox->auto_reply_start_at?->format('Y-m-d\TH:i');
        $this->editAutoReplyEnd = $mailbox->auto_reply_end_at?->format('Y-m-d\TH:i');
        $this->editForwardingTargets = implode("\n", (array) ($mailbox->forwarding_targets ?? []));
        $this->editSignature = (string) $mailbox->signature;

        $this->showSettingsModal = true;
    }

    public function saveSettings(WildDuckProvisioningService $wildduck): void
    {
        $mailbox = MailMailbox::findOrFail($this->editingMailboxId);

        $targets = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $this->editForwardingTargets))));

        $mailbox->update([
            'name' => $this->editDisplayName,
            'quota_mb' => $this->editQuotaMb,
            'purpose' => $this->editPurpose,
            'is_sensitive' => $this->editIsSensitive,
            'auto_reply_enabled' => $this->editAutoReplyEnabled,
            'auto_reply_subject' => $this->editAutoReplySubject ?: null,
            'auto_reply_body' => $this->editAutoReplyBody ?: null,
            'auto_reply_start_at' => $this->editAutoReplyStart ? Carbon::parse($this->editAutoReplyStart) : null,
            'auto_reply_end_at' => $this->editAutoReplyEnd ? Carbon::parse($this->editAutoReplyEnd) : null,
            'forwarding_targets' => $targets,
            'signature' => $this->editSignature ?: null,
        ]);

        // Sync with WildDuck
        if ($this->editQuotaMb) {
            $wildduck->updateMailboxQuota($mailbox->wildduck_user_id, $this->editQuotaMb);
        }
        $wildduck->updateAutoReply(
            $mailbox->wildduck_user_id,
            $this->editAutoReplyEnabled,
            $this->editAutoReplySubject,
            $this->editAutoReplyBody,
            $mailbox->auto_reply_start_at,
            $mailbox->auto_reply_end_at
        );
        $wildduck->updateForwarding($mailbox->wildduck_user_id, $targets);

        MailboxAuditLog::record($mailbox->id, auth()->id(), 'mailbox_updated', null, ['changes' => 'settings']);

        $this->showSettingsModal = false;
        $this->dispatch('success', "Settings for {$mailbox->address} updated successfully.");
    }

    public function toggleMailboxStatus(string $mailboxId, WildDuckProvisioningService $wildduck): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $newStatus = ! $mailbox->is_active;

        $mailbox->update([
            'is_active' => $newStatus,
            'suspended_at' => $newStatus ? null : now(),
            'suspension_reason' => $newStatus ? null : 'Manually suspended by owner',
        ]);

        $wildduck->updateMailboxActive($mailbox->wildduck_user_id, $newStatus);

        MailboxAuditLog::record($mailbox->id, auth()->id(), $newStatus ? 'mailbox_resumed' : 'mailbox_suspended');
        $this->dispatch('success', "Mailbox {$mailbox->address} is now ".($newStatus ? 'active' : 'suspended').".");
    }

    public function deleteMailbox(string $mailboxId, WildDuckProvisioningService $wildduck): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $address = $mailbox->address;

        $wildduck->deleteMailbox($mailbox->wildduck_user_id);
        $mailbox->delete();

        MailboxAuditLog::record(null, auth()->id(), 'mailbox_deleted', null, ['deleted_address' => $address]);
        $this->dispatch('success', "Mailbox {$address} deleted permanently.");
    }

    public function openCredentialsModal(string $mailboxId): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $this->credentialsMailboxId = $mailbox->id;
        $this->credentialsAddress = $mailbox->address;
        $this->revealedPassword = $mailbox->getDecryptedPassword();
        $this->newAppPasswordLabel = '';
        $this->newAppPasswordExpiresAt = null;
        $this->justGeneratedAppPassword = null;
        $this->portTestResult = null;
        $this->showCredentialsModal = true;

        MailboxAuditLog::record($mailbox->id, auth()->id(), 'credentials_viewed');
    }

    public function generateAppPassword(): void
    {
        $this->validate([
            'newAppPasswordLabel' => 'required|string|max:100',
        ]);

        $mailbox = MailMailbox::findOrFail($this->credentialsMailboxId);
        $expires = $this->newAppPasswordExpiresAt ? Carbon::parse($this->newAppPasswordExpiresAt) : null;

        $res = MailAppPassword::generate($mailbox, auth()->user(), $this->newAppPasswordLabel, $expires);
        $this->justGeneratedAppPassword = $res['plain_password'];
        $this->newAppPasswordLabel = '';

        MailboxAuditLog::record($mailbox->id, auth()->id(), 'app_password_generated', null, ['label' => $res['model']->label]);
        $this->dispatch('success', 'App-Specific Password generated successfully!');
    }

    public function revokeAppPassword(int $appPasswordId): void
    {
        $appPass = MailAppPassword::findOrFail($appPasswordId);
        $appPass->update(['is_revoked' => true]);

        MailboxAuditLog::record($appPass->mailbox_id, auth()->id(), 'app_password_revoked', null, ['label' => $appPass->label]);
        $this->dispatch('success', "App password '{$appPass->label}' revoked.");
    }

    public function testPorts(): void
    {
        $this->isTestingPorts = true;
        $host = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com'));

        $imapOk = false;
        $smtpOk = false;

        $s1 = @fsockopen("ssl://{$host}", 993, $errno, $errstr, 2);
        if ($s1) {
            $imapOk = true;
            fclose($s1);
        }

        $s2 = @fsockopen($host, 587, $errno, $errstr, 2);
        if ($s2) {
            $smtpOk = true;
            fclose($s2);
        }

        if ($imapOk && $smtpOk) {
            $this->portTestResult = "Ports Verified: IMAP (993 SSL) and SMTP (587 STARTTLS) are active and accepting connections.";
            $this->dispatch('success', 'Mail ports verified online.');
        } else {
            $this->portTestResult = "Ports: IMAP 993 is ".($imapOk ? 'Online' : 'Standby').", SMTP 587 is ".($smtpOk ? 'Online' : 'Standby').".";
            $this->dispatch('warning', 'Port check completed.');
        }

        $this->isTestingPorts = false;
    }

    public function openGrantModal(string $mailboxId, ?int $userId = null): void
    {
        $mailbox = MailMailbox::findOrFail($mailboxId);
        $this->grantMailboxId = $mailbox->id;
        $this->grantUserId = $userId;
        $this->grantLevel = 'read_only';
        $this->grantFolders = '';
        $this->grantSensitiveReadOnly = true;
        $this->grantExpiresAt = null;

        if ($userId) {
            $existing = MailboxPermission::where('mailbox_id', $mailbox->id)->where('user_id', $userId)->first();
            if ($existing) {
                $this->grantLevel = $existing->permission_level;
                $this->grantFolders = implode(', ', (array) ($existing->allowed_folders ?? []));
                $this->grantSensitiveReadOnly = (bool) $existing->is_sensitive_read_only;
                $this->grantExpiresAt = $existing->expires_at?->format('Y-m-d');
            }
        }

        $this->showGrantModal = true;
    }

    public function saveGrant(): void
    {
        $this->validate([
            'grantMailboxId' => 'required',
            'grantUserId' => 'required|exists:users,id',
            'grantLevel' => 'required|in:read_only,read_write,send_as,manage',
        ]);

        $folders = array_values(array_filter(array_map('trim', explode(',', $this->grantFolders))));

        MailboxPermission::updateOrCreate(
            ['mailbox_id' => $this->grantMailboxId, 'user_id' => $this->grantUserId],
            [
                'permission_level' => $this->grantLevel,
                'allowed_folders' => $folders ?: null,
                'is_sensitive_read_only' => $this->grantSensitiveReadOnly,
                'granted_by' => auth()->id(),
                'expires_at' => $this->grantExpiresAt ? Carbon::parse($this->grantExpiresAt) : null,
            ]
        );

        MailboxAuditLog::record(
            mailboxId: $this->grantMailboxId,
            actorId: auth()->id(),
            action: 'permission_granted',
            targetUserId: $this->grantUserId,
            details: ['level' => $this->grantLevel]
        );

        $this->showGrantModal = false;
        $this->dispatch('success', 'Admin mailbox permissions granted.');
    }

    public function revokeGrant(string $mailboxId, int $userId): void
    {
        MailboxPermission::where('mailbox_id', $mailboxId)->where('user_id', $userId)->delete();

        MailboxAuditLog::record(
            mailboxId: $mailboxId,
            actorId: auth()->id(),
            action: 'permission_revoked',
            targetUserId: $userId
        );

        $this->dispatch('success', 'Admin permission revoked.');
    }

    public function render(WildDuckProvisioningService $wildduck)
    {
        // 1. Mailboxes Query
        $query = MailMailbox::with(['domain', 'permissions.user', 'appPasswords'])
            ->where('is_platform_managed', true)
            ->latest();

        if ($this->search) {
            $term = '%'.strtolower(trim($this->search)).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(address) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
            });
        }

        if ($this->purposeFilter !== 'all') {
            $query->where('purpose', $this->purposeFilter);
        }

        $mailboxes = $query->get();

        // 2. Platform Admins
        $admins = User::whereHas('teams', function ($q) {
            $q->where('teams.id', 0)->whereIn('team_user.role', ['owner', 'admin']);
        })->get();

        // 3. Audit Logs
        $auditLogs = MailboxAuditLog::with(['actor', 'targetUser', 'mailbox'])->latest('created_at')->take(40)->get();

        // 4. Fleet Telemetry
        $fleetStats = $wildduck->getFleetStats();

        // Active App Passwords for current modal
        $currentAppPasswords = collect();
        if ($this->credentialsMailboxId) {
            $currentAppPasswords = MailAppPassword::where('mailbox_id', $this->credentialsMailboxId)->latest()->get();
        }

        $allMailboxesForMapping = MailMailbox::where('is_platform_managed', true)->where('is_active', true)->get();

        return view('livewire.admin.mail.mailbox-studio', [
            'mailboxes' => $mailboxes,
            'admins' => $admins,
            'auditLogs' => $auditLogs,
            'fleetStats' => $fleetStats,
            'currentAppPasswords' => $currentAppPasswords,
            'allMailboxesForMapping' => $allMailboxesForMapping,
        ])->layout('layouts.app', [
            'title' => 'Mailbox Studio — STX Platform Mail',
        ]);
    }
}
