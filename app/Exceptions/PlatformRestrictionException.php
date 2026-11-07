<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;

/** A client tried something their plan or the platform rules do not allow. The message is safe to show. */
class PlatformRestrictionException extends RuntimeException
{
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 403);
        }

        abort(403, $this->getMessage());
    }

    public function report(): bool
    {
        return false; // expected, user-facing; not an error to log
    }
}
