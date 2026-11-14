<?php

namespace App\Services\Vps;

/** What the panel may do on a customer's VPS. One real implementation (phpseclib); tests use a fake. */
interface RemoteShell
{
    /** Run one command and return its output. */
    public function exec(string $command, int $timeoutSeconds = 15): string;

    /** @return array<int, array{name: string, type: 'dir'|'file'|'link', size: int, modified: int}> */
    public function listDirectory(string $path): array;

    public function download(string $path, int $maxBytes): string;

    public function upload(string $path, string $contents): void;

    public function rename(string $oldPath, string $newPath): void;

    public function delete(string $path): void;

    /** SHA-256 fingerprint of the server's SSH host key (base64, as ssh prints it). */
    public function hostKeyFingerprint(): string;

    public function disconnect(): void;
}
