<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;

/**
 * Tests for RealIpResolver and TrustedProxy.
 *
 * Test IPs used:
 *   Proxy / trusted  → private ranges (192.168.x.x, 10.x.x.x) or Cloudflare CIDR IPs.
 *                      No public filter is applied to proxy addresses.
 *   Client (in headers) → globally routable public IPs (1.1.1.1, 8.8.8.8, 9.9.9.9…).
 *                         Must pass FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE.
 *
 * @package Tests
 */
class RealIpResolverTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_FORWARDED'],
        );
    }

    // -------------------------------------------------------------------------
    // Spoofing prevention
    // -------------------------------------------------------------------------

    /**
     * With no trusted proxy, XFF must be ignored — REMOTE_ADDR returned directly.
     */
    public function testNoTrustedProxyIgnoresXff(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertSame('8.8.8.8', (new RealIpResolver())->getIp());
    }

    /**
     * With an empty trusted proxy list, XFF must still be ignored.
     */
    public function testEmptyTrustedProxyIgnoresXff(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertSame('8.8.8.8', (new RealIpResolver(new TrustedProxy([])))->getIp());
    }

    /**
     * When REMOTE_ADDR is not in the trusted list, XFF must be ignored.
     */
    public function testUntrustedRemoteAddrIgnoresXff(): void
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertSame('8.8.8.8', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    // -------------------------------------------------------------------------
    // CIDR matching
    // -------------------------------------------------------------------------

    /**
     * Exact IP in trusted list resolves XFF.
     */
    public function testExactIpMatch(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * IPv4 CIDR match allows trusted resolution via X-Real-IP.
     */
    public function testCidrMatchIPv4(): void
    {
        $_SERVER['REMOTE_ADDR'] = '173.245.48.10';
        $_SERVER['HTTP_X_REAL_IP'] = '1.1.1.1';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['173.245.48.0/20'])))->getIp());
    }

    /**
     * IPv4 CIDR boundary conditions.
     */
    public function testCidrBoundaryIPv4(): void
    {
        $proxy = new TrustedProxy(['173.245.48.0/20']);

        $this->assertTrue($proxy->isTrusted('173.245.48.0'));
        $this->assertTrue($proxy->isTrusted('173.245.48.1'));
        $this->assertTrue($proxy->isTrusted('173.245.63.254'));
        $this->assertTrue($proxy->isTrusted('173.245.63.255'));
        $this->assertFalse($proxy->isTrusted('173.245.64.0'));
        $this->assertFalse($proxy->isTrusted('173.245.47.255'));
    }

    /**
     * IPv6 CIDR match allows trusted resolution via X-Real-IP.
     */
    public function testCidrMatchIPv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2400:cb00::1';
        $_SERVER['HTTP_X_REAL_IP'] = '1.1.1.1';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['2400:cb00::/32'])))->getIp());
    }

    /**
     * IPv6 CIDR boundary conditions.
     */
    public function testCidrBoundaryIPv6(): void
    {
        $proxy = new TrustedProxy(['2400:cb00::/32']);

        $this->assertTrue($proxy->isTrusted('2400:cb00::1'));
        $this->assertTrue($proxy->isTrusted('2400:cb00:ffff:ffff:ffff:ffff:ffff:ffff'));
        $this->assertFalse($proxy->isTrusted('2400:cb01::1'));
        $this->assertFalse($proxy->isTrusted('2400:caff::1'));
    }

    /**
     * IPv4/IPv6 family mismatch never matches.
     */
    public function testCidrFamilyMismatch(): void
    {
        $proxy = new TrustedProxy(['173.245.48.0/20']);
        $this->assertFalse($proxy->isTrusted('2400:cb00::1'));
    }

    // -------------------------------------------------------------------------
    // CF-Connecting-IP — opt-in
    // -------------------------------------------------------------------------

    /**
     * CF-Connecting-IP is ignored by default (not trusted without explicit opt-in).
     */
    public function testCfConnectingIpDisabledByDefault(): void
    {
        $_SERVER['REMOTE_ADDR'] = '173.245.48.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';
        $_SERVER['HTTP_X_REAL_IP'] = '8.8.8.8';

        $resolver = new RealIpResolver(new TrustedProxy(['173.245.48.0/20']));
        // CF-Connecting-IP disabled → falls through to X-Real-IP
        $this->assertSame('8.8.8.8', $resolver->getIp());
    }

    /**
     * CF-Connecting-IP takes priority over other headers when explicitly enabled.
     */
    public function testCfConnectingIpWhenEnabled(): void
    {
        $_SERVER['REMOTE_ADDR'] = '173.245.48.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';
        $_SERVER['HTTP_X_REAL_IP'] = '8.8.8.8';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';

        $resolver = new RealIpResolver(new TrustedProxy(['173.245.48.0/20']));
        $resolver->enableCloudflareHeader();
        $this->assertSame('1.1.1.1', $resolver->getIp());
    }

    // -------------------------------------------------------------------------
    // Header priority
    // -------------------------------------------------------------------------

    /**
     * X-Real-IP is used when CF-Connecting-IP is absent or disabled.
     */
    public function testXRealIpFallback(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_REAL_IP'] = '8.8.8.8';

        $this->assertSame('8.8.8.8', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * XFF is traversed right-to-left: trusted proxies at the tail are skipped.
     */
    public function testXffRightToLeft(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 192.168.1.1';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * Attacker-prepended IP in XFF must not be returned (right-to-left stops at real client).
     */
    public function testXffSpoofingPrevention(): void
    {
        // Attacker sends: X-Forwarded-For: 9.9.9.9, 1.1.1.1, <proxy>
        // Right-to-left: skip 192.168.1.1 (trusted), return 1.1.1.1 (real client).
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9, 1.1.1.1, 192.168.1.1';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    // -------------------------------------------------------------------------
    // RFC 7239 Forwarded header
    // -------------------------------------------------------------------------

    /**
     * RFC 7239 for= directive is parsed correctly.
     */
    public function testRfc7239ForwardedHeader(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=1.1.1.1;proto=https';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * RFC 7239 IPv6 address in brackets is extracted correctly.
     */
    public function testRfc7239IPv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for="[2606:4700::1]"';

        $this->assertSame('2606:4700::1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * RFC 7239 chain is traversed right-to-left, skipping trusted proxies.
     */
    public function testRfc7239ChainRightToLeft(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        // chain order: client → proxy1 → proxy2 (= REMOTE_ADDR)
        $_SERVER['HTTP_FORWARDED'] = 'for=1.1.1.1, for=192.168.1.2, for=192.168.1.1';

        $resolver = new RealIpResolver(new TrustedProxy(['192.168.1.1', '192.168.1.2']));
        // Right-to-left: skip 192.168.1.1 (trusted), skip 192.168.1.2 (trusted), return 1.1.1.1
        $this->assertSame('1.1.1.1', $resolver->getIp());
    }

    /**
     * RFC 7239 IPv4 node with port is normalized correctly.
     */
    public function testRfc7239WithIPv4Port(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=1.1.1.1:54321';

        $this->assertSame('1.1.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * RFC 7239 IPv6 node with port is normalized correctly.
     */
    public function testRfc7239WithIPv6Port(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for="[2606:4700::1]:4711"';

        $this->assertSame('2606:4700::1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * RFC 7239 IPv6 node with invalid suffix is rejected by the parser.
     */
    public function testRfc7239IPv6InvalidSuffixRejected(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        // '[::1]garbage' is not a valid RFC 7239 node — suffix after ] is not :port
        $_SERVER['HTTP_FORWARDED'] = 'for="[2606:4700::1]garbage"';
        $_SERVER['HTTP_X_REAL_IP'] = '8.8.8.8';

        // Malformed Forwarded node must be discarded; resolver falls through to X-Real-IP
        $this->assertSame('8.8.8.8', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * Disabling RFC 7239 causes the resolver to skip the Forwarded header.
     */
    public function testDisableRfc7239(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=1.1.1.1';
        $_SERVER['HTTP_X_REAL_IP'] = '8.8.8.8';

        $resolver = new RealIpResolver(new TrustedProxy(['192.168.1.1']));
        $resolver->disableRFC7239();

        $this->assertSame('8.8.8.8', $resolver->getIp());
    }

    // -------------------------------------------------------------------------
    // Private / reserved IP filtering
    // -------------------------------------------------------------------------

    /**
     * A private client IP in XFF is filtered, resolver falls back to REMOTE_ADDR.
     */
    public function testPrivateClientIpFiltered(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.5';

        $this->assertSame('192.168.1.1', (new RealIpResolver(new TrustedProxy(['192.168.1.1'])))->getIp());
    }

    /**
     * Disabling the private filter allows private IPs to be returned.
     */
    public function testDisablePrivateFilter(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.5';

        $resolver = new RealIpResolver(new TrustedProxy(['192.168.1.1']));
        $resolver->disablePrivateReservedFilter();

        $this->assertSame('10.0.0.5', $resolver->getIp());
    }

    // -------------------------------------------------------------------------
    // Cloudflare::import()
    // -------------------------------------------------------------------------

    /**
     * Cloudflare::import() overrides the list for testing without filesystem access.
     */
    public function testCloudflareImport(): void
    {
        Cloudflare::import(['192.168.100.0/24']);
        $proxy = new TrustedProxy(Cloudflare::get());

        $this->assertTrue($proxy->isTrusted('192.168.100.50'));
        $this->assertFalse($proxy->isTrusted('192.168.101.1'));
    }
}
