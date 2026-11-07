<?php

namespace App\Console\Commands;

use App\Services\Security\SecurityChecker;
use Illuminate\Console\Command;

class SecurityCheck extends Command
{
    protected $signature = 'stx:security-check {--strict : Treat warnings as failures}';

    protected $description = 'Check the settings that make the platform safe and working in production';

    public function handle(SecurityChecker $checker): int
    {
        $rows = $checker->run();

        $this->table(['', 'Check', 'Detail'], collect($rows)->map(fn ($r) => [
            match ($r['level']) { 'ok' => 'OK', 'warn' => 'WARN', default => 'FAIL' },
            $r['check'],
            $r['level'] === 'ok' ? '' : $r['detail'],
        ])->all());

        $fails = collect($rows)->where('level', 'fail')->count();
        $warns = collect($rows)->where('level', 'warn')->count();
        $this->line("{$fails} to fix now, {$warns} worth fixing.");

        return ($fails > 0 || ($this->option('strict') && $warns > 0)) ? self::FAILURE : self::SUCCESS;
    }
}
