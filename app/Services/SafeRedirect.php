<?php

namespace App\Services;

class SafeRedirect
{
    public function resolve($url): string
    {
        return $this->validate($url) ?? '/panel';
    }

    public function validate($url): ?string
    {
        if (!is_string($url) || strlen($url) > config('pnp.continue_max_length', 2000)
            || strpos($url, '\\') !== false || strpos($url, '//') === 0) {
            return null;
        }

        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $path = $parts['path'] ?? '/';
        if (isset($parts['port']) && $parts['port'] !== 443) {
            return null;
        }

        foreach ((array) config('pnp.redirect_allowlist', []) as $entry) {
            $allowedHost = strtolower(rtrim($entry['host'] ?? '', '.'));
            $prefix = $entry['path_prefix'] ?? '/';
            $prefix = rtrim($prefix, '/') ?: '/';
            $matchesPath = $prefix === '/' || $path === $prefix || strpos($path, $prefix . '/') === 0;
            if ($allowedHost !== '' && $host === $allowedHost && $matchesPath) {
                return $url;
            }
        }

        return null;
    }
}
