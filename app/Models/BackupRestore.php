<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One restore request: which backup, who asked, and how far it got. */
class BackupRestore extends BaseModel
{
    protected $guarded = [];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ScheduledDatabaseBackupExecution::class, 'execution_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
