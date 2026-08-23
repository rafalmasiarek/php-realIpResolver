# RealIpResolver

A lightweight PHP library to resolve the **real client IP address** behind proxies and load balancers, hardened against header spoofing.

## Security model

The fundamental rule: a forwarding header is trusted only when you know that the proxy delivering the request both **sets** and **sanitizes** that header.

- When **no trusted proxy is configured**, or when **REMOTE_ADDR is not in the trusted list**, all forwarding headers are ignored — REMOTE_ADDR is returned directly.
- All forwarding headers — including `X-Forwarded-For` — are **disabled by default** and require an explicit opt-in call.

Default behaviour per header:

| Header             | Default   | Enable via                        |
|--------------------|-----------|-----------------------------------|
| `X-Forwarded-For`  | off       | `enableXForwardedForHeader()`     |
| `CF-Connecting-IP` | off       | `enableCloudflareHeader()`        |
| `Forwarded`        | off       | `enableRFC7239()`                 |
| `X-Real-IP`        | off       | `enableXRealIpHeader()`           |

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

Pass a `TrustedProxy` instance populated with IP addresses or CIDR ranges, then enable the headers your proxy actually sets:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;

// Cloudflare setup: REMOTE_ADDR will always be a Cloudflare edge node
$resolver = new RealIpResolver(new TrustedProxy(Cloudflare::get()));
$resolver->enableCloudflareHeader(); // trust CF-Connecting-IP

echo $resolver->getIp();
```

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Nginx;

// Nginx setup with proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for
Nginx::import(['10.0.0.1']);
$resolver = new RealIpResolver(new TrustedProxy(Nginx::get()));
$resolver->enableXForwardedForHeader();

echo $resolver->getIp();
```

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Nginx;

// Nginx setup with proxy_set_header X-Real-IP $remote_addr
Nginx::import(['10.0.0.1']);
$resolver = new RealIpResolver(new TrustedProxy(Nginx::get()));
$resolver->enableXRealIpHeader();

echo $resolver->getIp();
```

`TrustedProxy` accepts exact IPs and CIDR notation for both IPv4 (`173.245.48.0/20`) and IPv6 (`2400:cb00::/32`).

## Header priority

When REMOTE_ADDR is trusted, headers are evaluated in this order (first match wins):

1. `CF-Connecting-IP` — opt-in via `enableCloudflareHeader()`
2. `Forwarded: for=` — opt-in via `enableRFC7239()`, right-to-left chain traversal
3. `X-Real-IP` — opt-in via `enableXRealIpHeader()`
4. `X-Forwarded-For` — opt-in via `enableXForwardedForHeader()`, right-to-left chain traversal

## When is each opt-in safe?

**`enableCloudflareHeader()`** — safe when every request reaching PHP passes through Cloudflare. Cloudflare sets `CF-Connecting-IP` to the original client IP and cannot be spoofed by the client.

**`enableRFC7239()`** — safe when the trusted proxy explicitly sets the `Forwarded` header and your infrastructure strips any client-supplied `Forwarded` headers before they reach PHP.

**`enableXRealIpHeader()`** — safe when Nginx is configured with `proxy_set_header X-Real-IP $remote_addr` and strips any client-supplied `X-Real-IP` header.

**`enableXForwardedForHeader()`** — safe when the trusted proxy appends the connecting client's IP to the right end of the chain (`proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;` in Nginx). Right-to-left traversal skips trusted proxy entries and stops at the first non-trusted IP — client-injected values appear to the left of the real client IP and are never returned.

In all cases: enable only headers that your proxy is known to set or sanitize. When in doubt, leave the header disabled.

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

## All options

```php
// Enable X-Forwarded-For header (right-to-left chain traversal)
$resolver->enableXForwardedForHeader();

// Enable CF-Connecting-IP (Cloudflare)
$resolver->enableCloudflareHeader();

// Enable RFC 7239 Forwarded header parsing
$resolver->enableRFC7239();

// Enable X-Real-IP header (Nginx)
$resolver->enableXRealIpHeader();

// Allow private and reserved IP ranges (e.g. for internal networks or tests)
$resolver->disablePrivateReservedFilter();
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
