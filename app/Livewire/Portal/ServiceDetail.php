<?php

namespace App\Livewire\Portal;

use App\Exceptions\VpsAccessException;
use App\Models\Commerce\CommerceAuditLog;
use App\Models\Commerce\ServiceCredential;
use App\Models\Commerce\ServiceInstance;
use App\Models\Commerce\VpsAuthorizedKey;
use App\Services\Vps\VpsConnectionFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class ServiceDetail extends Component
{
    public string $uuid;

    public ?string $revealedPassword = null;

    public string $accountPassword = '';

    public bool $showPasswordModal = false;

    // Changed Password Recovery Form
    public bool $showUpdatePasswordModal = false;

    public string $newRootPassword = '';

    public string $confirmNewRootPassword = '';

    // Authorized SSH Keys Form
    public bool $showKeyModal = false;

    public string $newKeyName = '';

    public string $newPublicKey = '';

    // Connectivity status
    public ?string $sshCheckStatus = null; // 'healthy', 'out_of_sync', 'unreachable', 'panel_only'

    public ?string $sshCheckMessage = null;

    public string $ptrRecord = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->getService(false);
    }

    private function getService(bool $requireAdmin = false): ServiceInstance
    {
        $service = ServiceInstance::where('uuid', $this->uuid)->firstOrFail();
        $user = auth()->user();
        abort_unless($user && $user->teams->contains('id', $service->team_id), 403, 'Unauthorized access to service resource.');

        if ($requireAdmin) {
            abort_unless($user->isAdminOfTeam($service->team_id), 403, 'Only team administrators or owners can perform this action.');
        }

        return $service;
    }

    public function openPasswordModal(): void
    {
        $this->accountPassword = '';
        $this->showPasswordModal = true;
    }

    public function revealRootPassword(): void
    {
        $this->validate([
            'accountPassword' => 'required|string',
        ]);

        $user = auth()->user();
        if (! Hash::check($this->accountPassword, $user->password)) {
            $this->addError('accountPassword', 'Incorrect account password.');

            return;
        }

        $service = $this->getService(true);

        $rootCred = $service->credentials()
            ->where('credential_type', 'root_password')
            ->first();

        if (! $rootCred) {
            $this->dispatch('error', 'No stored root password found for this instance.');
            $this->showPasswordModal = false;

            return;
        }

        $this->revealedPassword = $rootCred->getDecryptedSecret();
        $rootCred->update(['last_retrieved_at' => now()]);

        CommerceAuditLog::create([
            'user_id' => $user->id,
            'team_id' => $service->team_id,
            'action' => 'credential.revealed',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->showPasswordModal = false;
        $this->dispatch('success', 'Root password revealed. Store it securely in a password manager.');
    }

    /**
     * Proactively test SSH connection and verify whether the stored password still matches the server.
     */
    public function checkSshConnectivity(): void
    {
        $service = $this->getService(true);
        $factory = app(VpsConnectionFactory::class);

        $this->sshCheckStatus = null;
        $this->sshCheckMessage = null;

        if (blank($service->ip_address)) {
            $this->sshCheckStatus = 'unreachable';
            $this->sshCheckMessage = 'Server IP address is not assigned yet.';

            return;
        }

        $cred = $service->credentials()->where('credential_type', 'root_password')->first();

        // 1. If customer marked it as customer-managed, check panel key access
        if ($service->is_customer_managed_credentials || ! $cred) {
            if ($factory->hasPanelAccess($service)) {
                try {
                    $shell = $factory->connect($service);
                    $shell->exec('echo "ok"');
                    $shell->disconnect();
                    $this->sshCheckStatus = 'panel_only';
                    $this->sshCheckMessage = 'Panel key connection is verified. Root credentials are customer-managed.';
                } catch (\Throwable $e) {
                    $this->sshCheckStatus = 'unreachable';
                    $this->sshCheckMessage = 'Server is unreachable or panel access key was rejected: ' . $e->getMessage();
                }
            } else {
                $this->sshCheckStatus = 'out_of_sync';
                $this->sshCheckMessage = 'Server is set to customer-managed but panel key access is missing.';
            }

            return;
        }

        // 2. Test stored password against the live server
        try {
            $decrypted = $cred->getDecryptedSecret();
            $factory->testCredentials($service, 'root', $decrypted);
            $this->sshCheckStatus = 'healthy';
            $this->sshCheckMessage = 'SSH connection and stored password verified successfully.';
        } catch (VpsAccessException $e) {
            if ($e->getMessage() === 'The server rejected that login. Check the password or key.') {
                $this->sshCheckStatus = 'out_of_sync';
                $this->sshCheckMessage = 'The root password on the server was changed and no longer matches our vault. Please update it below.';
            } else {
                $this->sshCheckStatus = 'unreachable';
                $this->sshCheckMessage = $e->getMessage();
            }
        } catch (\Throwable $e) {
            $this->sshCheckStatus = 'out_of_sync';
            $this->sshCheckMessage = 'Could not authenticate with stored credentials: ' . $e->getMessage();
        }
    }

    public function openUpdatePasswordModal(): void
    {
        $this->newRootPassword = '';
        $this->confirmNewRootPassword = '';
        $this->showUpdatePasswordModal = true;
    }

    /**
     * Customer updates changed password. Pre-flights an SSH authentication before saving.
     */
    public function updateChangedPassword(): void
    {
        $this->validate([
            'newRootPassword' => 'required|string|min:6|max:256',
            'confirmNewRootPassword' => 'required|same:newRootPassword',
        ]);

        $service = $this->getService(true);
        $factory = app(VpsConnectionFactory::class);

        // Pre-flight test live on the server
        try {
            $factory->testCredentials($service, 'root', $this->newRootPassword);
        } catch (\Throwable $e) {
            $this->addError('newRootPassword', 'The server rejected this password. Please ensure you typed the current root password on the server.');

            return;
        }

        // Update or create credential
        $cred = $service->credentials()->where('credential_type', 'root_password')->first();
        if (! $cred) {
            $cred = new ServiceCredential([
                'uuid' => (string) Str::uuid(),
                'service_instance_id' => $service->id,
                'credential_type' => 'root_password',
                'username' => 'root',
                'ssh_port' => $service->network_config['ssh_port'] ?? 22,
            ]);
        }

        $cred->setEncryptedSecretAttribute($this->newRootPassword);
        $cred->save();

        // Ensure panel access key is also installed
        try {
            $factory->bootstrap($service, $this->newRootPassword);
        } catch (\Throwable) {
            // Ignore if key is already bootstrapped
        }

        $service->update([
            'is_customer_managed_credentials' => false,
            'customer_password_rotated_at' => now(),
        ]);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $service->team_id,
            'action' => 'credential.password_updated',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->revealedPassword = $this->newRootPassword;
        $this->showUpdatePasswordModal = false;
        $this->newRootPassword = '';
        $this->confirmNewRootPassword = '';
        $this->sshCheckStatus = 'healthy';
        $this->sshCheckMessage = 'New password verified against server and updated successfully.';

        $this->dispatch('success', 'Server password verified and vault updated!');
    }

    /**
     * Mark credentials as customer-managed, clearing the root password from our database.
     */
    public function markCustomerManaged(): void
    {
        $service = $this->getService(true);

        $service->credentials()->where('credential_type', 'root_password')->delete();
        $service->update([
            'is_customer_managed_credentials' => true,
            'customer_password_rotated_at' => now(),
        ]);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $service->team_id,
            'action' => 'credential.marked_customer_managed',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->revealedPassword = null;
        $this->sshCheckStatus = 'panel_only';
        $this->sshCheckMessage = 'Server marked as Customer-Managed. Root password cleared from vault; panel key remains active.';

        $this->dispatch('success', 'Marked as Customer-Managed. We no longer hold your root password.');
    }

    public function openKeyModal(): void
    {
        $this->newKeyName = '';
        $this->newPublicKey = '';
        $this->showKeyModal = true;
    }

    /**
     * Install customer public SSH key onto the server via the panel connection.
     */
    public function addAuthorizedKey(): void
    {
        $this->validate([
            'newKeyName' => 'required|string|max:128',
            'newPublicKey' => 'required|string|min:20|max:2048',
        ]);

        $cleanKey = trim($this->newPublicKey);

        if (! preg_match('/^(ssh-rsa|ssh-ed25519|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521)\s+[A-Za-z0-9+\/]+={0,3}(\s+.*)?$/', $cleanKey)) {
            $this->addError('newPublicKey', 'Invalid public key format. Must be an OpenSSH public key (e.g. ssh-ed25519 or ssh-rsa).');

            return;
        }

        $service = $this->getService(true);
        $factory = app(VpsConnectionFactory::class);

        try {
            $shell = $factory->connect($service);
            try {
                $shell->exec(
                    'umask 077 && mkdir -p ~/.ssh && touch ~/.ssh/authorized_keys && ' .
                    'echo ' . escapeshellarg($cleanKey) . ' >> ~/.ssh/authorized_keys'
                );
            } finally {
                $shell->disconnect();
            }
        } catch (\Throwable $e) {
            $this->addError('newPublicKey', 'Could not install key onto server: ' . $e->getMessage());

            return;
        }

        VpsAuthorizedKey::create([
            'uuid' => (string) Str::uuid(),
            'service_instance_id' => $service->id,
            'name' => $this->newKeyName,
            'public_key' => $cleanKey,
            'fingerprint_sha256' => substr(sha1($cleanKey), 0, 16),
        ]);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $service->team_id,
            'action' => 'vps.ssh_key_added',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['key_name' => $this->newKeyName],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->showKeyModal = false;
        $this->newKeyName = '';
        $this->newPublicKey = '';

        $this->dispatch('success', 'Public SSH key installed in /root/.ssh/authorized_keys on your server.');
    }

    public function deleteAuthorizedKey(int $keyId): void
    {
        $service = $this->getService(true);
        $key = VpsAuthorizedKey::where('service_instance_id', $service->id)->where('id', $keyId)->first();

        if ($key) {
            $key->delete();
            $this->dispatch('success', 'Key removed from management list.');
        }
    }

    public function requestPtr(): void
    {
        $this->validate([
            'ptrRecord' => 'required|string|regex:/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        ]);

        $service = $this->getService(true);

        CommerceAuditLog::create([
            'user_id' => auth()->id(),
            'team_id' => $service->team_id,
            'action' => 'ptr_record.requested',
            'target_type' => ServiceInstance::class,
            'target_id' => $service->id,
            'payload' => ['requested_ptr' => $this->ptrRecord],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $this->ptrRecord = '';
        $this->dispatch('success', 'Reverse DNS PTR request submitted to network operators.');
    }

    public function render()
    {
        $service = ServiceInstance::with(['productVariant.product', 'credentials', 'order', 'authorizedKeys'])
            ->where('uuid', $this->uuid)
            ->firstOrFail();

        abort_if(! currentTeam() || $service->team_id !== currentTeam()->id, 403, 'Unauthorized access to service resource.');

        return view('livewire.portal.service-detail', [
            'service' => $service,
        ]);
    }
}
