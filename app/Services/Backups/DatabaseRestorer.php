<?php

namespace App\Services\Backups;

use App\Actions\Database\StartDatabaseImport;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\S3Storage;
use App\Support\DatabaseImport\DatabaseImportSource;
use Illuminate\Database\Eloquent\Model;

/** Starts Coolify's own restore from an S3 backup. A seam, so tests do not need a server. */
class DatabaseRestorer
{
    public function start(Model $database, ScheduledDatabaseBackupExecution $execution, S3Storage $storage, int $teamId): void
    {
        $backup = $execution->scheduledDatabaseBackup;

        StartDatabaseImport::run(
            $database,
            new DatabaseImportSource(
                type: 's3',
                path: ltrim((string) $execution->filename, '/'),
                s3StorageUuid: $storage->uuid,
                dumpAll: (bool) $backup->dump_all,
                replaceExisting: true,
            ),
            $teamId,
            // The backup lives in the platform's storage, not in the customer's team.
            storageTeamId: (int) $storage->team_id,
        );
    }
}
