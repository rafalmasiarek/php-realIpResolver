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
