<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver;

/**
 * Validates whether an IP address belongs to a trusted proxy range.
 *
 * Accepts exact IP addresses and CIDR notation for both IPv4 and IPv6
 * (e.g. 173.245.48.0/20, 2400:cb00::/32).
 *
 * @package rafalmasiarek\RealIpResolver
 */
class TrustedProxy implements TrustedProxyInterface
{
    /**
     * Trusted IP addresses or CIDR ranges.
     *
     * @var string[]
     */
    private array $trustedIps;

    /**
     * @param string[] $trustedIps Trusted IP addresses or CIDR ranges.
     */
    public function __construct(array $trustedIps)
    {
        $this->trustedIps = $trustedIps;
    }

    /**
     * Check whether the given IP address is trusted.
     *
     * Supports both exact string matches and CIDR range matching.
     *
     * @param string $ip IP address to check.
     * @return bool True if the IP matches any trusted entry.
     */
    public function isTrusted(string $ip): bool
    {
        foreach ($this->trustedIps as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an IP address falls within a CIDR block.
     *
     * Uses inet_pton() for binary byte-level comparison, which handles
     * both IPv4 (4-byte) and IPv6 (16-byte) addresses uniformly.
     *
     * @param string $ip   IP address to test.
     * @param string $cidr CIDR block, e.g. 173.245.48.0/20 or 2400:cb00::/32.
     * @return bool True if the IP is within the block.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefix] = explode('/', $cidr, 2);
        $prefixLen = (int) $prefix;

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $fullBytes  = intdiv($prefixLen, 8);
        $remainBits = $prefixLen % 8;
        $maxBytes   = strlen($ipBin);

        for ($i = 0; $i < $fullBytes && $i < $maxBytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        if ($remainBits > 0 && $fullBytes < $maxBytes) {
            $mask = (0xff << (8 - $remainBits)) & 0xff;

            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
