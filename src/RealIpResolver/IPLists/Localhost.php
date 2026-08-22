<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\IPLists;

/**
 * Provides loopback addresses as trusted proxy sources.
 *
 * Useful when the application runs behind a local reverse proxy
 * (e.g. Nginx on the same host).
 *
 * @package rafalmasiarek\RealIpResolver\IPLists
 */
class Localhost implements IpListInterface
{
    /**
     * Return standard loopback addresses for IPv4 and IPv6.
     *
     * @return string[]
     */
    public static function get(): array
    {
        return ['127.0.0.1', '::1'];
    }
}
