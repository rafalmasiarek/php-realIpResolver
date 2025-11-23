# RealIpResolver

A lightweight PHP library to resolve the real client IP address, with optional support for trusted proxy lists like Cloudflare, AWS, or localhost.

## Installation

```
composer require rafalmasiarek/real-ip-resolver
```

## Basic Usage (no trusted proxy)

If you're not behind any proxies or load balancers, you can use the resolver directly:

```php
use rafalmasiarek\Http\RealIpResolver\RealIpResolver;

$resolver = new RealIpResolver();
$realIp = $resolver->getIp();

echo "Real IP: " . $realIp;
```

## Advanced Usage (with trusted proxies)

You can specify trusted proxy IP ranges using predefined providers:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;
use rafalmasiarek\RealIpResolver\IPLists\Localhost;

$trustedIps = array_merge(
    Localhost::get(),
    Cloudflare::get()
);

$trustedProxy = new TrustedProxy($trustedIps);$resolver = new RealIpResolver($trustedProxy);

$realIp = $resolver->getIp();
echo "Real IP: " . $realIp;
```

## Creating a Custom IP List

To define your own trusted proxy list, implement the `IpListInterface`:

```php
namespace rafalmasiarek\RealIpResolver\IPLists;

class MyCustomProxy implements IpListInterface
{
    public static function get(): array
    {
        return [
            '203.0.113.5',
            '203.0.113.6',
            '2001:db8::abcd:1234',
        ];
    }
}
```

Using it:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\MyCustomProxy;

$trustedIps = MyCustomProxy::get();
$trustedProxy = new TrustedProxy($trustedIps);
$resolver = new RealIpResolver($trustedProxy);
```

## Namespace Change in 1.2.0

Starting from version **1.2.0**, the library uses a cleaner and flatter namespace structure.

### What changed

Old namespace (before 1.2.0):

```
rafalmasiarek\Http\RealIpResolver\
```

New namespace (1.2.0 and later):

```
rafalmasiarek\RealIpResolver\
```

### Updated imports

Before:

```php
use rafalmasiarek\Http\RealIpResolver\RealIpResolver;
```

After:

```php
use rafalmasiarek\RealIpResolver;
use rafalmasiarek\RealIpResolver\TrustedProxy;
use rafalmasiarek\RealIpResolver\IPLists\Cloudflare;
use rafalmasiarek\RealIpResolver\IPLists\Localhost;
```

All users should update their imports accordingly.

## License

MIT

