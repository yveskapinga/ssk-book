#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Offline gold recall check against hybrid search (no LLM generation).
 * Prefer: php bin/console app:book:eval-ask-gold
 * This wrapper keeps the old entrypoint.
 */

passthru(
    sprintf(
        '%s %s app:book:eval-ask-gold',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(dirname(__DIR__).'/bin/console'),
    ),
    $code,
);
exit($code);
