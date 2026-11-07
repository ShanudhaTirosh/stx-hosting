<?php

namespace App\Services\Vps;

use App\Exceptions\VpsAccessException;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * RemoteShell over SSH/SFTP (phpseclib). Connects lazily, logs in once, reuses the connection.
 * Not exercised by the automated tests (they need a real SSH server); everything above it is.
 */
class PhpseclibRemoteShell implements RemoteShell
{
    private ?SSH2 $ssh = null;

    private ?SFTP $sftp = null;

    /** @param  string|PrivateKey  $credential  root password or a loaded private key */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string|PrivateKey $credential,
    ) {}

    public function exec(string $command, int $timeoutSeconds = 15): string
    {
        $ssh = $this->ssh();
        $ssh->setTimeout($timeoutSeconds);

        return (string) $ssh->exec($command);
    }

    public function listDirectory(string $path): array
    {
        $rows = $this->sftp()->rawlist($path);

        if (! is_array($rows)) {
            throw new \RuntimeException('That folder cannot be read.');
        }

        $entries = [];
        foreach ($rows as $name => $info) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            // SFTP file types: 1 regular, 2 directory, 3 symbolic link.
            $type = match ((int) ($info['type'] ?? 1)) {
                2 => 'dir',
                3 => 'link',
                default => 'file',
            };
            $entries[] = [
                'name' => (string) $name,
                'type' => $type,
                'size' => (int) ($info['size'] ?? 0),
                'modified' => (int) ($info['mtime'] ?? 0),
            ];
        }

        usort($entries, fn ($a, $b) => [$a['type'] !== 'dir', strtolower($a['name'])] <=> [$b['type'] !== 'dir', strtolower($b['name'])]);

        return $entries;
    }

    public function download(string $path, int $maxBytes): string
    {
        $sftp = $this->sftp();
        $stat = $sftp->stat($path);

        if (! is_array($stat) || (int) ($stat['type'] ?? 1) === 2) {
            throw new \RuntimeException('That is not a file.');
        }

        if ((int) ($stat['size'] ?? 0) > $maxBytes) {
            throw new \RuntimeException('That file is too large to download here. Use an SFTP client for big files.');
        }

        $data = $sftp->get($path);

        if ($data === false) {
            throw new \RuntimeException('The file could not be read.');
        }

        return (string) $data;
    }

    public function upload(string $path, string $contents): void
    {
        if (! $this->sftp()->put($path, $contents)) {
            throw new \RuntimeException('The file could not be written. Check the folder exists and is writable.');
        }
    }

    public function delete(string $path): void
    {
        if (! $this->sftp()->delete($path, true)) {
            throw new \RuntimeException('That could not be deleted.');
        }
    }

    public function hostKeyFingerprint(): string
    {
        $key = $this->ssh()->getServerPublicHostKey();

        return $key ? (string) $key->getFingerprint('sha256') : '';
    }

    public function disconnect(): void
    {
        $this->ssh?->disconnect();
        $this->sftp?->disconnect();
        $this->ssh = $this->sftp = null;
    }

    private function ssh(): SSH2
    {
        return $this->ssh ??= $this->login(new SSH2($this->host, $this->port, 10));
    }

    private function sftp(): SFTP
    {
        return $this->sftp ??= $this->login(new SFTP($this->host, $this->port, 10));
    }

    private function login(SSH2 $connection): SSH2
    {
        try {
            $ok = $connection->login($this->user, $this->credential);
        } catch (\Throwable) {
            throw VpsAccessException::unreachable();
        }

        if (! $ok) {
            throw $connection->isConnected() ? VpsAccessException::loginFailed() : VpsAccessException::unreachable();
        }

        return $connection;
    }
}
