<?php
/**
 * Minimal .env reader. Keeps credentials out of the source tree and out of Git:
 * .env sits at the project root, is git-ignored, and .htaccess denies it over HTTP.
 *
 * Syntax: KEY=value, one per line. "#" after whitespace starts a comment, so quote
 * any value containing one: SMTP_PASS="pa ss#word".
 */

function load_env(string $path): array
{
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];

    $env = [];
    if (is_readable($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
                $q = $v[0];
                $end = strpos($v, $q, 1);
                $v = $end === false ? substr($v, 1) : substr($v, 1, $end - 1);
            } else {
                $v = preg_replace('/\s+#.*$/', '', $v);
            }
            $env[trim($k)] = trim($v);
        }
    }
    return $cache[$path] = $env;
}

/** Read one key from the project's .env, with a fallback. */
function env(string $key, $default = null)
{
    $env = load_env(dirname(__DIR__) . '/.env');
    return array_key_exists($key, $env) && $env[$key] !== '' ? $env[$key] : $default;
}

/** Keys that must be set for the back-end to run at all. */
function env_missing(array $keys): array
{
    $missing = [];
    foreach ($keys as $k) if (env($k) === null) $missing[] = $k;
    return $missing;
}
