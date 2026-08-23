# RealIpResolver

A lightweight PHP library to resolve the **real client IP address** behind proxies and load balancers, hardened against header spoofing.

## Security model

- When **no trusted proxy is configured**, or when **REMOTE_ADDR is not in the trusted list**, all forwarding headers (`X-Forwarded-For`, `CF-Connecting-IP`, etc.) are ignored entirely — REMOTE_ADDR is returned directly. This prevents any header-based IP spoofing.
- Forwarding headers are trusted **only** when REMOTE_ADDR is a verified trusted proxy.
- `X-Forwarded-For` and `Forwarded` (RFC 7239) are traversed **right-to-left** to skip trusted intermediaries and return the first real client IP.
- `CF-Connecting-IP` is **disabled by default** — it must be enabled explicitly via `enableCloudflareHeader()`.

## Installation

```
composer require rafalmasiarek/real-ip-resolver
```

## Requirements

PHP 8.1 or later.

## Basic usage

Without any proxy, REMOTE_ADDR is returned directly:

```php
use rafalmasiarek\RealIpResolver;

$resolver = new RealIpResolver();
echo $resolver->getIp();
```

## With trusted proxies

Pass a `TrustedProxy` instance populated with IP addresses or CIDR ranges:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;
use rafalmasiarek\RealIpResolver\IPLists\Localhost;

$trustedProxy = new TrustedProxy(array_merge(
    Localhost::get(),
    Cloudflare::get(),
));

$resolver = new RealIpResolver($trustedProxy);
echo $resolver->getIp();
```

`TrustedProxy` accepts exact IPs and CIDR notation for both IPv4 (`173.245.48.0/20`) and IPv6 (`2400:cb00::/32`).

## Header priority

When REMOTE_ADDR is trusted, headers are evaluated in this order:

1. `CF-Connecting-IP` — Cloudflare, **opt-in only** via `enableCloudflareHeader()`
2. `Forwarded: for=` — RFC 7239, right-to-left chain traversal
3. `X-Real-IP` — Nginx
4. `X-Forwarded-For` — right-to-left, first non-proxy public IP

## Cloudflare setup

`CF-Connecting-IP` must be explicitly enabled. It is safe to enable when your trusted proxy list consists exclusively of Cloudflare edge IPs, because REMOTE_ADDR must already match a Cloudflare range for headers to be read at all:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;

$resolver = new RealIpResolver(new TrustedProxy(Cloudflare::get()));
$resolver->enableCloudflareHeader();

echo $resolver->getIp();
```

If your trusted proxy list is a **mix** (e.g. Cloudflare + a local Nginx), leave `CF-Connecting-IP` disabled — Nginx does not set it, and a client connecting directly to Nginx could spoof it if Nginx does not strip the header.

## Built-in IP list providers

### Cloudflare

```php
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;

// Returns CIDR ranges from the local cache file (src/RealIpResolver/data/cloudflare.txt)
$ips = Cloudflare::get();

// Download fresh ranges from Cloudflare's official endpoints and update the local file
Cloudflare::updateList();
```

A GitHub Actions workflow (`update-cloudflare.yml`) is included to refresh the list automatically on the 1st of each month.

### Localhost

```php
use rafalmasiarek\RealIpResolver\IPLists\Localhost;

// Returns ['127.0.0.1', '::1']
$ips = Localhost::get();
```

### Nginx

For a local Nginx reverse proxy with deployment-specific IPs:

```php
use rafalmasiarek\RealIpResolver\IPLists\Nginx;

Nginx::import(['10.0.0.1', '10.0.0.2']);
$ips = Nginx::get();
```

## Custom IP list

Implement `IpListInterface` to define your own provider:

```php
use rafalmasiarek\RealIpResolver\IPLists\IpListInterface;

class MyProxy implements IpListInterface
{
    public static function get(): array
    {
        return ['203.0.113.0/24', '2001:db8::/32'];
    }
}
```

## PSR-15 middleware

Install the optional dependency:

```
composer require psr/http-server-middleware
```

Then register the middleware:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;
use rafalmasiarek\RealIpResolver\Middleware\RealIpResolverMiddleware;

$resolver = new RealIpResolver(new TrustedProxy(Cloudflare::get()));
$resolver->enableCloudflareHeader();
$app->add(new RealIpResolverMiddleware($resolver));

// In a route handler:
$realIp = $request->getAttribute('real_ip');
```

## Options

```php
// Enable CF-Connecting-IP (safe when proxy list is exclusively Cloudflare IPs)
$resolver->enableCloudflareHeader();

// Allow private and reserved IP ranges (e.g. for internal networks or tests)
$resolver->disablePrivateReservedFilter();

// Skip RFC 7239 Forwarded header parsing
$resolver->disableRFC7239();
```

## Namespace change in 1.2.0

The namespace was flattened in 1.2.0. Old imports:

```php
use rafalmasiarek\Http\RealIpResolver\RealIpResolver;
```

New imports:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;
```

## License

MIT
