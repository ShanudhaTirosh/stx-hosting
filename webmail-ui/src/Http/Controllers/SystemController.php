<?php
declare(strict_types=1);

namespace Webmail\Http\Controllers;

use Webmail\App;
use Webmail\Core\{Request, Response};

final class SystemController
{
    /** Liveness only. Deliberately reveals nothing about WildDuck or configuration. */
    public static function health(Request $r, App $app): Response
    {
        return Response::json(['ok' => true]);
    }
}
