<?php

use App\Mcp\Servers\StxServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', StxServer::class)
    ->middleware(['mcp.enabled', 'api.ip.allowed', 'auth:sanctum', 'api.token.team', 'mcp.team.enabled']);
