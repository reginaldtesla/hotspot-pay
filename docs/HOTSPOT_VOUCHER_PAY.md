# TesNet Hotspot Voucher Pay — design (no Laravel)

Plain PHP service on the **ProBook** + MikroTik-hosted **`login.html`**, **`logout.html`**, **`status.html`** from the repo root.

Laravel (`TesNet/`) stays separate and is **not** used for this flow.

---

## Goals

1. Student on Wi‑Fi sees **your existing** hotspot pages on MikroTik.
2. Taps a **package** → pays with **Paystack** (MoMo/card).
3. After Paystack confirms payment, backend **assigns the next available code** from your pre-imported pool (codes already exist in MikroTik → Hotspot → Users).
4. Success page shows the code; student enters it on **`login.html`** (username = password = code).
5. **`logout.html`** and **`status.html`** unchanged except optional copy tweaks.

---

## What runs where

| Component | Location |
|-----------|----------|
| `login.html`, `logout.html`, `status.html` | **MikroTik** (upload from repo root) |
| `TesNet.png` (logo on login) | MikroTik hotspot HTML directory |
| **`hotspot-pay/`** (plain PHP) | **ProBook** Apache (`/var/www/.../hotspot-pay` or vhost) |
| Code pool + payment log | SQLite file or MariaDB on ProBook |
| Paystack webhook | HTTPS URL on ProBook (domain + Cloudflare Tunnel) |

```text
  Student phone
       │
       ▼
  MikroTik captive portal (login.html)
       │  tap package
       ▼
  https://pay.YOURDOMAIN.com/buy.php?pkg=hostel-legend
       │
       ▼
  Paystack checkout
       │
       ▼
  POST webhook → ProBook hotspot-pay/webhook.php
       │  assign next code from pool
       ▼
  success.php → "Your code: TN5GU6ONSXM4"
       │
       ▼
  Back to login.html → enter code → Wi‑Fi
```

---

## Tech stack (simple)

| Piece | Choice |
|-------|--------|
| Language | **PHP 8.1+** (no framework) |
| Database | **SQLite** (`storage/pool.sqlite`) — zero setup; optional MariaDB later |
| Config | `hotspot-pay/config.php` + `config.local.php` (gitignored secrets) |
| Paystack | cURL initialize + verify webhook HMAC |
| Admin | `admin/import.php` (CSV upload) + basic password in config |
| Sessions | None for buyers; admin cookie or HTTP basic |

No Composer required for v1 (optional `vlucas/phpdotenv` later).

---

## Folder layout (new, repo root)

```text
TesNet/                          ← repo root
├── login.html                   ← upload to MikroTik (edit Pay links)
├── logout.html                  ← upload to MikroTik (no logic change)
├── status.html                  ← upload to MikroTik (no logic change)
├── TesNet.png                   ← logo for login.html
├── docs/HOTSPOT_VOUCHER_PAY.md  ← this file
└── hotspot-pay/
    ├── config.php               ← packages, URLs (no secrets)
    ├── config.local.php.example
    ├── config.local.php         ← PAYSTACK_SECRET, ADMIN_PASSWORD (gitignore)
    ├── lib/
    │   ├── bootstrap.php        ← config, SQLite PDO, helpers
    │   ├── pool.php             ← import, assign, stock counts
    │   └── paystack.php         ← initialize, verify webhook
    ├── public/                  ← Apache document root
    │   ├── buy.php              ← start checkout for ?pkg=
    │   ├── callback.php         ← Paystack redirect (poll until assigned)
    │   ├── success.php          ← show code
    │   ├── webhook.php          ← Paystack POST
    │   └── assets/style.css     ← minimal success page
    ├── admin/
    │   ├── index.php            ← stock per package
    │   └── import.php           ← upload CSV
    ├── storage/
    │   ├── pool.sqlite          ← gitignore
    │   └── schema.sql
    └── data/
        └── packages.example.csv ← code import template
```

Apache on ProBook: point vhost `pay.yourdomain.com` → `hotspot-pay/public/`.

---

## Database (SQLite)

### `packages` (catalog — matches login.html cards)

| Column | Example |
|--------|---------|
| slug | `hostel-legend` |
| name | `Hostel Legend` |
| data_label | `45GB` |
| amount_pesewas | `9500` |
| mikrotik_profile | `Hostel_Legen...` (exact WinBox name) |
| sort_order | `5` |
| is_active | `1` |

### `voucher_codes` (your document import)

| Column | Example |
|--------|---------|
| id | auto |
| code | `TN5GU6ONSXM4` |
| package_slug | `hostel-legend` |
| status | `available` / `assigned` / `revoked` |
| paystack_reference | set on sale |
| buyer_email | from Paystack |
| buyer_phone | from Paystack metadata |
| assigned_at | datetime |
| created_at | import time |

### `payments` (audit)

| Column | Example |
|--------|---------|
| reference | Paystack ref |
| package_slug | `hostel-legend` |
| amount_pesewas | `9500` |
| status | `pending` / `paid` / `failed` |
| voucher_code_id | FK after assign |

---

## Code import (your document)

CSV you send (one row per MikroTik user already created in WinBox):

```csv
code,package_slug
TN5GU6ONSXM4,hostel-legend
TN2TIBWYJ8LR,hostel-legend
TNVHG9TFABT3,quick-surf
```

Rules:

- Code must **already exist** on MikroTik (Hotspot → Users).
- `package_slug` must match a row in `packages`.
- Import skips duplicates; admin UI shows counts per package.

---

## Paystack flow

### 1. `buy.php?pkg=hostel-legend`

- Validate package active and **stock > 0** (count `voucher_codes` where `status=available`).
- Create `payments` row `pending`, generate reference `HP-{random}`.
- POST Paystack `/transaction/initialize`:
  - `amount` = package pesewas
  - `email` = `buyer@billing.yourdomain.com` or collect phone on small form
  - `callback_url` = `https://pay.YOURDOMAIN.com/callback.php?ref=...`
  - `metadata`: `package_slug`, `payment_id`
- Redirect user to Paystack authorization URL.

### 2. `webhook.php` (source of truth)

- Verify `x-paystack-signature` with secret key.
- On `charge.success`: idempotent on `reference`.
- Transaction:
  - `SELECT code FROM voucher_codes WHERE package_slug=? AND status='available' ORDER BY id LIMIT 1` (lock row).
  - If none → log alert, mark payment `paid_no_stock` (manual refund).
  - Else set code `assigned`, link `payments` + `voucher_code_id`.
- **Never** assign code from browser callback alone.

### 3. `callback.php` + `success.php`

- User returns from Paystack.
- Poll DB (or wait for webhook) until `payments.status=paid` and code assigned.
- `success.php` displays:

  > Payment received  
  > **Your login code:** `TN5GU6ONSXM4`  
  > Go back to Wi‑Fi login and enter this code (same for password).

Optional: `?code=` deep link — login.html JS reads query and prefills `#code`.

---

## Changes to `login.html` (repo root)

Keep MikroTik variables: `$(link-login-only)`, `$(link-status)`, `$(error)`, etc.

### Replace manual MoMo block

Remove Telecel number block; replace with:

- Short line: “Pay with MoMo or card — get your code instantly.”
- Package cards: on click → redirect to pay server (not scroll to MoMo).

### Add config line (top of script)

```javascript
var PAY_BASE = 'https://pay.YOURDOMAIN.com';  // ProBook + tunnel
var PKG_SLUG = {
  'Quick Surf': 'quick-surf',
  'Student Choice': 'student-choice',
  'Big Bundle': 'big-bundle',
  'Heavy User': 'heavy-user',
  'Hostel Legend': 'hostel-legend'
};
```

### Package click handler

```javascript
card.addEventListener('click', function () {
  var name = card.getAttribute('data-package');
  var slug = PKG_SLUG[name];
  if (!slug) return;
  window.location.href = PAY_BASE + '/buy.php?pkg=' + encodeURIComponent(slug);
});
```

### Voucher login (unchanged)

- `doLogin()` sets username/password = code → submit to `$(link-login-only)`.

### Prefill after payment

```javascript
var params = new URLSearchParams(window.location.search);
if (params.get('code')) document.getElementById('code').value = params.get('code');
```

Success page can link: `$(link-login)?code=TNxxx` — only if MikroTik preserves query on login page (test on router).

### `logout.html` / `status.html`

No Paystack changes. Optional footer link: “Buy more data” → `$(link-login)`.

---

## Package catalog (sync with login.html)

| Card name (login.html) | slug | Price (GH¢) | Profile (WinBox) |
|------------------------|------|---------------|------------------|
| Quick Surf | `quick-surf` | 3.50 | `Quick_Surf_1...` |
| Student Choice | `student-choice` | 9.00 | `Student_Choi...` |
| Big Bundle | `big-bundle` | 18.00 | `Big_Bundle_7...` |
| Heavy User | `heavy-user` | 35.00 | `Heavy_Gamer...` |
| Hostel Legend | `hostel-legend` | 95.00 | `Hostel_Legen...` |

Stored in `hotspot-pay/config.php` and seeded into SQLite `packages` on first run.

---

## ProBook + domain

```env
# config.local.php
PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_SECRET_KEY=sk_live_...
APP_URL=https://pay.yourdomain.com
ADMIN_PASSWORD=...
```

- **Cloudflare Tunnel** → ProBook Apache → `hotspot-pay/public`
- Paystack dashboard webhook: `https://pay.yourdomain.com/webhook.php`

Laravel `TesNet/` can remain on same machine different path (`/portal`) or be retired later for students.

---

## MikroTik walled garden

Allow **before** login:

| Host |
|------|
| `pay.yourdomain.com` |
| `*.paystack.com` |
| `js.paystack.co` |
| `api.paystack.co` |

Upload HTML files: **IP → Hotspot → Server Profiles → HTML** (or file manager).

---

## Admin workflow (you)

1. Create `TN…` users in WinBox with correct profile + `limit-bytes-total`.
2. Export codes to CSV → **Admin → Import**.
3. Monitor **Admin → Stock** (e.g. Hostel Legend: 12 available).
4. When low, create more users in WinBox and import again.

---

## Security

- Webhook: HMAC verify only; no CSRF on webhook.
- `buy.php`: rate limit by IP (simple file-based or MikroTik).
- Admin: password + optional IP allowlist (192.168.88.0/24).
- SQLite file outside `public/` (only `public/*.php` exposed).

---

## Out of scope (v1)

- Auto-create MikroTik users via API
- SMS delivery of codes
- Laravel portal integration
- RADIUS (auth is MikroTik local hotspot users)

---

## Implementation order

1. `hotspot-pay/` scaffold + SQLite schema + `packages` seed from config.
2. `admin/import.php` + sample CSV.
3. `buy.php` + Paystack initialize.
4. `webhook.php` + pool assign.
5. `success.php` + `callback.php`.
6. Edit **`login.html`** package clicks → `PAY_BASE`.
7. Deploy ProBook vhost + Cloudflare Tunnel + Paystack webhook.
8. Upload **`login.html`**, **`logout.html`**, **`status.html`** to MikroTik.
9. Test: buy → code → login.

---

## What you provide before build

1. CSV of available codes per package.
2. Exact MikroTik **profile names** (copy from WinBox).
3. Paystack live/test keys.
4. Domain or tunnel hostname for `pay.YOURDOMAIN.com`.
