<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver;

/**
 * Contract for trusted proxy validators.
 *
 * @package rafalmasiarek\RealIpResolver
 */
interface TrustedProxyInterface
{
    /**
     * Determine whether the given IP address belongs to a trusted proxy.
     *
     * @param string $ip IP address to check.
     * @return bool
     */
    public function isTrusted(string $ip): bool;
}
