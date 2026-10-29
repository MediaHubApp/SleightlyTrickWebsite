<?php
// Production defaults. Local overrides only apply to PHP's development server.
return [
    'storage_dir' => PHP_SAPI === 'cli-server' && getenv('ST_STORAGE_DIR')
        ? getenv('ST_STORAGE_DIR') : dirname(__DIR__, 2) . '/private/sleightly-tricky',
    'require_https' => !(PHP_SAPI === 'cli-server' && getenv('ST_LOCAL_HTTP') === '1'),
];
