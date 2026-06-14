<?php
/**
 * Loads PasarKraft configuration from .env files.
 * Uses an in-memory store because getenv()/putenv() are unreliable on some shared hosts.
 */
$GLOBALS['PK_CONFIG'] = $GLOBALS['PK_CONFIG'] ?? [];

function pk_load_env($path) {
    if (!file_exists($path) || !is_readable($path)) {
        return;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return;
    }

    // Strip UTF-8 BOM if present.
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $lines = preg_split('/\R/', $raw);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (preg_match('/^"([^"]*)"$/', $value, $match) || preg_match('/^\'([^\']*)\'$/', $value, $match)) {
            $value = $match[1];
        }

        $GLOBALS['PK_CONFIG'][$name] = $value;
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        if (function_exists('putenv')) {
            @putenv($name . '=' . $value);
        }
    }
}

function pk_env($key, $default = '') {
    if (isset($GLOBALS['PK_CONFIG'][$key]) && $GLOBALS['PK_CONFIG'][$key] !== '') {
        return $GLOBALS['PK_CONFIG'][$key];
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

// InfinityFree only allows uploads inside htdocs.
pk_load_env(__DIR__ . '/pk_config.env');
pk_load_env(__DIR__ . '/.env');
pk_load_env(__DIR__ . '/../.env');
