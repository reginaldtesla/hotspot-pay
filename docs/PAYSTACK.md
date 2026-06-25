# Paystack (live) — HTTPS and webhooks

Students pay for **data packages** only via Paystack. Fulfillment activates the plan in Laravel and FreeRADIUS after Paystack confirms payment (callback + webhook).

## Production `.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://portal.yourdomain.com
APP_FORCE_HTTPS=true

PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_SECRET_KEY=sk_live_...
PAYSTACK_CUSTOMER_EMAIL_DOMAIN=billing.yourdomain.com
```

Students log in with **phone only**; Paystack still requires an email on initialize. The app sends `{phone}@your-domain` (not `@tesnet.local`, which Paystack rejects).

`APP_URL` must be the **public HTTPS origin** students and Paystack use. Laravel builds callback and webhook URLs from named routes:

| Route | Path |
| :--- | :--- |
| Webhook | `POST /portal/payments/webhook` |
| Callback (after checkout) | `GET /portal/payments/callback` (authenticated student) |

Full webhook URL to register in Paystack Dashboard → Settings → API & Webhooks:

```text
https://portal.yourdomain.com/portal/payments/webhook
```

## HTTPS requirements

- Paystack **live** webhooks require a **public HTTPS** endpoint (not `http://192.168.88.2` on a LAN-only IP unless you tunnel).
- Set `APP_FORCE_HTTPS=true` so `route()` and redirects use `https://` behind a reverse proxy or TLS terminator.
- Terminate TLS on Apache/nginx/Caddy on the ProBook, or use **Cloudflare Tunnel** ([`CLOUDFLARE_TUNNEL.md`](CLOUDFLARE_TUNNEL.md)). Avoid ngrok for production — URLs change and break Paystack webhooks.

## Paystack dashboard

1. Switch to **Live** mode.
2. Paste the webhook URL above; enable `charge.success` (and any events your `PaymentController::webhook` handles).
3. Copy **live** public/secret keys into `.env`.
4. Confirm **callback URL** in initialize requests matches `route('portal.payments.callback')` (generated from `APP_URL`).

## CSRF

The webhook route is excluded from CSRF verification (see `bootstrap/app.php` or `VerifyCsrfToken` exceptions) so Paystack can `POST` signed payloads.

## Local testing

- Use Paystack **test** keys and the test webhook URL from the dashboard.
- `php artisan serve` binds `127.0.0.1` by default; use `--host=0.0.0.0` only on a trusted LAN, or expose via HTTPS tunnel for webhook delivery.

## Verify webhook delivery

After a test payment, check Laravel logs and the `transactions` table. Failed signature verification returns `400` — confirm `PAYSTACK_SECRET_KEY` matches the mode (test vs live).
