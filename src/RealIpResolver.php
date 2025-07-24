<?php

namespace rafalmasiarek\Http\RealIpResolver;

class RealIpResolver
{
    private ?TrustedProxyInterface $trustedProxy;

    public function __construct(?TrustedProxyInterface $trustedProxy = null)
    {
        $this->trustedProxy = $trustedProxy;
    }

    public function getIp(): string
    {
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!$forwardedFor) {
            return $remoteAddr;
        }

        $ipList = array_map('trim', explode(',', $forwardedFor));
        $ipList[] = $remoteAddr;

        foreach (array_reverse($ipList) as $ip) {
            if (!$this->trustedProxy || !$this->trustedProxy->isTrusted($ip)) {
                return $ip;
            }
        }

        return $remoteAddr;
    }
}
