# hotspot-pay documentation

Guides for operating TesNet’s plain-PHP voucher payment service.

| Document | When to read |
|----------|----------------|
| [**HOTSPOT_VOUCHER_PAY.md**](HOTSPOT_VOUCHER_PAY.md) | Understand architecture, DB schema, and payment flow |
| [**PAYSTACK.md**](PAYSTACK.md) | Configure Paystack keys, webhooks, HTTPS |
| [**HOTSPOT.md**](HOTSPOT.md) | MikroTik captive portal, `login.html`, walled garden |
| [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md) | Add more codes for an **existing** package |
| [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md) | Add a **new** package (profile, price, login card) |

**Deploy examples** (repo `deploy/`):

| File | Purpose |
|------|---------|
| `apache-pay.tesnet.xyz.conf.example` | Linux Apache vhost for `pay.tesnet.xyz` |
| `apache-tesnet.xyz.conf.example` | Main marketing site vhost |
| `cloudflared-ingress.example.yml` | Cloudflare Tunnel ingress snippet |
| `hosts-windows.example` | Local `pay.tesnet.xyz` → 127.0.0.1 |

Start with the project [**README.md**](../README.md) for setup and troubleshooting.
