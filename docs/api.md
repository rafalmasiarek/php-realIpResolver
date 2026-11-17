## Index

### Classes

- [`Cloudflare`](#cloudflare) — `rafalmasiarek\RealIpResolver\IPLists`
- [`Localhost`](#localhost) — `rafalmasiarek\RealIpResolver\IPLists`
- [`Nginx`](#nginx) — `rafalmasiarek\RealIpResolver\IPLists`
- [`RealIpResolverMiddleware`](#realipresolvermiddleware) — `rafalmasiarek\RealIpResolver\Middleware`
- [`TrustedProxy`](#trustedproxy) — `rafalmasiarek\RealIpResolver`
- [`RealIpResolver`](#realipresolver) — `rafalmasiarek`

### Interfaces

- [`IpListInterface`](#iplistinterface) — `rafalmasiarek\RealIpResolver\IPLists`
- [`TrustedProxyInterface`](#trustedproxyinterface) — `rafalmasiarek\RealIpResolver`

## `Cloudflare`

*class* — `rafalmasiarek\RealIpResolver\IPLists\Cloudflare` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Cloudflare.php#L18)

Provides Cloudflare edge server IP ranges for use as trusted proxy sources. Ranges are read from a local cache file and can be refreshed from Cloudflare's official IP list endpoints. An import() method allows overriding the list in-memory, which is useful in tests. setFile() allows relocating the cache file, which is useful for applications where the package install directory does not persist across deploys.

implements [`IpListInterface`](#iplistinterface)

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$cache` | `string[]\|null` | private static | In-memory cache of loaded IP ranges. |
| `$file` | `string` | private static | Absolute path to the local IP list cache file. Override via setFile(). |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`get()`](#cloudflare-get) | public static | `string[]` | Return the list of Cloudflare CIDR ranges from the local cache file. When $path is given, it is read instead of the configured file and the in-memory cache is bypassed entirely — the cache only ever holds the default file's content, so a per-call path would otherwise return another call's cached result. |
| [`updateList()`](#cloudflare-updatelist) | public static | `bool` | Download fresh Cloudflare IP ranges from official endpoints and overwrite the local cache file. The file is left untouched when the downloaded list is identical to what is already on disk, so its mtime only changes on an actual update. When $path is given, it is written instead of the configured file; the in-memory cache — which only ever reflects the default file — is reset only when writing to that default. |
| [`import()`](#cloudflare-import) | public static | `void` | Override the IP list in-memory, bypassing the local cache file. Intended for use in tests to avoid filesystem access. |
| [`setFile()`](#cloudflare-setfile) | public static | `void` | Relocate the local cache file used by get() and updateList(). Useful when the package install directory does not persist across deploys (e.g. a `composer install` that rebuilds vendor/ on every release), so the cache should live under an application-managed, persistent storage path instead. |

<details>
<summary><code>get()</code></summary>

#### `Cloudflare get()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Cloudflare.php#L45)

```php
public static function get(?string $path): array
```

Return the list of Cloudflare CIDR ranges from the local cache file. When $path is given, it is read instead of the configured file and the in-memory cache is bypassed entirely — the cache only ever holds the default file's content, so a per-call path would otherwise return another call's cached result.

| Param | Type | Description |
|---|---|---|
| `$path` | `string\|null` | Cache file to read instead of the configured default. |

**Returns:** `string[]`

</details>

<details>
<summary><code>updateList()</code></summary>

#### `Cloudflare updateList()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Cloudflare.php#L81)

```php
public static function updateList(?string $path): bool
```

Download fresh Cloudflare IP ranges from official endpoints and overwrite the local cache file. The file is left untouched when the downloaded list is identical to what is already on disk, so its mtime only changes on an actual update. When $path is given, it is written instead of the configured file; the in-memory cache — which only ever reflects the default file — is reset only when writing to that default.

| Param | Type | Description |
|---|---|---|
| `$path` | `string\|null` | Cache file to write instead of the configured default. |

**Returns:** `bool` — True when the file was written (new or changed), false when the downloaded list was identical to what was already on disk.

| Throws | When |
|---|---|
| `\RuntimeException` | When both the IPv4 and IPv6 endpoints are unreachable. |

</details>

<details>
<summary><code>import()</code></summary>

#### `Cloudflare import()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Cloudflare.php#L124)

```php
public static function import(array $ips): void
```

Override the IP list in-memory, bypassing the local cache file. Intended for use in tests to avoid filesystem access.

| Param | Type | Description |
|---|---|---|
| `$ips` | `string[]` | List of IP addresses or CIDR ranges. |

</details>

<details>
<summary><code>setFile()</code></summary>

#### `Cloudflare setFile()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Cloudflare.php#L140)

```php
public static function setFile(string $path): void
```

Relocate the local cache file used by get() and updateList(). Useful when the package install directory does not persist across deploys (e.g. a `composer install` that rebuilds vendor/ on every release), so the cache should live under an application-managed, persistent storage path instead.

| Param | Type | Description |
|---|---|---|
| `$path` | `string` | Absolute path to the cache file. |

</details>

## `IpListInterface`

*interface* — `rafalmasiarek\RealIpResolver\IPLists\IpListInterface` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/IpListInterface.php#L14)

Contract for IP address list providers used as trusted proxy sources. Implementations may return plain IP addresses or CIDR notation ranges.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`get()`](#iplistinterface-get) | public static | `string[]` | Return the list of IP addresses or CIDR ranges. |

<details>
<summary><code>get()</code></summary>

#### `IpListInterface get()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/IpListInterface.php#L21)

```php
public static function get(): array
```

Return the list of IP addresses or CIDR ranges.

**Returns:** `string[]` — Array of IP addresses or CIDR blocks (e.g. '173.245.48.0/20').

</details>

## `Localhost`

*class* — `rafalmasiarek\RealIpResolver\IPLists\Localhost` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Localhost.php#L15)

Provides loopback addresses as trusted proxy sources. Useful when the application runs behind a local reverse proxy (e.g. Nginx on the same host).

implements [`IpListInterface`](#iplistinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`get()`](#localhost-get) | public static | `string[]` | Return standard loopback addresses for IPv4 and IPv6. |

<details>
<summary><code>get()</code></summary>

#### `Localhost get()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Localhost.php#L22)

```php
public static function get(): array
```

Return standard loopback addresses for IPv4 and IPv6.

**Returns:** `string[]`

</details>

## `Nginx`

*class* — `rafalmasiarek\RealIpResolver\IPLists\Nginx` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Nginx.php#L15)

Provides a configurable list of trusted Nginx reverse proxy IP addresses. Unlike Cloudflare, Nginx proxy IPs are deployment-specific and must be supplied via import() before use.

implements [`IpListInterface`](#iplistinterface)

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$cache` | `string[]\|null` | private static | In-memory list of trusted Nginx proxy IPs or CIDR ranges. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`get()`](#nginx-get) | public static | `string[]` | Return the configured list of Nginx proxy IPs or CIDR ranges. |
| [`import()`](#nginx-import) | public static | `void` | Set the list of trusted Nginx proxy IPs or CIDR ranges. |

<details>
<summary><code>get()</code></summary>

#### `Nginx get()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Nginx.php#L29)

```php
public static function get(): array
```

Return the configured list of Nginx proxy IPs or CIDR ranges.

**Returns:** `string[]`

</details>

<details>
<summary><code>import()</code></summary>

#### `Nginx import()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/IPLists/Nginx.php#L40)

```php
public static function import(array $ips): void
```

Set the list of trusted Nginx proxy IPs or CIDR ranges.

| Param | Type | Description |
|---|---|---|
| `$ips` | `string[]` | List of IP addresses or CIDR ranges. |

</details>

## `RealIpResolverMiddleware`

*class* — `rafalmasiarek\RealIpResolver\Middleware\RealIpResolverMiddleware` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/Middleware/RealIpResolverMiddleware.php#L21)

PSR-15 middleware that resolves the real client IP and injects it into the request attributes as 'real_ip'. Requires psr/http-server-middleware (listed as a suggested dependency).

implements `MiddlewareInterface`

**Depends on:** [`RealIpResolver`](#realipresolver)

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$resolver` | [`RealIpResolver`](#realipresolver) | private | The resolver used to determine the real client IP. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#realipresolvermiddleware-__construct) | public | — | — |
| [`process()`](#realipresolvermiddleware-process) | public | `ResponseInterface` | Resolve the real client IP and inject it into request attributes. |

<details>
<summary><code>__construct()</code></summary>

#### `RealIpResolverMiddleware __construct()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/Middleware/RealIpResolverMiddleware.php#L33)

```php
public function __construct(RealIpResolver $resolver)
```

| Param | Type | Description |
|---|---|---|
| `$resolver` | [`RealIpResolver`](#realipresolver) | Configured resolver instance. |

</details>

<details>
<summary><code>process()</code></summary>

#### `RealIpResolverMiddleware process()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/Middleware/RealIpResolverMiddleware.php#L45)

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

Resolve the real client IP and inject it into request attributes.

| Param | Type | Description |
|---|---|---|
| `$request` | `ServerRequestInterface` | Current HTTP request. |
| `$handler` | `RequestHandlerInterface` | Next middleware handler. |

**Returns:** `ResponseInterface`

</details>

## `TrustedProxy`

*class* — `rafalmasiarek\RealIpResolver\TrustedProxy` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxy.php#L15)

Validates whether an IP address belongs to a trusted proxy range. Accepts exact IP addresses and CIDR notation for both IPv4 and IPv6 (e.g. 173.245.48.0/20, 2400:cb00::/32).

implements [`TrustedProxyInterface`](#trustedproxyinterface)

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$trustedIps` | `string[]` | private | Trusted IP addresses or CIDR ranges. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#trustedproxy-__construct) | public | — | — |
| [`isTrusted()`](#trustedproxy-istrusted) | public | `bool` | Check whether the given IP address is trusted. Supports both exact string matches and CIDR range matching. |
| [`ipInCidr()`](#trustedproxy-ipincidr) | private | `bool` | Check whether an IP address falls within a CIDR block. Uses inet_pton() for binary byte-level comparison, which handles both IPv4 (4-byte) and IPv6 (16-byte) addresses uniformly. |

<details>
<summary><code>__construct()</code></summary>

#### `TrustedProxy __construct()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxy.php#L27)

```php
public function __construct(array $trustedIps)
```

| Param | Type | Description |
|---|---|---|
| `$trustedIps` | `string[]` | Trusted IP addresses or CIDR ranges. |

</details>

<details>
<summary><code>isTrusted()</code></summary>

#### `TrustedProxy isTrusted()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxy.php#L40)

```php
public function isTrusted(string $ip): bool
```

Check whether the given IP address is trusted. Supports both exact string matches and CIDR range matching.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IP address to check. |

**Returns:** `bool` — True if the IP matches any trusted entry.

</details>

<details>
<summary><code>ipInCidr()</code></summary>

#### `TrustedProxy ipInCidr()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxy.php#L65)

```php
private function ipInCidr(string $ip, string $cidr): bool
```

Check whether an IP address falls within a CIDR block. Uses inet_pton() for binary byte-level comparison, which handles both IPv4 (4-byte) and IPv6 (16-byte) addresses uniformly.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IP address to test. |
| `$cidr` | `string` | CIDR block, e.g. 173.245.48.0/20 or 2400:cb00::/32. |

**Returns:** `bool` — True if the IP is within the block.

</details>

## `TrustedProxyInterface`

*interface* — `rafalmasiarek\RealIpResolver\TrustedProxyInterface` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxyInterface.php#L12)

Contract for trusted proxy validators.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`isTrusted()`](#trustedproxyinterface-istrusted) | public | `bool` | Determine whether the given IP address belongs to a trusted proxy. |

<details>
<summary><code>isTrusted()</code></summary>

#### `TrustedProxyInterface isTrusted()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver/TrustedProxyInterface.php#L20)

```php
public function isTrusted(string $ip): bool
```

Determine whether the given IP address belongs to a trusted proxy.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IP address to check. |

**Returns:** `bool`

</details>

## `RealIpResolver`

*class* — `rafalmasiarek\RealIpResolver` · [source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L35)

Resolves the real client IP address, protected against header spoofing. When no trusted proxy is configured, or when REMOTE_ADDR is not a trusted proxy, all forwarding headers are ignored and REMOTE_ADDR is returned directly. When REMOTE_ADDR belongs to a trusted proxy, additional headers can be trusted via explicit opt-in. All forwarding headers are disabled by default and must be enabled individually: enableCloudflareHeader()    → CF-Connecting-IP enableRFC7239()             → Forwarded: for= (RFC 7239), right-to-left chain enableXRealIpHeader()       → X-Real-IP enableXForwardedForHeader() → X-Forwarded-For, right-to-left chain Headers are evaluated in priority order: 1. CF-Connecting-IP     (opt-in) 2. Forwarded: for=      (opt-in, right-to-left) 3. X-Real-IP            (opt-in) 4. X-Forwarded-For      (opt-in, right-to-left) Enabling a header is safe only when the trusted proxy is known to set or sanitize that header — the library cannot enforce this at the network level.

**Depends on:** [`TrustedProxyInterface`](#trustedproxyinterface)

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$trustedProxy` | `TrustedProxyInterface\|null` | private | Trusted proxy handler, or null when none is configured. |
| `$filterPrivateReserved` | `bool` | private | Whether to reject private and reserved IP ranges. |
| `$trustCloudflareHeader` | `bool` | private | Whether to trust the CF-Connecting-IP header set by Cloudflare. |
| `$trustForwardedHeader` | `bool` | private | Whether to trust the RFC 7239 Forwarded header. |
| `$trustXRealIpHeader` | `bool` | private | Whether to trust the X-Real-IP header set by Nginx. |
| `$trustXForwardedForHeader` | `bool` | private | Whether to trust the X-Forwarded-For header. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#realipresolver-__construct) | public | — | — |
| [`disablePrivateReservedFilter()`](#realipresolver-disableprivatereservedfilter) | public | `void` | Disable filtering of private and reserved IP ranges. |
| [`enableCloudflareHeader()`](#realipresolver-enablecloudflareheader) | public | `void` | Enable trusting the CF-Connecting-IP header set by Cloudflare. Safe only when the trusted proxy list consists exclusively of Cloudflare edge IPs, or when the infrastructure sanitizes this header for all non-Cloudflare requests. |
| [`enableRFC7239()`](#realipresolver-enablerfc7239) | public | `void` | Enable parsing of the RFC 7239 Forwarded header. Safe only when the trusted proxy is known to set or sanitize this header. |
| [`enableXRealIpHeader()`](#realipresolver-enablexrealipheader) | public | `void` | Enable trusting the X-Real-IP header. Safe only when the trusted proxy is known to set or sanitize this header. |
| [`enableXForwardedForHeader()`](#realipresolver-enablexforwardedforheader) | public | `void` | Enable trusting the X-Forwarded-For header. Safe only when the trusted proxy appends the client IP to the existing chain and the infrastructure strips any client-supplied X-Forwarded-For values before they reach PHP. |
| [`getIp()`](#realipresolver-getip) | public | `string` | Return the real client IP address. Returns REMOTE_ADDR immediately when the direct peer is not a trusted proxy, preventing any header-based spoofing attack. |
| [`parseRFC7239()`](#realipresolver-parserfc7239) | private | `string\|null` | Parse an RFC 7239 Forwarded header and return the real client IP. Collects all for= node values in chain order, then traverses them right-to-left, skipping trusted proxy IPs — consistent with the X-Forwarded-For security model. |
| [`normalizeRFC7239Node()`](#realipresolver-normalizerfc7239node) | private | `string\|null` | Normalize an RFC 7239 node identifier to a plain IP address string. Handles the following node forms per RFC 7239 §6: 192.0.2.60 plain IPv4 192.0.2.60:47011 IPv4 with port [2001:db8::1] IPv6 literal [2001:db8::1]:4711 IPv6 literal with port unknown / _token returns null (not an IP) Ports are validated to be in range 1–65535 with no leading zeros. |
| [`isValidPort()`](#realipresolver-isvalidport) | private | `bool` | Validate a port string per RFC 7239 / HTTP syntax. Rejects values outside 1–65535 and strings with leading zeros. |
| [`isValidIp()`](#realipresolver-isvalidip) | private | `bool` | Validate an IP address, optionally rejecting private and reserved ranges. |

<details>
<summary><code>__construct()</code></summary>

#### `RealIpResolver __construct()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L82)

```php
public function __construct(?TrustedProxyInterface $trustedProxy)
```

| Param | Type | Description |
|---|---|---|
| `$trustedProxy` | `TrustedProxyInterface\|null` | Optional trusted proxy handler. |

</details>

<details>
<summary><code>disablePrivateReservedFilter()</code></summary>

#### `RealIpResolver disablePrivateReservedFilter()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L92)

```php
public function disablePrivateReservedFilter(): void
```

Disable filtering of private and reserved IP ranges.

</details>

<details>
<summary><code>enableCloudflareHeader()</code></summary>

#### `RealIpResolver enableCloudflareHeader()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L106)

```php
public function enableCloudflareHeader(): void
```

Enable trusting the CF-Connecting-IP header set by Cloudflare. Safe only when the trusted proxy list consists exclusively of Cloudflare edge IPs, or when the infrastructure sanitizes this header for all non-Cloudflare requests.

</details>

<details>
<summary><code>enableRFC7239()</code></summary>

#### `RealIpResolver enableRFC7239()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L118)

```php
public function enableRFC7239(): void
```

Enable parsing of the RFC 7239 Forwarded header. Safe only when the trusted proxy is known to set or sanitize this header.

</details>

<details>
<summary><code>enableXRealIpHeader()</code></summary>

#### `RealIpResolver enableXRealIpHeader()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L130)

```php
public function enableXRealIpHeader(): void
```

Enable trusting the X-Real-IP header. Safe only when the trusted proxy is known to set or sanitize this header.

</details>

<details>
<summary><code>enableXForwardedForHeader()</code></summary>

#### `RealIpResolver enableXForwardedForHeader()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L144)

```php
public function enableXForwardedForHeader(): void
```

Enable trusting the X-Forwarded-For header. Safe only when the trusted proxy appends the client IP to the existing chain and the infrastructure strips any client-supplied X-Forwarded-For values before they reach PHP.

</details>

<details>
<summary><code>getIp()</code></summary>

#### `RealIpResolver getIp()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L157)

```php
public function getIp(): string
```

Return the real client IP address. Returns REMOTE_ADDR immediately when the direct peer is not a trusted proxy, preventing any header-based spoofing attack.

**Returns:** `string` — Resolved client IP address.

</details>

<details>
<summary><code>parseRFC7239()</code></summary>

#### `RealIpResolver parseRFC7239()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L212)

```php
private function parseRFC7239(string $header): ?string
```

Parse an RFC 7239 Forwarded header and return the real client IP. Collects all for= node values in chain order, then traverses them right-to-left, skipping trusted proxy IPs — consistent with the X-Forwarded-For security model.

| Param | Type | Description |
|---|---|---|
| `$header` | `string` | Raw Forwarded header value. |

**Returns:** `string\|null` — Extracted IP, or null when none is valid.

</details>

<details>
<summary><code>normalizeRFC7239Node()</code></summary>

#### `RealIpResolver normalizeRFC7239Node()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L259)

```php
private function normalizeRFC7239Node(string $value): ?string
```

Normalize an RFC 7239 node identifier to a plain IP address string. Handles the following node forms per RFC 7239 §6: 192.0.2.60              plain IPv4 192.0.2.60:47011        IPv4 with port [2001:db8::1]           IPv6 literal [2001:db8::1]:4711      IPv6 literal with port unknown / _token        returns null (not an IP) Ports are validated to be in range 1–65535 with no leading zeros.

| Param | Type | Description |
|---|---|---|
| `$value` | `string` | Raw node value extracted from a for= directive. |

**Returns:** `string\|null` — Plain IP address, or null when the node is not a valid IP.

</details>

<details>
<summary><code>isValidPort()</code></summary>

#### `RealIpResolver isValidPort()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L305)

```php
private function isValidPort(string $port): bool
```

Validate a port string per RFC 7239 / HTTP syntax. Rejects values outside 1–65535 and strings with leading zeros.

| Param | Type | Description |
|---|---|---|
| `$port` | `string` | Raw port string from a node identifier. |

**Returns:** `bool` — True when the port is a valid HTTP port number.

</details>

<details>
<summary><code>isValidIp()</code></summary>

#### `RealIpResolver isValidIp()`

[source](https://github.com/rafalmasiarek/php-realIpResolver/blob/main/src/RealIpResolver.php#L326)

```php
private function isValidIp(string $ip): bool
```

Validate an IP address, optionally rejecting private and reserved ranges.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IP address to validate. |

**Returns:** `bool` — True when the IP is valid and (if filtering is on) publicly routable.

</details>

