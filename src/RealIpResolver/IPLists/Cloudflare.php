<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\IPLists;

/**
 * Provides Cloudflare edge server IP ranges for use as trusted proxy sources.
 *
 * Ranges are read from a local cache file and can be refreshed from Cloudflare's
 * official IP list endpoints. An import() method allows overriding the list
 * in-memory, which is useful in tests. setFile() allows relocating the cache
 * file, which is useful for applications where the package install directory
 * does not persist across deploys.
 *
 * @package rafalmasiarek\RealIpResolver\IPLists
 */
class Cloudflare implements IpListInterface
{
    /**
     * In-memory cache of loaded IP ranges.
     *
     * @var string[]|null
     */
    private static ?array $cache = null;

    /**
     * Absolute path to the local IP list cache file. Override via setFile().
     *
     * @var string
     */
    private static string $file = __DIR__ . '/../data/cloudflare.txt';

    /**
     * Return the list of Cloudflare CIDR ranges from the local cache file.
     *
     * @return string[]
     */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (!file_exists(self::$file)) {
            return [];
        }

        $lines = file(self::$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::$cache = array_values(array_filter(array_map('trim', $lines !== false ? $lines : [])));

        return self::$cache;
    }

    /**
     * Download fresh Cloudflare IP ranges from official endpoints and overwrite the local cache file.
     *
     * @throws \RuntimeException When both the IPv4 and IPv6 endpoints are unreachable.
     * @return void
     */
    public static function updateList(): void
    {
        $ips4 = @file_get_contents('https://www.cloudflare.com/ips-v4');
        $ips6 = @file_get_contents('https://www.cloudflare.com/ips-v6');

        if ($ips4 === false && $ips6 === false) {
            throw new \RuntimeException('Failed to download Cloudflare IP lists.');
        }

        $all = array_values(array_filter(array_map('trim', array_merge(
            $ips4 !== false ? explode("\n", $ips4) : [],
            $ips6 !== false ? explode("\n", $ips6) : [],
        ))));

        $dir = dirname(self::$file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::$file, implode("\n", $all));
        self::$cache = null;
    }

    /**
     * Override the IP list in-memory, bypassing the local cache file.
     *
     * Intended for use in tests to avoid filesystem access.
     *
     * @param string[] $ips List of IP addresses or CIDR ranges.
     * @return void
     */
    public static function import(array $ips): void
    {
        self::$cache = array_values(array_filter(array_map('trim', $ips)));
    }

    /**
     * Relocate the local cache file used by get() and updateList().
     *
     * Useful when the package install directory does not persist across
     * deploys (e.g. a `composer install` that rebuilds vendor/ on every
     * release), so the cache should live under an application-managed,
     * persistent storage path instead.
     *
     * @param string $path Absolute path to the cache file.
     * @return void
     */
    public static function setFile(string $path): void
    {
        self::$file = $path;
        self::$cache = null;
    }
}
