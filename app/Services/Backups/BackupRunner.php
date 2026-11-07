<?php

namespace App\Services\Backups;

use App\Jobs\DatabaseBackupJob;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;

/** Runs one backup now and waits for it. A seam, so restores can be tested without a real server. */
class BackupRunner
{
    public function run(ScheduledDatabaseBackup $backup): ?ScheduledDatabaseBackupExecution
    {
        $before = (int) $backup->executions()->max('id');

        DatabaseBackupJob::dispatchSync($backup);

        return $backup->executions()->where('id', '>', $before)->latest('id')->first();
    }
}
