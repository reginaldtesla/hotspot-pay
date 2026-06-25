# TesNet — Add a new hotspot package (end-to-end)

When you create a **new hotspot profile** on MikroTik, you must update **four places** before students can see it, pay for it, and receive a code:

```text
MikroTik profile + voucher users
        ↓
hotspot-pay/config.php  (price, slug, profile name)
        ↓
login.html (+ login-preview.html, index.html)  (card users tap)
        ↓
Import CSV codes into payment pool
```

There is no single “sync” button — follow every step below.

---

## Example used in this guide

Suppose you added a new MikroTik profile:

| Field | Value |
|-------|--------|
| **Display name** (what users see) | `Night Owl` |
| **MikroTik profile name** (Winbox, exact) | `Night_Owl_5GB` |
| **Data cap** | 5 GB (`5368709120` bytes) |
| **Speed** | 15M/10M |
| **Price** | GH¢ 12.00 |
| **URL slug** (for Paystack) | `night-owl` |
| **Pesewas** (Paystack amount) | `1200` |

Replace these with your real package details everywhere below.

---

## Step 1 — Create the profile on MikroTik

Winbox → **IP → Hotspot → User Profiles** → **+**

| Setting | Example |
|---------|---------|
| Name | `Night_Owl_5GB` |
| Rate limit | `15M/10M` |

Or terminal:

```text
/ip hotspot user profile add name=Night_Owl_5GB rate-limit=15M/10M
```

**Write down the profile name exactly** — it must match in config, CSV, and voucher users.

---

## Step 2 — Generate voucher codes for the new profile

Paste in MikroTik terminal (creates **100 codes**). Change `profile=`, `limit-bytes-total=`, and `comment=` to your package:

```text
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Night_Owl_5GB server=all limit-bytes-total=5368709120 comment=Night_Owl_5GB disabled=no; :set code $c } } }
```

Verify:

```text
/ip hotspot user print count-only where profile="Night_Owl_5GB"
```

---

## Step 3 — Register the package in `hotspot-pay`

Edit **`hotspot-pay/config.php`** on your PC, commit, then `git pull` on ProBook.

### 3a. Add profile → slug map

In `'profile_to_slug'`:

```php
'Night_Owl_5GB' => 'night-owl',
```

### 3b. Add package catalog entry

In `'packages'` array, add a new block (adjust `sort_order`):

```php
[
    'slug' => 'night-owl',
    'name' => 'Night Owl',
    'data_label' => '5GB',
    'amount_pesewas' => 1200,
    'mikrotik_profile' => 'Night_Owl_5GB',
    'sort_order' => 6,
],
```

| Field | Meaning |
|-------|---------|
| `slug` | URL: `buy.php?pkg=night-owl` — lowercase, hyphens only |
| `name` | Shown on success page |
| `data_label` | Shown on success page |
| `amount_pesewas` | GH¢ × 100 (12.00 → `1200`) |
| `mikrotik_profile` | **Exact** Winbox profile name |
| `sort_order` | Admin stock table order |

After deploy, the SQLite `packages` table updates automatically on the next page load (`hp_seed_packages`).

**No Apache restart needed** — only `config.php` changed.

---

## Step 4 — Show the package on `login.html` (hotspot)

Edit **`login.html`** in the repo root (then re-upload to MikroTik).

### 4a. Add a package card

Inside `<div class="package-grid">`, add:

```html
<div class="package-card" data-package="Night Owl">
    <div class="package-name">Night Owl</div>
    <div class="package-data">5GB</div>
    <div class="package-price">GH¢12</div>
    <div class="package-speed">10 Mbps</div>
</div>
```

**Critical:** `data-package="Night Owl"` must match the key you add in `PKG_SLUG` below (same spelling and caps).

### 4b. Add Paystack slug mapping

In the `<script>` block, extend `PKG_SLUG`:

```javascript
var PKG_SLUG = {
    'Quick Surf': 'quick-surf',
    'Student Choice': 'student-choice',
    'Big Bundle': 'big-bundle',
    'Heavy User': 'heavy-user',
    'Hostel Legend': 'hostel-legend',
    'Night Owl': 'night-owl'
};
```

When the user taps the card → `https://pay.tesnet.xyz/buy.php?pkg=night-owl`.

### 4c. Re-upload to MikroTik

Upload the updated **`login.html`** (+ `TesNet.png` if unchanged) to the hotspot HTML folder.

---

## Step 5 — Website copies (optional but recommended)

Keep these in sync so **Get Started** / preview matches the hotspot.

| File | What to add |
|------|-------------|
| **`login-preview.html`** | Same card + same `PKG_SLUG` entry as `login.html` |
| **`index.html`** | New row in pricing table under `#pricing` |

---

## Step 6 — Import codes into the payment pool

Export from MikroTik:

```text
/ip hotspot user export file=tesnet-night-owl where profile="Night_Owl_5GB"
```

Download `.rsc` → convert → import (see **`docs/VOUCHER_REFILL_GUIDE.md`**).

**Windows:**

```powershell
cd C:\Apache24\htdocs\TesNet\hotspot-pay\scripts
python rsc-to-csv.py "C:\Users\RegiTes\Downloads\tesnet-night-owl.rsc" -o "C:\Users\RegiTes\Downloads\night-owl.csv"
```

CSV must include rows like:

```csv
code,profile
TNPMZBY84G4H,Night_Owl_5GB
```

Import at **`https://pay.tesnet.xyz/admin/`** → Import CSV.

Admin stock should show **Night Owl — 100 available** (or your count).

---

## Step 7 — Test everything

| # | Test | Expected |
|---|------|----------|
| 1 | `https://pay.tesnet.xyz/buy.php?pkg=night-owl` | Paystack opens, GH¢ 12.00 |
| 2 | Complete test payment | Code on success page |
| 3 | Admin stock | Available −1, Sold +1 |
| 4 | Hotspot `login.html` | New card visible |
| 5 | Tap card on phone (on Wi‑Fi) | Paystack opens |
| 6 | Enter code on hotspot login | Internet works, correct quota |

---

## Naming rules (avoid common bugs)

| Item | Rule | Bad example |
|------|------|-------------|
| MikroTik profile | Exact Winbox name, underscores OK | `Night Owl` (spaces) |
| CSV `profile` column | Same as MikroTik profile | `night-owl` |
| `PKG_SLUG` key | Same as `data-package` on card | `'Night owl'` vs `Night Owl` |
| `slug` in config | lowercase, hyphens | `Night_Owl` |
| `amount_pesewas` | Integer pesewas | `12` instead of `1200` for GH¢12 |

---

## Checklist (new package)

```
[ ] 1. MikroTik: user profile created (Night_Owl_5GB)
[ ] 2. MikroTik: 100 voucher users generated
[ ] 3. hotspot-pay/config.php: profile_to_slug + packages entry
[ ] 4. git pull on ProBook
[ ] 5. login.html: card + PKG_SLUG
[ ] 6. login-preview.html + index.html (optional)
[ ] 7. Re-upload login.html to MikroTik
[ ] 8. Export .rsc → rsc-to-csv → admin import
[ ] 9. Admin: stock shows new package
[ ] 10. Test buy.php?pkg=night-owl → code → hotspot login
```

---

## What each part does

| Layer | Role |
|-------|------|
| **MikroTik profile** | Speed / hotspot rules |
| **MikroTik user + limit-bytes-total** | The actual voucher code + data cap |
| **config.php** | Price for Paystack + which pool slug to use |
| **voucher_codes in SQLite** | Codes Paystack can assign after payment |
| **login.html card** | What the student taps on captive portal |
| **webhook.php** | Assigns next `available` code for that `package_slug` |

---

## Removing or hiding a package

- Set `'is_active' => 0` — not supported in config array yet; quickest fix: remove card from `login.html` and remove from `packages` in config (or set stock to 0 and don’t import more codes).
- Old sold codes remain valid on MikroTik until data is used up.

---

## Related docs

- **`docs/VOUCHER_REFILL_GUIDE.md`** — more codes for an existing package
- **`docs/HOTSPOT_VOUCHER_PAY.md`** — system design
- **`hotspot-pay/scripts/rsc-to-csv.py`** — export converter
