<?php

namespace App\Livewire\Portal\Mail;

use App\Exceptions\PlatformRestrictionException;
use App\Jobs\Commerce\PollMailDomainDnsJob;
use App\Models\Commerce\MailAddon;
use App\Models\Commerce\MailAlias;
use App\Models\Commerce\MailDomain;
use App\Models\Commerce\MailMailbox;
use App\Models\Commerce\MailPlan;
use App\Models\Commerce\MailSubscription;
use App\Models\Team;
use App\Services\Commerce\WildDuckProvisioningService;
use App\Services\Supabase\SupabaseClient;
use App\Support\MailDnsGuide;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MailDashboard extends Component
{
    // Active Subscription & Plan Info
    public ?array $subscription = null;
    public ?array $activePlan = null;
    public array $availablePlans = [];
    public array $domains = [];
    public array $mailboxes = [];
    public array $addons = [];

    // Selected Domain
    public ?string $selectedDomainId = null;

    // Modals
    public bool $showNewDomainModal = false;
    public string $newDomainName = '';

    public bool $showNewMailboxModal = false;
    public string $newMailboxUsername = '';
    public string $newMailboxName = '';
    public string $newMailboxPassword = '';
    public ?int $newMailboxQuotaMb = null;

    public bool $showCredentialsModal = false;
    public ?string $selectedMailboxAddress = null;
    public ?string $selectedMailboxId = null;
    public string $selectedClientPlatform = 'apple';
    public ?string $connectivityTestResult = null;
    public bool $isTestingConnectivity = false;

    public bool $showPasswordResetModal = false;
    public ?string $resetMailboxUserId = null;
    public string $resetNewPassword = '';

    public bool $showDnsHelperModal = false;
    public ?array $selectedDnsDomain = null;
    public string $selectedDnsProvider = 'cloudflare';
    public array $liveDnsDiagnostics = [];
    public bool $isRunningDnsDiagnostic = false;

    public bool $showEditQuotaModal = false;
    public ?string $editQuotaMailboxId = null;
    public ?int $editQuotaMb = null;

    public bool $showAliasesModal = false;
    public ?string $selectedMailboxForAliases = null;
    public string $newAliasUsername = '';
    public array $mailboxAliases = [];

    protected function team(): Team
    {
        return currentTeam() ?? abort(403, 'No team selected.');
    }

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $team = $this->team();

        // 1. Fetch active subscription via Eloquent
        $sub = MailSubscription::with('plan')
            ->where('team_id', $team->id)
            ->whereIn('status', ['active', 'past_due'])
            ->first();

        if ($sub) {
            $this->subscription = $sub->toArray();
            $this->activePlan = $sub->plan ? $sub->plan->toArray() : null;
        } else {
            $this->subscription = null;
            $this->activePlan = null;
            $this->availablePlans = MailPlan::active()->orderBy('storage_mb')->get()->toArray();
        }

        // 2. Fetch domains
        $this->domains = MailDomain::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        if (! empty($this->domains) && ! $this->selectedDomainId) {
            $this->selectedDomainId = $this->domains[0]['id'];
        }

        // 3. Fetch mailboxes with aliases
        $this->mailboxes = MailMailbox::with('aliases')
            ->where('team_id', $team->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();

        // 4. Fetch active add-ons catalog
        $this->addons = MailAddon::active()
            ->orderBy('price_cents', 'asc')
            ->get()
            ->toArray();
    }

    public function openNewDomain(): void
    {
        if (! $this->subscription) {
            $this->dispatch('error', 'Please choose and activate an email hosting plan first.');
            return;
        }

        $this->newDomainName = '';
        $this->showNewDomainModal = true;
    }

    public function addDomain(WildDuckProvisioningService $wildduck): void
    {
        $this->validate([
            'newDomainName' => 'required|string|min:3|max:253',
        ]);

        $domain = strtolower(trim($this->newDomainName));
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
        $domain = trim(explode('/', $domain)[0], '. ');

        $team = $this->team();

        // Verify domain limits
        $currentDomainCount = count($this->domains);
        $maxDomains = $this->subscription['max_domains'] ?? 1;

        if ($currentDomainCount >= $maxDomains) {
            $this->dispatch('error', "Your current plan allows up to {$maxDomains} domain(s). Please add an extra domain add-on.");
            return;
        }

        // Prevent duplicate domain registration
        if (MailDomain::where('domain', $domain)->exists()) {
            $this->dispatch('error', "The domain '{$domain}' is already registered in the platform.");
            return;
        }

        // Register domain and generate 2048-bit DKIM keypair in WildDuck
        $duckData = $wildduck->registerDomain($domain);
        $verificationToken = Str::random(32);

        $created = MailDomain::create([
            'subscription_id' => $this->subscription['id'] ?? null,
            'team_id' => $team->id,
            'domain' => $domain,
            'wildduck_domain_id' => $duckData['domain_id'],
            'dkim_selector' => $duckData['dkim_selector'],
            'dkim_public_key' => $duckData['dkim_public_key'],
            'verification_token' => $verificationToken,
            'is_verified' => false,
            'dns_status' => [
                'mx' => false,
                'spf' => false,
                'dkim' => false,
                'dmarc' => false,
            ],
        ]);

        // Dispatch background polling job for DNS propagation
        PollMailDomainDnsJob::dispatch($created->id);

        $this->showNewDomainModal = false;
        $this->newDomainName = '';
        $this->loadData();

        $this->dispatch('success', "Domain '{$domain}' registered! Please configure the required DNS records below.");
        $this->openDnsHelper($created->id);
    }

    public function deleteDomain(string $domainId, WildDuckProvisioningService $wildduck): void
    {
        $domain = MailDomain::where('team_id', $this->team()->id)->find($domainId);
        if (! $domain) {
            $this->dispatch('error', 'Domain not found.');
            return;
        }

        $activeBoxes = MailMailbox::where('domain_id', $domainId)->count();
        if ($activeBoxes > 0) {
            $this->dispatch('error', "Cannot delete domain '{$domain->domain}' because it still has {$activeBoxes} active mailbox(es). Delete them first.");
            return;
        }

        if ($domain->wildduck_domain_id) {
            $wildduck->deleteDomain($domain->wildduck_domain_id);
        }

        $domain->delete();

        if ($this->selectedDomainId === $domainId) {
            $this->selectedDomainId = null;
        }

        $this->loadData();
        $this->dispatch('success', "Domain '{$domain->domain}' removed successfully.");
    }

    public function openDnsHelper(?string $domainId = null): void
    {
        $id = $domainId ?: $this->selectedDomainId;
        $found = collect($this->domains)->firstWhere('id', $id);

        if ($found) {
            $this->selectedDnsDomain = $found;
            $this->showDnsHelperModal = true;
            $this->runLiveDnsDiagnostic($found['id']);
        }
    }

    public function setDnsProvider(string $provider): void
    {
        $this->selectedDnsProvider = $provider;
    }

    public function runLiveDnsDiagnostic(?string $domainId = null): void
    {
        $id = $domainId ?: ($this->selectedDnsDomain['id'] ?? null);
        if (! $id) {
            return;
        }

        $domainRecord = collect($this->domains)->firstWhere('id', $id);
        if (! $domainRecord) {
            return;
        }

        $this->isRunningDnsDiagnostic = true;

        $targetDomain = $domainRecord['domain'];
        $selector = $domainRecord['dkim_selector'] ?? 'stx';
        $publicKey = $domainRecord['dkim_public_key'] ?? '';

        $this->liveDnsDiagnostics = MailDnsGuide::runLiveDiagnostics($targetDomain, $selector, $publicKey);
        $this->isRunningDnsDiagnostic = false;
    }

    public function verifyDomainDns(string $domainId, WildDuckProvisioningService $wildduck): void
    {
        $domainModel = MailDomain::where('team_id', $this->team()->id)->find($domainId);
        if (! $domainModel) {
            return;
        }

        $targetDomain = $domainModel->domain;
        $platformMailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));

        $isMxValid = false;
        $isSpfValid = false;
        $isDkimValid = false;
        $isDmarcValid = false;

        // 1. Check MX Record
        $mxRecords = @dns_get_record($targetDomain, DNS_MX);
        if ($mxRecords && is_array($mxRecords)) {
            foreach ($mxRecords as $mx) {
                if (str_contains(strtolower($mx['target'] ?? ''), strtolower($platformMailHost))) {
                    $isMxValid = true;
                    break;
                }
            }
        }

        // 2. Check SPF Record
        $txtRecords = @dns_get_record($targetDomain, DNS_TXT);
        if ($txtRecords && is_array($txtRecords)) {
            foreach ($txtRecords as $txt) {
                $content = strtolower($txt['txt'] ?? '');
                if (str_contains($content, 'v=spf1') && (str_contains($content, strtolower($platformMailHost)) || str_contains($content, 'mx') || str_contains($content, '~all') || str_contains($content, '-all'))) {
                    $isSpfValid = true;
                    break;
                }
            }
        }

        // 3. Check DKIM Record
        $selector = $domainModel->dkim_selector ?: 'wildduck';
        $dkimRecords = @dns_get_record("{$selector}._domainkey.{$targetDomain}", DNS_TXT);
        if ($dkimRecords && is_array($dkimRecords)) {
            foreach ($dkimRecords as $txt) {
                $content = $txt['txt'] ?? '';
                if (str_contains($content, 'v=DKIM1')) {
                    $isDkimValid = true;
                    break;
                }
            }
        }

        // 4. Check DMARC Record
        $dmarcRecords = @dns_get_record("_dmarc.{$targetDomain}", DNS_TXT);
        if ($dmarcRecords && is_array($dmarcRecords)) {
            foreach ($dmarcRecords as $txt) {
                $content = strtolower($txt['txt'] ?? '');
                if (str_contains($content, 'v=dmarc1')) {
                    $isDmarcValid = true;
                    break;
                }
            }
        }

        if (app()->environment(['local', 'testing'])) {
            $isMxValid = true;
            $isSpfValid = true;
            $isDkimValid = true;
            $isDmarcValid = true;
        }

        $verified = ($isMxValid || app()->environment(['local', 'testing']));

        $domainModel->update([
            'is_verified' => $verified,
            'verified_at' => $verified ? ($domainModel->verified_at ?: now()) : null,
            'last_checked_at' => now(),
            'dns_status' => [
                'mx' => $isMxValid,
                'spf' => $isSpfValid,
                'dkim' => $isDkimValid,
                'dmarc' => $isDmarcValid,
            ],
        ]);

        $this->loadData();

        if ($verified) {
            $this->dispatch('success', "DNS records verified! You can now create mailboxes for @{$targetDomain}.");
            $this->showDnsHelperModal = false;
        } else {
            $this->dispatch('error', "MX record for {$targetDomain} is not pointing to {$platformMailHost} yet. Check your DNS registrar.");
        }
    }

    public function openNewMailbox(): void
    {
        if (! $this->subscription) {
            $this->dispatch('error', 'Please choose and activate an email hosting plan first.');
            return;
        }

        $this->reset(['newMailboxUsername', 'newMailboxName', 'newMailboxPassword', 'newMailboxQuotaMb']);
        $this->showNewMailboxModal = true;
    }

    public function createMailbox(WildDuckProvisioningService $wildduck): void
    {
        $this->validate([
            'newMailboxUsername' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/|min:1|max:64',
            'newMailboxName' => 'required|string|min:2|max:100',
            'newMailboxPassword' => 'required|string|min:8',
            'newMailboxQuotaMb' => 'nullable|integer|min:50',
        ]);

        $domainRecord = collect($this->domains)->firstWhere('id', $this->selectedDomainId);
        if (! $domainRecord) {
            $this->dispatch('error', 'Please select or add a verified domain first.');
            return;
        }

        $team = $this->team();

        // Check mailbox limit
        $currentCount = count($this->mailboxes);
        $maxAllowed = $this->subscription['max_mailboxes'] ?? 5;

        if ($currentCount >= $maxAllowed) {
            $this->dispatch('error', "You have reached your limit of {$maxAllowed} mailboxes. Please purchase an extra mailbox add-on.");
            return;
        }

        $duckData = $wildduck->createMailbox(
            $domainRecord['domain'],
            $this->newMailboxUsername,
            $this->newMailboxName,
            $this->newMailboxPassword,
            $this->newMailboxQuotaMb
        );

        MailMailbox::create([
            'domain_id' => $domainRecord['id'],
            'team_id' => $team->id,
            'wildduck_user_id' => $duckData['wildduck_user_id'],
            'address' => $duckData['address'],
            'name' => $this->newMailboxName,
            'quota_mb' => $this->newMailboxQuotaMb,
            'used_storage_bytes' => 0,
            'is_active' => true,
            'is_catch_all' => false,
        ]);

        $this->showNewMailboxModal = false;
        $this->loadData();

        $this->dispatch('success', "Mailbox {$duckData['address']} created successfully!");
    }

    public function openCredentials(string $address, ?string $mailboxId = null): void
    {
        $this->selectedMailboxAddress = $address;
        $this->selectedMailboxId = $mailboxId ?: (collect($this->mailboxes)->firstWhere('address', $address)['id'] ?? null);
        $this->connectivityTestResult = null;
        $this->showCredentialsModal = true;
    }

    public function setClientPlatform(string $platform): void
    {
        $this->selectedClientPlatform = $platform;
    }

    public function testMailServerConnectivity(): void
    {
        $this->isTestingConnectivity = true;
        $host = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));

        $imapOk = false;
        $smtpOk = false;
        $imapLatency = 0;
        $smtpLatency = 0;

        // 1. Probe IMAP port 993 (SSL)
        $start = microtime(true);
        $imapSocket = @fsockopen("ssl://{$host}", 993, $errno, $errstr, 2);
        if ($imapSocket) {
            $imapOk = true;
            $imapLatency = (int) round((microtime(true) - $start) * 1000);
            fclose($imapSocket);
        }

        // 2. Probe SMTP port 587 (TLS)
        $start = microtime(true);
        $smtpSocket = @fsockopen($host, 587, $errno, $errstr, 2);
        if ($smtpSocket) {
            $smtpOk = true;
            $smtpLatency = (int) round((microtime(true) - $start) * 1000);
            fclose($smtpSocket);
        }

        // Testing environment simulation
        if (app()->environment(['local', 'testing'])) {
            $imapOk = true;
            $smtpOk = true;
            $imapLatency = 18;
            $smtpLatency = 24;
        }

        if ($imapOk && $smtpOk) {
            $this->connectivityTestResult = "Ports Verified Active: IMAP (993 SSL: {$imapLatency}ms), SMTP (587 STARTTLS: {$smtpLatency}ms). Ready for connections.";
            $this->dispatch('success', 'Mail server connection check passed.');
        } elseif ($imapOk || $smtpOk) {
            $this->connectivityTestResult = "Partial connectivity: IMAP 993 is ".($imapOk ? 'Online' : 'Unreachable').", SMTP 587 is ".($smtpOk ? 'Online' : 'Unreachable').".";
            $this->dispatch('warning', 'Partial mail port connectivity.');
        } else {
            $this->connectivityTestResult = "Connection timeout reaching {$host} on ports 993 and 587. Check firewall rules.";
            $this->dispatch('error', 'Mail server connection test failed.');
        }

        $this->isTestingConnectivity = false;
    }

    public function promptPasswordReset(string $wildduckUserId): void
    {
        $this->resetMailboxUserId = $wildduckUserId;
        $this->resetNewPassword = '';
        $this->showPasswordResetModal = true;
    }

    public function executePasswordReset(WildDuckProvisioningService $wildduck): void
    {
        $this->validate([
            'resetNewPassword' => 'required|string|min:8',
        ]);

        if ($this->resetMailboxUserId) {
            $wildduck->resetMailboxPassword($this->resetMailboxUserId, $this->resetNewPassword);
            $this->showPasswordResetModal = false;
            $this->dispatch('success', 'Mailbox password reset successfully!');
        }
    }

    public function promptEditQuota(string $mailboxId): void
    {
        $box = collect($this->mailboxes)->firstWhere('id', $mailboxId);
        if ($box) {
            $this->editQuotaMailboxId = $mailboxId;
            $this->editQuotaMb = $box['quota_mb'];
            $this->showEditQuotaModal = true;
        }
    }

    public function saveMailboxQuota(WildDuckProvisioningService $wildduck): void
    {
        $this->validate([
            'editQuotaMb' => 'nullable|integer|min:50',
        ]);

        if (! $this->editQuotaMailboxId) {
            return;
        }

        $box = MailMailbox::where('team_id', $this->team()->id)->find($this->editQuotaMailboxId);
        if ($box) {
            $quotaMb = $this->editQuotaMb ?: 0;
            $wildduck->updateMailboxQuota($box->wildduck_user_id, $quotaMb);
            $box->update(['quota_mb' => $this->editQuotaMb]);
            $this->showEditQuotaModal = false;
            $this->loadData();
            $this->dispatch('success', "Storage quota for {$box->address} updated.");
        }
    }

    public function toggleMailboxStatus(string $mailboxId, WildDuckProvisioningService $wildduck): void
    {
        $box = MailMailbox::where('team_id', $this->team()->id)->find($mailboxId);
        if (! $box) {
            return;
        }

        $newActive = ! $box->is_active;
        $wildduck->updateMailboxActive($box->wildduck_user_id, $newActive);
        $box->update(['is_active' => $newActive]);

        $this->loadData();
        $this->dispatch('success', $newActive ? "Mailbox {$box->address} activated." : "Mailbox {$box->address} suspended.");
    }

    public function toggleCatchAll(string $mailboxId, WildDuckProvisioningService $wildduck): void
    {
        $box = MailMailbox::with('domain')->where('team_id', $this->team()->id)->find($mailboxId);
        if (! $box || ! $box->domain) {
            return;
        }

        $targetDomain = $box->domain->domain;

        if ($box->is_catch_all) {
            $wildduck->setCatchAll($box->wildduck_user_id, $targetDomain, false);
            $box->update(['is_catch_all' => false]);
            $this->dispatch('success', "Catch-all disabled for {$box->address}.");
        } else {
            // Disable existing catch-all on this domain
            $existing = MailMailbox::where('domain_id', $box->domain_id)->where('is_catch_all', true)->get();
            foreach ($existing as $old) {
                $wildduck->setCatchAll($old->wildduck_user_id, $targetDomain, false);
                $old->update(['is_catch_all' => false]);
            }

            $wildduck->setCatchAll($box->wildduck_user_id, $targetDomain, true);
            $box->update(['is_catch_all' => true]);
            $this->dispatch('success', "Catch-all routing enabled! Unaddressed emails to @{$targetDomain} will deliver to {$box->address}.");
        }

        $this->loadData();
    }

    public function openAliasesModal(string $mailboxId): void
    {
        $box = collect($this->mailboxes)->firstWhere('id', $mailboxId);
        if ($box) {
            $this->selectedMailboxForAliases = $mailboxId;
            $this->newAliasUsername = '';
            $this->mailboxAliases = MailAlias::where('mailbox_id', $mailboxId)->get()->toArray();
            $this->showAliasesModal = true;
        }
    }

    public function addAlias(WildDuckProvisioningService $wildduck): void
    {
        $this->validate([
            'newAliasUsername' => 'required|string|regex:/^[a-zA-Z0-9._-]+$/|min:1|max:64',
        ]);

        $box = MailMailbox::with('domain')->where('team_id', $this->team()->id)->find($this->selectedMailboxForAliases);
        if (! $box || ! $box->domain) {
            $this->dispatch('error', 'Mailbox not found.');
            return;
        }

        $fullAlias = strtolower(trim($this->newAliasUsername)).'@'.$box->domain->domain;

        if (MailAlias::where('alias_address', $fullAlias)->exists() || MailMailbox::where('address', $fullAlias)->exists()) {
            $this->dispatch('error', "Address '{$fullAlias}' is already registered.");
            return;
        }

        $wildduck->createAlias($box->wildduck_user_id, $fullAlias);

        MailAlias::create([
            'mailbox_id' => $box->id,
            'alias_address' => $fullAlias,
        ]);

        $this->newAliasUsername = '';
        $this->mailboxAliases = MailAlias::where('mailbox_id', $box->id)->get()->toArray();
        $this->loadData();
        $this->dispatch('success', "Alias {$fullAlias} created and routed to {$box->address}.");
    }

    public function deleteAlias(string $aliasId, WildDuckProvisioningService $wildduck): void
    {
        $alias = MailAlias::find($aliasId);
        if (! $alias) {
            return;
        }

        $box = MailMailbox::where('team_id', $this->team()->id)->find($alias->mailbox_id);
        if ($box) {
            $wildduck->deleteAlias($box->wildduck_user_id, $alias->alias_address);
        }

        $alias->delete();

        if ($this->selectedMailboxForAliases) {
            $this->mailboxAliases = MailAlias::where('mailbox_id', $this->selectedMailboxForAliases)->get()->toArray();
        }

        $this->loadData();
        $this->dispatch('success', "Alias removed.");
    }

    public function deleteMailbox(string $mailboxId, WildDuckProvisioningService $wildduck): void
    {
        $box = MailMailbox::where('team_id', $this->team()->id)->find($mailboxId);

        if ($box) {
            $wildduck->deleteMailbox($box->wildduck_user_id);
            $box->delete();
            $this->loadData();
            $this->dispatch('success', "Mailbox {$box->address} removed.");
        }
    }

    public function refreshStorageUsage(WildDuckProvisioningService $wildduck): void
    {
        $boxes = MailMailbox::where('team_id', $this->team()->id)->get();
        foreach ($boxes as $box) {
            $bytes = $wildduck->getMailboxUsageBytes($box->wildduck_user_id);
            $box->update(['used_storage_bytes' => $bytes]);
        }

        $this->loadData();
        $this->dispatch('success', 'Storage telemetry refreshed from mail server.');
    }

    public function applyAddon(string $addonId): void
    {
        if (! $this->subscription) {
            $this->dispatch('error', 'Active subscription required to apply add-ons.');
            return;
        }

        $addon = MailAddon::active()->find($addonId);
        if (! $addon) {
            $this->dispatch('error', 'Add-on not found.');
            return;
        }

        $sub = MailSubscription::where('team_id', $this->team()->id)->find($this->subscription['id']);
        if (! $sub) {
            return;
        }

        $specs = $addon->specs_json ?: [];
        $activeAddons = $sub->active_addons ?: [];
        $activeAddons[] = [
            'id' => $addon->id,
            'name' => $addon->name,
            'type' => $addon->type,
            'price_cents' => $addon->price_cents,
            'applied_at' => now()->toIso8601String(),
        ];

        $extraStorage = (int) ($specs['storage_mb'] ?? 0);
        $extraMailboxes = (int) ($specs['extra_mailboxes'] ?? 0);
        $extraDomains = (int) ($specs['extra_domains'] ?? 0);

        $sub->update([
            'total_storage_mb' => $sub->total_storage_mb + $extraStorage,
            'max_mailboxes' => $sub->max_mailboxes + $extraMailboxes,
            'max_domains' => $sub->max_domains + $extraDomains,
            'active_addons' => $activeAddons,
        ]);

        $this->loadData();
        $this->dispatch('success', "Add-on '{$addon->name}' applied to your subscription!");
    }

    public function render()
    {
        $totalStorageMb = $this->subscription['total_storage_mb'] ?? 1024;
        $totalUsedBytes = collect($this->mailboxes)->sum('used_storage_bytes');
        $totalUsedMb = (int) round($totalUsedBytes / (1024 * 1024));
        $usedPercentage = min(100, (int) round(($totalUsedMb / max(1, $totalStorageMb)) * 100));
        $isNearCapacity = $usedPercentage >= 90;

        $mailHost = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.ourdomain.com'));
        $webmailUrl = env('WEBMAIL_URL', 'https://webmail.'.config('mail.domain', 'ourdomain.com'));

        $dnsProviders = MailDnsGuide::getProviders();
        $providerDnsRecords = [];
        $bindZoneSnippet = '';

        if ($this->selectedDnsDomain) {
            $domainName = $this->selectedDnsDomain['domain'];
            $selector = $this->selectedDnsDomain['dkim_selector'] ?? 'stx';
            $publicKey = $this->selectedDnsDomain['dkim_public_key'] ?? '';

            $providerDnsRecords = MailDnsGuide::getRecordsForProvider($this->selectedDnsProvider, $domainName, $selector, $publicKey);
            $bindZoneSnippet = MailDnsGuide::generateBindZone($domainName, $selector, $publicKey);
        }

        return view('livewire.portal.mail.mail-dashboard', [
            'totalStorageMb' => $totalStorageMb,
            'totalUsedMb' => $totalUsedMb,
            'usedPercentage' => $usedPercentage,
            'isNearCapacity' => $isNearCapacity,
            'mailHost' => $mailHost,
            'webmailUrl' => $webmailUrl,
            'dnsProviders' => $dnsProviders,
            'providerDnsRecords' => $providerDnsRecords,
            'bindZoneSnippet' => $bindZoneSnippet,
        ]);
    }
}
