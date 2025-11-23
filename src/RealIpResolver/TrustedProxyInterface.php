<?php

namespace rafalmasiarek\RealIpResolver;

interface TrustedProxyInterface
{
    public function isTrusted(string $ip): bool;
}
