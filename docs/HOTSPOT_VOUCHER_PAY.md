# TesNet Hotspot Voucher Pay — architecture

Plain PHP on the **ProBook** + MikroTik-hosted **`login.html`**. Laravel and the portal app are **not** part of this flow.

**Status:** Implemented. See [**README.md**](../README.md) for setup.

---

## Goals

1. Student on Wi‑Fi sees the hotspot captive portal (`login.html` on MikroTik).
2. Taps a package → pays with **Paystack** (MoMo/card).
3. After Paystack confirms, backend **assigns the next available code** from the pre-imported SQLite pool.
4. Success page shows the code; student enters it on `login.html` (username = password = code).

---

## What runs where

| Component | Location |
|-----------|----------|
| `login.html`, `logout.html`, `status.html` | **MikroTik** (upload from `MiniISP-Landing-page/`) |
| `TesNet.png` | MikroTik hotspot HTML directory |
| **`hotspot-pay/`** | **ProBook** Apache — vhost `pay.tesnet.xyz` → `public/` |
| Code pool + payments | SQLite `storage/pool.sqlite` |
| Paystack webhook | `https://pay.tesnet.xyz/webhook.php` (HTTPS + tunnel) |

```text
  Student phone
       │
       ▼
  MikroTik captive portal (login.html)
       │  tap package
       ▼
  https://pay.tesnet.xyz/buy.php?pkg=hostel-legend
       │
       ▼
  Paystack checkout
       │
       ├── POST webhook.php → assign code (source of truth)
       │
       └── redirect callback.php → success.php (poll for code)
       │
       ▼
  Back to login.html → enter code → Wi‑Fi
```

---

## Tech stack

| Piece | Choice |
|-------|--------|
| Language | PHP 8.1+ (no framework, no Composer) |
| Database | SQLite (`storage/pool.sqlite`) |
| Config | `config.php` + `config.local.php` (gitignored) |
| Paystack | cURL initialize + webhook HMAC + transaction verify |
| Admin | Password session; import + stock + sold views |
| Buyer sessions | None; access via `ref` + `tok` on success URL |

---

## Folder layout

```text
hotspot-pay/
├── config.php
├── config.local.php.example
├── config.local.php              # gitignored
├── lib/
│   ├── bootstrap.php             # config merge, PDO, migrations, seed
│   ├── pool.php                  # import, stock, fulfillment
│   └── paystack.php              # API + signature verify
├── public/                       # Apache document root
│   ├── index.php
│   ├── buy.php
│   ├── callback.php
│   ├── success.php
│   ├── webhook.php
│   └── assets/style.css
├── admin/                        # Alias /admin
│   ├── auth.php
│   ├── index.php                 # stock summary
│   ├── import.php
│   └── sold.php
├── storage/
│   ├── schema.sql
│   └── pool.sqlite               # gitignored
├── data/
│   └── packages.example.csv
├── scripts/
│   ├── rsc-to-csv.py
│   └── rsc-to-csv.php
├── deploy/
└── docs/
```

---

## Database (SQLite)

Schema: `storage/schema.sql`. Packages auto-seeded from `config.php` via `hp_seed_packages()`.

### `packages`

| Column | Example |
|--------|---------|
| slug | `hostel-legend` |
| name | `Hostel Legend` |
| data_label | `45GB` |
| amount_pesewas | `9500` |
| mikrotik_profile | `Hostel_Legend_45GB` |
| sort_order | `5` |
| is_active | `1` |

### `voucher_codes`

| Column | Example |
|--------|---------|
| code | `TN5GU6ONSXM4` (unique) |
| package_slug | `hostel-legend` |
| status | `available` / `assigned` / `revoked` |
| paystack_reference | set on sale |
| buyer_email, buyer_phone | from Paystack |
| assigned_at | datetime |

### `payments`

| Column | Example |
|--------|---------|
| reference | `HP-…` (Paystack reference) |
| access_token | secret token for success page access |
| package_slug | `hostel-legend` |
| amount_pesewas | `9500` |
| status | `pending` / `paid` / `paid_no_stock` |
| voucher_code_id | FK after assign |
| paid_at | datetime |

---

## Code import

CSV (admin upload or CLI):

```csv
code,profile
TNPMZBY84G4H,Quick_Surf_1GB
```

Or `code,package_slug`. Rules:

- Code must **already exist** on MikroTik as a hotspot user.
- Profile or slug must match `config.php`.
- Duplicates skipped (`INSERT OR IGNORE`).

Convert MikroTik exports: `scripts/rsc-to-csv.py`.

---

## Payment flow (implemented)

### 1. `buy.php?pkg={slug}`

- Validate package active and stock > 0.
- Create `payments` row (`pending`) with `reference` + `access_token`.
- POST Paystack `/transaction/initialize` (amount, email, callback with `ref` + `tok`).
- Redirect to Paystack authorization URL.

### 2. `webhook.php` (fulfillment)

- Verify `X-Paystack-Signature`.
- On `charge.success`: verify transaction via API (status, GHS, amount).
- `hp_fulfill_payment()` in a transaction:
  - Idempotent if already `paid`.
  - `SELECT` oldest `available` code for package; mark `assigned`.
  - If none: `paid_no_stock`.

### 3. `callback.php` + `success.php`

- `callback.php` redirects to `success.php?ref=…&tok=…`.
- `success.php` polls (`?poll=1`) until code assigned or `paid_no_stock`.
- Code displayed only when `access_token` matches.

**Never assign codes from the browser callback alone.**

---

## Package catalog

| Card name | slug | GH¢ | MikroTik profile |
|-----------|------|-----|------------------|
| Quick Surf | `quick-surf` | 3.50 | `Quick_Surf_1GB` |
| Student Choice | `student-choice` | 9.00 | `Student_Choice_3GB` |
| Big Bundle | `big-bundle` | 18.00 | `Big_Bundle_7GB` |
| Heavy User | `heavy-user` | 35.00 | `Heavy_User_15GB` |
| Hostel Legend | `hostel-legend` | 95.00 | `Hostel_Legend_45GB` |

Defined in `config.php`; must stay in sync with `login.html` cards and `PKG_SLUG`.

---

## Configuration

**`config.php`** — packages, `profile_to_slug`, `app_url` default.

**`config.local.php`** — secrets:

```php
return [
    'app_url' => 'https://pay.tesnet.xyz',
    'paystack_public_key' => 'pk_live_…',
    'paystack_secret_key' => 'sk_live_…',
    'admin_password' => '…',
    'checkout_email' => 'checkout@tesnet.xyz',
];
```

---

## Apache deployment

- DocumentRoot → `hotspot-pay/public`
- `Alias /admin` → `hotspot-pay/admin`
- Deny `storage/`, `lib/`, `config.php`, `config.local.php`

Examples:

- Linux: `deploy/apache-pay.tesnet.xyz.conf.example`
- Windows: `C:\Apache24\conf\extra\httpd-pay-tesnet.conf`

Cloudflare Tunnel: `deploy/cloudflared-ingress.example.yml`.

---

## Security

| Control | Implementation |
|---------|----------------|
| Webhook authenticity | HMAC-SHA512 |
| Payment amount | Verified against Paystack API response |
| Success page | Requires matching `access_token` |
| Admin | Password + rate limit on failed logins |
| Secrets / DB | Outside `public/`; Apache deny rules |
| Voucher CSVs | Gitignored (`data/vouchers-import.csv`) |

---

## Admin workflow

1. Create voucher users on MikroTik (profile + byte limit).
2. Export → `rsc-to-csv` → **Admin → Import**.
3. Monitor **Stock** and **Sold codes**.
4. Refill when low — [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md).

---

## Out of scope

- Auto-create MikroTik users via API on payment
- SMS code delivery
- Laravel / RADIUS integration
- Per-purchase dynamic hotspot users

---

## Related docs

| Doc | Topic |
|-----|--------|
| [**README.md**](../README.md) | Setup, troubleshooting |
| [**PAYSTACK.md**](PAYSTACK.md) | Webhooks and keys |
| [**HOTSPOT.md**](HOTSPOT.md) | MikroTik + login.html |
| [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md) | New package |
| [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md) | Refill stock |
