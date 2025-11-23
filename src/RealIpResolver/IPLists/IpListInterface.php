<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\IPLists;

/**
 * Common interface for IP list providers used by TrustedProxy.
 *
 * Implementations should return a list of IPs/CIDR ranges as strings.
 */
interface IpListInterface
{
    /**
     * Return list of IPs/CIDRs for this provider.
     *
     * @return string[] Array of IP addresses or CIDR blocks.
     */
    public static function get(): array;
}

