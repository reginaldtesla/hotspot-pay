# Paystack — hotspot-pay

Paystack configuration for the plain-PHP voucher service (`hotspot-pay`). This is **separate** from any Laravel portal billing.

---

## URLs to register

Replace `pay.tesnet.xyz` with your live hostname.

| Purpose | URL |
|---------|-----|
| **Webhook** (required) | `https://pay.tesnet.xyz/webhook.php` |
| Checkout callback | Set automatically by `buy.php` → `callback.php?ref=…&tok=…` |
| Buy link (per package) | `https://pay.tesnet.xyz/buy.php?pkg=quick-surf` |

In Paystack Dashboard → **Settings → API Keys & Webhooks**:

1. Add webhook URL above.
2. Enable event **`charge.success`**.
3. Copy **public** and **secret** keys into `config.local.php`.

---

## Configuration (`config.local.php`)

```php
return [
    'app_url' => 'https://pay.tesnet.xyz',
    'paystack_public_key' => 'pk_live_xxxxxxxx',
    'paystack_secret_key' => 'sk_live_xxxxxxxx',
    'admin_password' => 'your-strong-password',
    'checkout_email' => 'checkout@tesnet.xyz',
];
```

| Key | Notes |
|-----|--------|
| `app_url` | Must match the URL students and Paystack reach (HTTPS in production, no trailing slash) |
| `paystack_secret_key` | Used for API calls and webhook HMAC verification |
| `checkout_email` | Paystack requires an email on initialize; hotspot buyers may not have one. **MoMo number appears in admin** (Sales / CSV) from the Paystack webhook — not in the Paystack dashboard email column. |

Package amounts come from `config.php` → `amount_pesewas` (integer pesewas, e.g. GH¢ 3.50 → `350`).

---

## Payment flow

```text
buy.php
  → POST /transaction/initialize (amount, reference, callback_url, metadata)
  → redirect to Paystack hosted checkout

User pays
  → Paystack POST webhook.php (charge.success)
  → HMAC verify → API verify transaction → hp_fulfill_payment()
  → assign next available voucher code

User browser
  → callback.php → success.php (polls until code ready)
```

**Webhook is the source of truth** for code assignment. The browser callback only redirects to the success page.

---

## HTTPS requirements

- **Live** webhooks require a **public HTTPS** endpoint.
- LAN-only IPs (e.g. `http://192.168.88.2`) will not receive Paystack webhooks unless tunneled.
- Use Cloudflare Tunnel, reverse proxy TLS, or similar — see `deploy/cloudflared-ingress.example.yml`.
- Avoid ephemeral tunnel URLs (ngrok free tier) in production; webhook URL must stay stable.

---

## Test vs live

| Mode | Keys | Webhook |
|------|------|---------|
| Test | `pk_test_…` / `sk_test_…` | Same dashboard; use test cards |
| Live | `pk_live_…` / `sk_live_…` | Production webhook URL |

Secret key **must** match the mode. Mismatch causes `401 Invalid signature` on webhook.

Paystack test cards: [Paystack docs](https://paystack.com/docs/payments/test-payments).

---

## Webhook behavior (`webhook.php`)

1. Read raw body; verify `X-Paystack-Signature` (HMAC-SHA512 with secret).
2. Ignore events other than `charge.success`.
3. Look up payment by `reference` in SQLite.
4. Call Paystack verify API; confirm `status=success`, `currency=GHS`, amount matches.
5. Run `hp_fulfill_payment()` — idempotent if already paid.
6. Return `200 OK` on success.

If no voucher stock: payment marked `paid_no_stock`; student sees support message on success page.

---

## Verify after setup

| Check | How |
|-------|-----|
| Initialize works | Open `buy.php?pkg=quick-surf` — Paystack checkout opens |
| Webhook delivered | Paystack dashboard → Webhooks → recent deliveries |
| Code assigned | Admin → Sold codes; success page shows code |
| Amount correct | Paystack charge matches `amount_pesewas` in config |

Failed signature → wrong `paystack_secret_key` or body modified by proxy.

---

## MikroTik walled garden (Paystack)

Students need these **before** hotspot login:

| Host |
|------|
| Your pay host (`pay.tesnet.xyz`) |
| `*.paystack.com` |
| `js.paystack.co` |
| `api.paystack.co` |

---

## Related

- [**HOTSPOT_VOUCHER_PAY.md**](HOTSPOT_VOUCHER_PAY.md) — full flow and database
- [**README.md**](../README.md) — setup and troubleshooting
