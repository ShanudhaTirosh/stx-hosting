<?php

namespace App\Livewire\Concerns;

use App\Services\RbacService;

/**
 * Livewire runs bootRequiresPlatformStaff() on EVERY request to the component:
 * the initial mount and every later action call. That makes the staff check
 * impossible to skip by calling an action method directly.
 */
trait RequiresPlatformStaff
{
    public function bootRequiresPlatformStaff(): void
    {
        abort_unless(RbacService::isStaff(auth()->user()), 403, 'This area is restricted to platform staff.');
    }
}
