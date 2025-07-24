<?php

namespace rafalmasiarek\Http\RealIpResolver;

interface TrustedProxyInterface
{
    public function isTrusted(string $ip): bool;
}
