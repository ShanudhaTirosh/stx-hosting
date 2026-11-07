<?php

namespace App\Services\Vps;

use App\Exceptions\VpsAccessException;
use App\Models\Commerce\ServiceInstance;
use App\Models\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * Opens connections from the panel to a customer's VPS.
 *
 * The panel never depends on the customer's root password: the first time (using the password we delivered,
 * or one the customer types in once) it installs its own key and from then on signs in with that key, so
 * the customer can change the root password whenever they like. The server's host key is pinned on the
 * first connection and a change stops the connection.
 */
class VpsConnectionFactory
{
    /** Connect with the panel key. */
    public function connect(ServiceInstance $service): RemoteShell
    {
        $key = $service->panel_private_key_id ? PrivateKey::find($service->panel_private_key_id) : null;

        if (! $key) {
            throw VpsAccessException::notSetUp();
        }

        return $this->open($service, PublicKeyLoader::load($key->private_key));
    }

    public function hasPanelAccess(ServiceInstance $service): bool
    {
        return (bool) $service->panel_private_key_id;
    }

    /**
     * Install the panel key. Uses the given password, or the one stored at delivery. The password is not kept.
     */
    public function bootstrap(ServiceInstance $service, ?string $password = null): void
    {
        if ($this->hasPanelAccess($service)) {
            return;
        }

        $password ??= $service->credentials()->where('credential_type', 'root_password')->first()?->getDecryptedSecret();

        if (blank($password)) {
            throw VpsAccessException::notSetUp();
        }

        $pair = generateSSHKey('ed25519');
        // Keep only the key type and the key itself, then label it.
        $publicKey = implode(' ', array_slice(explode(' ', trim($pair['public'])), 0, 2)).' stx-panel';

        $shell = $this->open($service, $password);

        try {
            $shell->exec(
                'umask 077 && mkdir -p ~/.ssh && touch ~/.ssh/authorized_keys && '.
                'grep -qxF '.escapeshellarg($publicKey).' ~/.ssh/authorized_keys || '.
                'echo '.escapeshellarg($publicKey).' >> ~/.ssh/authorized_keys'
            );
        } finally {
            $shell->disconnect();
        }

        // Prove the key works before relying on it.
        $this->open($service, PublicKeyLoader::load($pair['private']))->disconnect();

        $stored = PrivateKey::createAndStore([
            'name' => 'VPS panel key '.$service->uuid,
            'description' => 'Used by the client panel to reach VPS '.$service->ip_address,
            'private_key' => $pair['private'],
            'team_id' => 0,
        ]);

        $service->forceFill(['panel_private_key_id' => $stored->id])->save();
    }

    protected function open(ServiceInstance $service, mixed $credential): RemoteShell
    {
        $port = (int) ($service->credentials()->value('ssh_port') ?: 22);
        $shell = $this->makeShell((string) $service->ip_address, $port, 'root', $credential);

        $this->pinHostKey($service, $shell);

        return $shell;
    }

    /** Overridden in tests. */
    protected function makeShell(string $host, int $port, string $user, mixed $credential): RemoteShell
    {
        return new PhpseclibRemoteShell($host, $port, $user, $credential);
    }

    private function pinHostKey(ServiceInstance $service, RemoteShell $shell): void
    {
        $fingerprint = $shell->hostKeyFingerprint();

        if ($fingerprint === '') {
            $shell->disconnect();
            throw VpsAccessException::unreachable();
        }

        if (blank($service->host_key_sha256)) {
            $service->forceFill(['host_key_sha256' => $fingerprint])->save();

            return;
        }

        if (! hash_equals((string) $service->host_key_sha256, $fingerprint)) {
            $shell->disconnect();
            throw VpsAccessException::hostKeyChanged();
        }
    }
}
