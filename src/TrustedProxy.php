<?php

namespace rafalmasiarek\Http\RealIpResolver;

class TrustedProxy implements TrustedProxyInterface
{
    private array $trustedIps;

    public function __construct(array $trustedIps)
    {
        $this->trustedIps = $trustedIps;
    }

    public function isTrusted(string $ip): bool
    {
        return in_array($ip, $this->trustedIps, true);
    }
}
