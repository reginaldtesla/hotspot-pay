# MikroTik hotspot — voucher pay integration

How the captive portal (`login.html` on the router) connects to **hotspot-pay** on your billing server.

**Full site rebuild (factory reset, dual Turbonet WAN, Layout B):** [**MIKROTIK_SITE_SETUP.md**](MIKROTIK_SITE_SETUP.md)

This flow uses **pre-generated voucher codes** on MikroTik. It does not use Laravel, FreeRADIUS, or per-purchase API user creation.

---

## End-to-end flow

1. Student joins Wi‑Fi → MikroTik shows **`login.html`**.
2. Student taps a **package card** → browser opens `https://pay.tesnet.xyz/buy.php?pkg=…`.
3. Student pays on Paystack (MoMo/card).
4. **webhook.php** assigns the next imported code from SQLite.
5. **success.php** displays the code.
6. Student returns to hotspot login, enters code as **username and password**, submits.

Codes must already exist as MikroTik hotspot users (`/ip hotspot user`) with the correct profile and `limit-bytes-total`.

---

## What lives where

| Item | Location |
|------|----------|
| `login.html`, `logout.html`, `status.html` | **MikroTik** hotspot HTML directory |
| `TesNet.png` (logo) | Same MikroTik HTML folder |
| `hotspot-pay/` | **ProBook** Apache (`pay.tesnet.xyz` → `public/`) |
| Voucher users | MikroTik **IP → Hotspot → Users** |
| Code pool for sale | SQLite on ProBook (`storage/pool.sqlite`) |

**Source for `login.html`:** `MiniISP-Landing-page/Mikrotik pages/login.html` in the marketing repo (edit there, re-upload to router).

---

## Walled garden

Add to MikroTik **IP → Hotspot → Walled Garden** (and DNS if using hostnames):

| Host / pattern |
|----------------|
| `pay.tesnet.xyz` (or your pay hostname) |
| `*.paystack.com` |
| `js.paystack.co` |
| `api.paystack.co` |

Without these, phones cannot reach checkout before authentication.

---

## `login.html` integration

The captive portal must redirect package taps to the pay server.

### JavaScript config

```javascript
var PAY_BASE = 'https://pay.tesnet.xyz';
var PKG_SLUG = {
    'Quick Surf': 'quick-surf',
    'Student Choice': 'student-choice',
    'Big Bundle': 'big-bundle',
    'Heavy User': 'heavy-user',
    'Hostel Legend': 'hostel-legend',
    '2-Hour': '2-hour',
    '4-Hour': '4-hour',
    '8-Hour': '8-hour',
    'Full Day': 'full-day',
    '2-Week': '2-week',
    'Month': 'month'
};
```

### Package cards

Each card needs `data-package` matching a `PKG_SLUG` key **exactly** (case and spacing):

```html
<div class="package-card" data-package="Quick Surf">
    <div class="package-name">Quick Surf</div>
    <div class="package-data">1GB</div>
    <div class="package-price">GH¢3.5</div>
    ...
</div>
```

On click → `PAY_BASE + '/buy.php?pkg=' + slug`.

### Voucher login (unchanged)

`doLogin()` sets username and password to the entered code and posts to MikroTik `$(link-login-only)`.

### Optional code prefill

If the success page links back with `?code=TN…`, JS can prefill the code field:

```javascript
var params = new URLSearchParams(window.location.search);
if (params.get('code')) document.getElementById('code').value = params.get('code');
```

---

## Package sync checklist

These four must agree:

| Layer | What to match |
|-------|----------------|
| MikroTik profile name | Winbox exact name (e.g. `Quick_Surf_1GB`) |
| `hotspot-pay/config.php` | `mikrotik_profile`, `slug`, `amount_pesewas` |
| `login.html` | Card label, `data-package`, `PKG_SLUG`, displayed price |
| CSV import | `profile` column = MikroTik profile name |

Current catalog: see [**README.md**](../README.md#current-packages).

---

## MikroTik user setup

For each voucher code:

```text
/ip hotspot user add name=TNXXXX password=TNXXXX profile=Quick_Surf_1GB \
  server=all limit-bytes-total=1073741824 comment=Quick_Surf_1GB disabled=no
```

- **name** = **password** = the code shown to the student
- **profile** = rate limit / hotspot rules
- **limit-bytes-total** = data cap in bytes

Then export and import into hotspot-pay — see [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md).

---

## Upload HTML to MikroTik

1. Edit `MiniISP-Landing-page/Mikrotik pages/login.html` on your PC.
2. Upload to router: Winbox → **Files** → hotspot HTML directory, or **IP → Hotspot → Server Profiles → Login** tab.
3. Include `TesNet.png` if referenced.

Test on a phone on Wi‑Fi: tap package → Paystack opens; after pay → code works on login.

---

## `logout.html` / `status.html`

No Paystack logic required. Optional footer: “Buy more data” linking to `$(link-login)`.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Tap package, nothing happens | `data-package` not in `PKG_SLUG` |
| Paystack won’t load on phone | Walled garden missing pay/Paystack hosts |
| Code invalid on login | User not created on MikroTik, or wrong profile |
| Paid but no code | Import pool empty; check webhook + admin stock |
| Wrong price at checkout | `config.php` `amount_pesewas` ≠ card display (card is cosmetic; Paystack uses config) |

---

## Related

- [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md) — new package end-to-end
- [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md) — refill stock
- [**HOTSPOT_VOUCHER_PAY.md**](HOTSPOT_VOUCHER_PAY.md) — server-side design
