<?php

declare(strict_types=1);

namespace rafalmasiarek;

use rafalmasiarek\RealIpResolver\TrustedProxyInterface;

/**
 * Resolves the real client IP address, protected against header spoofing.
 *
 * When no trusted proxy is configured, or when REMOTE_ADDR is not a trusted proxy,
 * all forwarding headers are ignored and REMOTE_ADDR is returned directly.
 *
 * When REMOTE_ADDR belongs to a trusted proxy, additional headers can be trusted
 * via explicit opt-in. X-Forwarded-For is always evaluated when trusted; all other
 * headers are disabled by default and must be enabled individually:
 *
 *   enableCloudflareHeader() → CF-Connecting-IP
 *   enableRFC7239()          → Forwarded: for= (RFC 7239), right-to-left chain
 *   enableXRealIpHeader()    → X-Real-IP
 *
 * Headers are evaluated in priority order:
 *   1. CF-Connecting-IP     (opt-in)
 *   2. Forwarded: for=      (opt-in, right-to-left)
 *   3. X-Real-IP            (opt-in)
 *   4. X-Forwarded-For      (always on, right-to-left)
 *
 * Enabling a header is safe only when the trusted proxy is known to set or
 * sanitize that header — the library cannot enforce this at the network level.
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
     * Whether to trust the CF-Connecting-IP header set by Cloudflare.
     *
     * @var bool
     */
    private bool $trustCloudflareHeader = false;

    /**
     * Whether to trust the RFC 7239 Forwarded header.
     *
     * @var bool
     */
    private bool $trustForwardedHeader = false;

    /**
     * Whether to trust the X-Real-IP header set by Nginx.
     *
     * @var bool
     */
    private bool $trustXRealIpHeader = false;

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
     * Enable trusting the CF-Connecting-IP header set by Cloudflare.
     *
     * Safe only when the trusted proxy list consists exclusively of Cloudflare
     * edge IPs, or when the infrastructure sanitizes this header for all
     * non-Cloudflare requests.
     *
     * @return void
     */
    public function enableCloudflareHeader(): void
    {
        $this->trustCloudflareHeader = true;
    }

    /**
     * Enable parsing of the RFC 7239 Forwarded header.
     *
     * Safe only when the trusted proxy is known to set or sanitize this header.
     *
     * @return void
     */
    public function enableRFC7239(): void
    {
        $this->trustForwardedHeader = true;
    }

    /**
     * Enable trusting the X-Real-IP header.
     *
     * Safe only when the trusted proxy is known to set or sanitize this header.
     *
     * @return void
     */
    public function enableXRealIpHeader(): void
    {
        $this->trustXRealIpHeader = true;
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

        // 1. CF-Connecting-IP — opt-in
        if ($this->trustCloudflareHeader && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 2. RFC 7239 Forwarded — opt-in, right-to-left chain
        if ($this->trustForwardedHeader && !empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $this->parseRFC7239($_SERVER['HTTP_FORWARDED']);
            if ($ip !== null) {
                return $ip;
            }
        }

        // 3. X-Real-IP — opt-in
        if ($this->trustXRealIpHeader && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // 4. X-Forwarded-For — always on, right-to-left, skip trusted proxies
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
     * Ports are validated to be in range 1–65535 with no leading zeros.
     *
     * @param string $value Raw node value extracted from a for= directive.
     * @return string|null Plain IP address, or null when the node is not a valid IP.
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
            if ($suffix !== '') {
                if (!preg_match('/^:(\d+)$/', $suffix, $m) || !$this->isValidPort($m[1])) {
                    return null;
                }
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
                $this->isValidPort($port)
            ) {
                return $host;
            }
        }

        return null;
    }

    /**
     * Validate a port string per RFC 7239 / HTTP syntax.
     *
     * Rejects values outside 1–65535 and strings with leading zeros.
     *
     * @param string $port Raw port string from a node identifier.
     * @return bool True when the port is a valid HTTP port number.
     */
    private function isValidPort(string $port): bool
    {
        if ($port === '' || !ctype_digit($port)) {
            return false;
        }

        if (strlen($port) > 1 && $port[0] === '0') {
            return false;
        }

        $n = (int) $port;

        return $n >= 1 && $n <= 65535;
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
