<?php

namespace rafalmasiarek;

use rafalmasiarek\RealIpResolver\TrustedProxy;

class RealIpResolver
{
    private ?TrustedProxy $trustedProxy;
    private bool $filterPrivateReserved = true;

    public function __construct(?TrustedProxy $trustedProxy = null)
    {
        $this->trustedProxy = $trustedProxy;
    }

    /**
     * Disable filtering of private and reserved IP ranges.
     */
    public function disablePrivateReservedFilter(): void
    {
        $this->filterPrivateReserved = false;
    }

    /**
     * Get the best-effort real client IP address based on REMOTE_ADDR,
     * trusted proxy headers and X-Forwarded-For.
     */
    public function getIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        $isTrusted = $this->trustedProxy?->isTrusted($remoteAddr) ?? false;

        if ($isTrusted && $this->trustedProxy !== null) {
            // 1. Cloudflare-specific header
            if (
                !empty($_SERVER['HTTP_CF_CONNECTING_IP']) &&
                $this->isValidPublicIp($_SERVER['HTTP_CF_CONNECTING_IP'])
            ) {
                return $_SERVER['HTTP_CF_CONNECTING_IP'];
            }

            // 2. Nginx-style header
            if (
                !empty($_SERVER['HTTP_X_REAL_IP']) &&
                $this->isValidPublicIp($_SERVER['HTTP_X_REAL_IP'])
            ) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }

            // 3. X-Forwarded-For list
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipList = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
                foreach (array_reverse($ipList) as $ip) {
                    // we only accept the first non-trusted AND valid IP
                    if (
                        !$this->trustedProxy->isTrusted($ip) &&
                        $this->isValidPublicIp($ip)
                    ) {
                        return $ip;
                    }
                }
            }
        } else {
            // Fallback if no trusted proxy defined
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwardedFor) {
                $ipList = array_map('trim', explode(',', $forwardedFor));
                $ipList[] = $remoteAddr;

                foreach (array_reverse($ipList) as $ip) {
                    if ($this->isValidPublicIp($ip)) {
                        return $ip;
                    }
                }
            }
        }

        // 4. Fallback to REMOTE_ADDR
        return $remoteAddr;
    }

    /**
     * Check if the IP is valid and (optionally) public (not private/reserved).
     */
    private function isValidPublicIp(string $ip): bool
    {
        $options = [];

        if ($this->filterPrivateReserved) {
            $options['flags'] = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $options) !== false;
    }
}
