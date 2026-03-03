<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Helper for resolving and loading timer skin files (.json and .jsonc).
 */
class SkinFileHelper
{
    private static string $skinsDir;

    public static function skinsDir(): string
    {
        if (!isset(self::$skinsDir)) {
            self::$skinsDir = resource_path('timerskins');
        }
        return self::$skinsDir;
    }

    /**
     * Resolve the path to a skin file by key. Tries .json first, then .jsonc.
     * Returns null if neither exists.
     */
    public static function resolvePath(string $key): ?string
    {
        $dir = self::skinsDir();
        $json = $dir . DIRECTORY_SEPARATOR . $key . '.json';
        $jsonc = $dir . DIRECTORY_SEPARATOR . $key . '.jsonc';
        if (file_exists($json)) {
            return $json;
        }
        if (file_exists($jsonc)) {
            return $jsonc;
        }
        return null;
    }

    /**
     * Check if a skin file exists (either .json or .jsonc).
     */
    public static function exists(string $key): bool
    {
        return self::resolvePath($key) !== null;
    }

    /**
     * Load and decode a skin file. Handles JSONC (strips comments) for .jsonc files.
     * If initial parse fails, tries stripping comments (handles .json files with comments).
     * Returns null on parse failure.
     */
    public static function load(string $path): ?array
    {
        $content = File::get($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jsonc') {
            $content = self::stripJsoncComments($content);
        }
        $decoded = json_decode($content, true);
        if ($decoded === null && ($ext === 'json' || str_contains($content, '//') || str_contains($content, '/*'))) {
            $content = self::stripJsoncComments($content);
            $decoded = json_decode($content, true);
        }
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * List all skin keys (from both .json and .jsonc). Prefers .json when both exist for same key.
     */
    public static function listKeys(): array
    {
        $dir = self::skinsDir();
        if (!is_dir($dir)) {
            return [];
        }
        $json = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $jsonc = glob($dir . DIRECTORY_SEPARATOR . '*.jsonc') ?: [];
        $byKey = [];
        foreach ($json as $path) {
            $byKey[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }
        foreach ($jsonc as $path) {
            $key = pathinfo($path, PATHINFO_FILENAME);
            if (!isset($byKey[$key])) {
                $byKey[$key] = $path;
            }
        }
        return array_values(array_keys($byKey));
    }

    /**
     * List skin file paths (SplFileInfo or path strings) for iteration.
     * Includes both .json and .jsonc. For duplicate keys, .json takes precedence.
     */
    public static function listFiles(): array
    {
        $dir = self::skinsDir();
        if (!is_dir($dir)) {
            return [];
        }
        $json = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $jsonc = glob($dir . DIRECTORY_SEPARATOR . '*.jsonc') ?: [];
        $byKey = [];
        foreach ($json as $path) {
            $byKey[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }
        foreach ($jsonc as $path) {
            $key = pathinfo($path, PATHINFO_FILENAME);
            if (!isset($byKey[$key])) {
                $byKey[$key] = $path;
            }
        }
        return array_values($byKey);
    }

    /**
     * Strip JSONC comments (// and /* *\/) from a string.
     */
    private static function stripJsoncComments(string $json): string
    {
        $result = '';
        $len = strlen($json);
        $i = 0;
        $inString = false;
        $stringChar = '';
        $escape = false;
        $inSingleLineComment = false;
        $inMultiLineComment = false;

        while ($i < $len) {
            $c = $json[$i];
            $next = $json[$i + 1] ?? '';

            if ($inSingleLineComment) {
                if ($c === "\n" || $c === "\r") {
                    $inSingleLineComment = false;
                    $result .= $c;
                }
                $i++;
                continue;
            }

            if ($inMultiLineComment) {
                if ($c === '*' && $next === '/') {
                    $inMultiLineComment = false;
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            if ($escape) {
                $result .= $c;
                $escape = false;
                $i++;
                continue;
            }

            if ($inString) {
                if ($c === '\\') {
                    $result .= $c;
                    $escape = true;
                } elseif ($c === $stringChar) {
                    $result .= $c;
                    $inString = false;
                } else {
                    $result .= $c;
                }
                $i++;
                continue;
            }

            if (($c === '"' || $c === "'") && !$inString) {
                $inString = true;
                $stringChar = $c;
                $result .= $c;
                $i++;
                continue;
            }

            if ($c === '/' && $next === '/') {
                $inSingleLineComment = true;
                $i += 2;
                continue;
            }

            if ($c === '/' && $next === '*') {
                $inMultiLineComment = true;
                $i += 2;
                continue;
            }

            $result .= $c;
            $i++;
        }

        return $result;
    }
}
