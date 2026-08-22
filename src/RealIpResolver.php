<?php

declare(strict_types=1);

namespace rafalmasiarek;

use rafalmasiarek\RealIpResolver\TrustedProxyInterface;

/**
 * Resolves the real client IP address, protected against header spoofing.
 *
 * When no trusted proxy is configured, or when REMOTE_ADDR is not a trusted proxy,
 * all forwarding headers are ignored and REMOTE_ADDR is returned directly — this
 * fully prevents spoofing via X-Forwarded-For or similar headers.
 *
 * When REMOTE_ADDR belongs to a trusted proxy, the real IP is resolved from headers
 * in the following priority order:
 *   1. CF-Connecting-IP (Cloudflare)
 *   2. Forwarded: for= (RFC 7239)
 *   3. X-Real-IP (Nginx)
 *   4. X-Forwarded-For, traversed right-to-left (first non-proxy public IP)
 *
 * @package rafalmasiarek
 */
class RealIpResolver
{
    /**
     * Trusted proxy handler, or null when none is configured.
     *
     * @var TrustedProxyInterface|null
     */
    private ?TrustedProxyInterface $trustedProxy;

    /**
     * Whether to reject private and reserved IP ranges.
     *
     * @var bool
     */
    private bool $filterPrivateReserved = true;

    /**
     * Whether to parse the RFC 7239 Forwarded header.
     *
     * @var bool
     */
    private bool $enableRFC7239 = true;

    /**
     * @param TrustedProxyInterface|null $trustedProxy Optional trusted proxy handler.
     */
    public function __construct(?TrustedProxyInterface $trustedProxy = null)
    {
        $this->trustedProxy = $trustedProxy;
    }

    /**
     * Disable filtering of private and reserved IP ranges.
     *
     * @return void
     */
    public function disablePrivateReservedFilter(): void
    {
        $this->filterPrivateReserved = false;
    }

    /**
     * Disable parsing of the RFC 7239 Forwarded header.
     *
     * @return void
     */
    public function disableRFC7239(): void
    {
        $this->enableRFC7239 = false;
    }

    /**
     * Return the real client IP address.
     *
     * Returns REMOTE_ADDR immediately when the direct peer is not a trusted proxy,
     * preventing any header-based spoofing attack.
     *
     * @return string Resolved client IP address.
     */
    public function getIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($this->trustedProxy === null || !$this->trustedProxy->isTrusted($remoteAddr)) {
            return $remoteAddr;
        }

        // 1. CF-Connecting-IP (Cloudflare sets the real client IP in this header)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if ($this->isValidPublicIp($ip)) {
                return $ip;
            }
        }

        // 2. RFC 7239 Forwarded header
        if ($this->enableRFC7239 && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $this->parseRFC7239($_SERVER['HTTP_FORWARDED']);
            if ($ip !== null) {
                return $ip;
            }
        }

        // 3. X-Real-IP (set by Nginx proxy_set_header X-Real-IP $remote_addr)
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if ($this->isValidPublicIp($ip)) {
                return $ip;
            }
        }

        // 4. X-Forwarded-For, right-to-left: skip trusted proxies, return first real client IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chain = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach (array_reverse($chain) as $ip) {
                if (!$this->trustedProxy->isTrusted($ip) && $this->isValidPublicIp($ip)) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Parse an RFC 7239 Forwarded header and return the first valid public client IP.
     *
     * Handles:
     *   Forwarded: for=192.0.2.43;proto=http
     *   Forwarded: for="[2001:db8::1]"
     *
     * @param string $header Raw Forwarded header value.
     * @return string|null Extracted IP, or null when none is valid.
     */
    private function parseRFC7239(string $header): ?string
    {
        foreach (explode(',', $header) as $entry) {
            foreach (explode(';', trim($entry)) as $directive) {
                $directive = trim($directive);

                if (stripos($directive, 'for=') !== 0) {
                    continue;
                }

                $value = trim(substr($directive, 4), " \t\n\r\0\x0B\"'");

                if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                    $value = substr($value, 1, -1);
                }

                if ($this->isValidPublicIp($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Validate an IP address, optionally rejecting private and reserved ranges.
     *
     * @param string $ip IP address to validate.
     * @return bool True when the IP is valid and (if filtering is on) publicly routable.
     */
    private function isValidPublicIp(string $ip): bool
    {
        $options = $this->filterPrivateReserved
            ? ['flags' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE]
            : [];

        return filter_var($ip, FILTER_VALIDATE_IP, $options) !== false;
    }
}
