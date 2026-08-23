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
 *   1. CF-Connecting-IP (Cloudflare) — opt-in only via enableCloudflareHeader()
 *   2. Forwarded: for= (RFC 7239), traversed right-to-left, skipping trusted proxies
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
     * Whether to trust the CF-Connecting-IP header set by Cloudflare.
     *
     * Disabled by default. Enable only when the trusted proxy list consists
     * exclusively of Cloudflare edge IPs, or when the proxy infrastructure
     * guarantees that this header is stripped for non-Cloudflare requests.
     *
     * @var bool
     */
    private bool $trustCloudflareHeader = false;

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
     * Enable trusting the CF-Connecting-IP header set by Cloudflare.
     *
     * Call this only when REMOTE_ADDR is guaranteed to always be a Cloudflare
     * edge node, or when the proxy infrastructure sanitizes this header for
     * requests that do not originate from Cloudflare.
     *
     * @return void
     */
    public function enableCloudflareHeader(): void
    {
        $this->trustCloudflareHeader = true;
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
        $remoteAddr = trim($_SERVER['REMOTE_ADDR'] ?? '');

        if ($this->trustedProxy === null || !$this->trustedProxy->isTrusted($remoteAddr)) {
            return $remoteAddr;
        }

        // 1. CF-Connecting-IP — opt-in; safe only when proxy is exclusively Cloudflare
        if ($this->trustCloudflareHeader && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 2. RFC 7239 Forwarded — right-to-left, skipping trusted proxies
        if ($this->enableRFC7239 && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $this->parseRFC7239($_SERVER['HTTP_FORWARDED']);
            if ($ip !== null) {
                return $ip;
            }
        }

        // 3. X-Real-IP (set by Nginx proxy_set_header X-Real-IP $remote_addr)
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 4. X-Forwarded-For — right-to-left: skip trusted proxies, return first real client IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chain = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach (array_reverse($chain) as $ip) {
                if (!$this->trustedProxy->isTrusted($ip) && $this->isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Parse an RFC 7239 Forwarded header and return the real client IP.
     *
     * Collects all for= node values in chain order, then traverses them
     * right-to-left, skipping trusted proxy IPs — consistent with the
     * X-Forwarded-For security model.
     *
     * @param string $header Raw Forwarded header value.
     * @return string|null Extracted IP, or null when none is valid.
     */
    private function parseRFC7239(string $header): ?string
    {
        $ips = [];

        foreach (explode(',', $header) as $entry) {
            foreach (explode(';', trim($entry)) as $directive) {
                $directive = trim($directive);

                if (stripos($directive, 'for=') !== 0) {
                    continue;
                }

                $value = trim(substr($directive, 4), " \t\n\r\0\x0B\"'");
                $ip    = $this->normalizeRFC7239Node($value);

                if ($ip !== null) {
                    $ips[] = $ip;
                }

                break;
            }
        }

        foreach (array_reverse($ips) as $ip) {
            if (!$this->trustedProxy->isTrusted($ip) && $this->isValidIp($ip)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Normalize an RFC 7239 node identifier to a plain IP address string.
     *
     * Handles the following node forms per RFC 7239 §6:
     *   192.0.2.60              plain IPv4
     *   192.0.2.60:47011        IPv4 with port
     *   [2001:db8::1]           IPv6 literal
     *   [2001:db8::1]:4711      IPv6 literal with port
     *   unknown / _token        returns null (not an IP)
     *
     * @param string $value Raw node value extracted from a for= directive.
     * @return string|null Plain IP address, or null when the node is not an IP.
     */
    private function normalizeRFC7239Node(string $value): ?string
    {
        // IPv6 literal: [addr] or [addr]:port
        if (str_starts_with($value, '[')) {
            $end = strpos($value, ']');
            if ($end === false) {
                return null;
            }

            $suffix = substr($value, $end + 1);
            if ($suffix !== '' && !preg_match('/^:\d+$/', $suffix)) {
                return null;
            }

            return substr($value, 1, $end - 1);
        }

        // Plain IPv4 or IPv6 without port
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return $value;
        }

        // IPv4 with port: 192.0.2.60:47011
        if (substr_count($value, ':') === 1) {
            [$host, $port] = explode(':', $value, 2);
            if (
                filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false &&
                ctype_digit($port)
            ) {
                return $host;
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
    private function isValidIp(string $ip): bool
    {
        $options = $this->filterPrivateReserved
            ? ['flags' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE]
            : [];

        return filter_var($ip, FILTER_VALIDATE_IP, $options) !== false;
    }
}
