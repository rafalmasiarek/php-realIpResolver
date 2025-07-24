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
use rafalmasiarek\Http\RealIpResolver\TrustedProxy;
use rafalmasiarek\Http\RealIpResolver\RealIpResolver;
use rafalmasiarek\Http\RealIpResolver\IPLists\Cloudflare;
use rafalmasiarek\Http\RealIpResolver\IPLists\Localhost;

$trustedIps = array_merge(
    Localhost::get(),
    Cloudflare::get()
);

$trustedProxy = new TrustedProxy($trustedIps);
$resolver = new RealIpResolver($trustedProxy);

$realIp = $resolver->getIp();
echo "Real IP: " . $realIp;
```

## Creating a Custom IP List

To define your own trusted proxy list, simply implement the `IpListInterface`:

```php
namespace rafalmasiarek\Http\RealIpResolver\IPLists;

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

Then use it:

```php
use rafalmasiarek\Http\RealIpResolver\IPLists\MyCustomProxy;

$trustedIps = MyCustomProxy::get();
$trustedProxy = new TrustedProxy($trustedIps);
$resolver = new RealIpResolver($trustedProxy);
```

## License

MIT