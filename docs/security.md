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
