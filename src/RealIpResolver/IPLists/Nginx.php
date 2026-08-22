<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\IPLists;

/**
 * Provides a configurable list of trusted Nginx reverse proxy IP addresses.
 *
 * Unlike Cloudflare, Nginx proxy IPs are deployment-specific and must be
 * supplied via import() before use.
 *
 * @package rafalmasiarek\RealIpResolver\IPLists
 */
class Nginx implements IpListInterface
{
    /**
     * In-memory list of trusted Nginx proxy IPs or CIDR ranges.
     *
     * @var string[]|null
     */
    private static ?array $cache = null;

    /**
     * Return the configured list of Nginx proxy IPs or CIDR ranges.
     *
     * @return string[]
     */
    public static function get(): array
    {
        return self::$cache ?? [];
    }

    /**
     * Set the list of trusted Nginx proxy IPs or CIDR ranges.
     *
     * @param string[] $ips List of IP addresses or CIDR ranges.
     * @return void
     */
    public static function import(array $ips): void
    {
        self::$cache = array_values(array_filter(array_map('trim', $ips)));
    }
}
