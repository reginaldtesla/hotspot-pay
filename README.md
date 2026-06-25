# TesNet hotspot-pay

Plain PHP service that sells MikroTik hotspot voucher codes via **Paystack**. Students tap a package on the captive-portal `login.html`, pay, receive a code on the success page, and log in with that code (username = password).

No Laravel, no Composer — PHP 8.1+, SQLite, and Apache.

---

## How it works

```text
MikroTik login.html  →  buy.php?pkg=…  →  Paystack checkout
                              ↓
                    webhook.php assigns next code from pool
                              ↓
                    success.php shows code  →  student logs in on hotspot
```

Codes must exist on **both** MikroTik (Hotspot → Users) **and** in the SQLite pool (admin CSV import). Payment only hands out pre-imported codes.

---

## Project layout

```text
hotspot-pay/
├── config.php                 # Packages, prices, profile map (no secrets)
├── config.local.php.example   # Template for secrets
├── config.local.php           # Paystack keys, admin password (gitignored)
├── public/                    # Apache document root
│   ├── buy.php                # Start Paystack checkout
│   ├── callback.php           # Paystack redirect → success page
│   ├── success.php            # Show / poll for voucher code
│   ├── webhook.php            # Paystack POST (fulfillment)
│   └── assets/style.css
├── admin/                     # Stock, import, sold codes (via /admin alias)
│   ├── index.php
│   ├── import.php
│   └── sold.php
├── lib/
│   ├── bootstrap.php          # Config, DB, helpers
│   ├── pool.php               # Import, stock, fulfillment
│   └── paystack.php           # Initialize + verify
├── storage/
│   ├── schema.sql
│   └── pool.sqlite            # gitignored
├── data/
│   └── packages.example.csv   # CSV import template
├── scripts/
│   ├── rsc-to-csv.py          # MikroTik .rsc → CSV
│   ├── rsc-to-csv.php
│   └── restart-apache-admin.cmd
├── deploy/                    # Apache, Cloudflare, hosts examples
└── docs/                      # Full guides (see below)
```

**Related files outside this folder:** `MiniISP-Landing-page/login.html` (package cards + Paystack links) — upload to MikroTik.

---

## Requirements

| Requirement | Notes |
|-------------|--------|
| PHP 8.1+ | `pdo_sqlite`, `curl`, `openssl` |
| Apache | Vhost docroot → `public/`; deny `storage/`, `lib/`, config |
| HTTPS | Required for live Paystack webhooks |
| Paystack | GHS, test or live keys |
| MikroTik | Voucher users + profiles; walled garden for pay host |

---

## Setup

### 1. Configuration

```bash
cp config.local.php.example config.local.php
```

Edit `config.local.php`:

| Key | Purpose |
|-----|---------|
| `app_url` | Public base URL, no trailing slash (e.g. `https://pay.tesnet.xyz`) |
| `paystack_public_key` | Paystack public key |
| `paystack_secret_key` | Paystack secret key |
| `admin_password` | Admin UI password (not the example default) |
| `checkout_email` | Email sent to Paystack on initialize (buyers may not have email) |

Package catalog and prices live in **`config.php`** — synced into SQLite on each request.

### 2. Apache

**Linux (ProBook):**

```bash
cd /var/www/MiniISP-Landing-page/hotspot-pay
sudo cp deploy/apache-pay.tesnet.xyz.conf.example /etc/apache2/sites-available/pay.tesnet.xyz.conf
# Adjust paths in the file if needed
sudo a2ensite pay.tesnet.xyz.conf
sudo systemctl reload apache2
chmod 750 storage
```

**Windows (local dev):**

- Vhost: `C:\Apache24\conf\extra\httpd-pay-tesnet.conf` (included from `httpd.conf`)
- Optional hosts: `127.0.0.1 pay.tesnet.xyz` — see `deploy/hosts-windows.example`
- After `httpd.conf` or `php.ini` changes, restart Apache **as Administrator**: `scripts\restart-apache-admin.cmd`
- PHP extensions must load under Apache (`pdo_sqlite`, `curl`) — see [Troubleshooting](#troubleshooting)

### 3. HTTPS / tunnel

Expose `pay.tesnet.xyz` with TLS (Cloudflare Tunnel example: `deploy/cloudflared-ingress.example.yml`).

### 4. Paystack dashboard

| Setting | Value |
|---------|--------|
| Webhook URL | `https://pay.tesnet.xyz/webhook.php` |
| Event | `charge.success` |

Details: **`docs/PAYSTACK.md`**

### 5. Import voucher codes

```bash
php -m | grep -i sqlite   # verify extension
```

**Admin UI:** `https://pay.tesnet.xyz/admin/` → Login → **Import CSV**

**CLI:**

```bash
php -r "
require 'lib/bootstrap.php';
\$r = hp_import_csv(hp_db(), 'data/your-import.csv');
print_r(\$r);
"
```

CSV format (`code,profile` or `code,package_slug`) — see `data/packages.example.csv`.

Do not commit real voucher CSVs; `data/vouchers-import.csv` is gitignored.

### 6. MikroTik

- Upload `login.html` from `MiniISP-Landing-page/` to the router hotspot HTML folder
- Walled garden: `pay.tesnet.xyz`, `*.paystack.com`, `js.paystack.co`, `api.paystack.co`

Details: **`docs/HOTSPOT.md`**

---

## Admin

| URL | Purpose |
|-----|---------|
| `/admin/` | Stock per package (available / sold / revoked) |
| `/admin/import.php` | Upload voucher CSV |
| `/admin/sold.php` | Recently sold codes |

Login uses `admin_password` from `config.local.php`. Failed attempts are rate-limited (5 tries → 15 min lockout).

---

## Current packages

| Card name (`login.html`) | Slug | Price | MikroTik profile |
|--------------------------|------|-------|------------------|
| Quick Surf | `quick-surf` | GH¢ 3.50 | `Quick_Surf_1GB` |
| Student Choice | `student-choice` | GH¢ 9.00 | `Student_Choice_3GB` |
| Big Bundle | `big-bundle` | GH¢ 18.00 | `Big_Bundle_7GB` |
| Heavy User | `heavy-user` | GH¢ 35.00 | `Heavy_User_15GB` |
| Hostel Legend | `hostel-legend` | GH¢ 95.00 | `Hostel_Legend_45GB` |

Test: `https://pay.tesnet.xyz/buy.php?pkg=quick-surf`

---

## Documentation

| Doc | Contents |
|-----|----------|
| [**docs/HOTSPOT_VOUCHER_PAY.md**](docs/HOTSPOT_VOUCHER_PAY.md) | Architecture, database, payment flow |
| [**docs/PAYSTACK.md**](docs/PAYSTACK.md) | Keys, webhooks, test vs live |
| [**docs/HOTSPOT.md**](docs/HOTSPOT.md) | MikroTik walled garden, `login.html` |
| [**docs/VOUCHER_REFILL_GUIDE.md**](docs/VOUCHER_REFILL_GUIDE.md) | Add more codes to an existing package |
| [**docs/ADD_NEW_PACKAGE.md**](docs/ADD_NEW_PACKAGE.md) | New profile, price, and login card |

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `could not find driver` (web) | `pdo_sqlite` not loaded in Apache PHP | Enable extensions in `php.ini`, add PHP to Apache `PATH` / `LoadFile`, restart Apache |
| `Admin password not configured` | Missing or default `config.local.php` | Copy example, set `admin_password` |
| `Could not start payment` | Invalid/missing Paystack secret | Check `paystack_secret_key` and mode (test/live) |
| `503 out of stock` | No `available` codes for slug | Import CSV via admin |
| Payment OK, no code on screen | Webhook not delivered or pool empty | Check Paystack webhook logs; verify HTTPS URL; check `paid_no_stock` |
| Webhook `401 Invalid signature` | Wrong secret key | Secret must match Paystack mode |
| CSV import all **invalid** | Profile name mismatch | CSV `profile` must match Winbox name exactly |
| Package tap does nothing | `data-package` ≠ `PKG_SLUG` key | Fix spelling in `login.html` |

---

## Security notes

- `config.local.php` and `storage/pool.sqlite` are gitignored
- Apache vhost denies web access to `storage/`, `lib/`, and config files
- Success page requires `ref` + `tok` (access token) — reference alone is not enough
- Fulfillment runs only from verified `webhook.php`, not the browser callback

---

## Deploy checklist

```
[ ] config.local.php with real keys and admin password
[ ] Apache vhost → public/; /admin alias; deny storage/lib
[ ] HTTPS + Paystack webhook registered
[ ] PHP extensions: pdo_sqlite, curl (CLI and Apache)
[ ] Voucher CSV imported; admin stock > 0 per package
[ ] login.html on MikroTik with correct PAY_BASE and PKG_SLUG
[ ] Walled garden for pay host + Paystack
[ ] Test payment end-to-end
```
