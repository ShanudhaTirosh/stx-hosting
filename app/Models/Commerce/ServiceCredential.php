<?php

namespace App\Models\Commerce;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ServiceCredential extends BaseModel
{
    protected $guarded = [];

    protected $casts = [
        'ssh_port' => 'integer',
        'last_retrieved_at' => 'datetime',
    ];

    public function serviceInstance(): BelongsTo
    {
        return $this->belongsTo(ServiceInstance::class);
    }

    public function setEncryptedSecretAttribute(string $value): void
    {
        $this->attributes['encrypted_secret'] = Crypt::encryptString($value);
    }

    public function getDecryptedSecret(): string
    {
        return Crypt::decryptString($this->encrypted_secret);
    }
}
