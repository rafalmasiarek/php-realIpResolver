<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\IPLists;

/**
 * Contract for IP address list providers used as trusted proxy sources.
 *
 * Implementations may return plain IP addresses or CIDR notation ranges.
 *
 * @package rafalmasiarek\RealIpResolver\IPLists
 */
interface IpListInterface
{
    /**
     * Return the list of IP addresses or CIDR ranges.
     *
     * @return string[] Array of IP addresses or CIDR blocks (e.g. '173.245.48.0/20').
     */
    public static function get(): array;
}
