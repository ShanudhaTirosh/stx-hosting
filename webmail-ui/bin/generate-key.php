#!/usr/bin/env php
<?php
declare(strict_types=1);

// Prints a new APP_KEY (32 random bytes, base64). Put it in .env; changing it later signs everyone out.
fwrite(STDOUT, base64_encode(random_bytes(32)) . "\n");
