# Refill voucher stock (existing package)

Use this when a package runs low on **available** codes in the admin stock table. You generate more users on MikroTik, export them, convert to CSV, and import into `hotspot-pay`.

For a **brand-new** MikroTik profile and price, see **`docs/ADD_NEW_PACKAGE.md`** instead.

---

## Overview

```text
MikroTik: generate N voucher users (profile + data cap)
        ↓
Export .rsc  (/ip hotspot user export ...)
        ↓
scripts/rsc-to-csv.py  (or rsc-to-csv.php)
        ↓
Admin → Import CSV  (https://pay.tesnet.xyz/admin/import.php)
        ↓
Stock: Available +N for that package
```

Codes must already exist as MikroTik hotspot users **and** in the SQLite pool. Payment only assigns codes that were pre-imported.

---

## Step 1 — Generate codes on MikroTik

Winbox → **IP → Hotspot → Users**, or terminal.

Example: **100 more** codes for **Quick Surf** (`Quick_Surf_1GB`, 1 GB = `1073741824` bytes):

```text
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Quick_Surf_1GB server=all limit-bytes-total=1073741824 comment=Quick_Surf_1GB disabled=no; :set code $c } } }
```

Change `profile=`, `limit-bytes-total=`, and `comment=` for your package. Profile name must match **`config.php`** → `mikrotik_profile` exactly.

Verify count:

```text
/ip hotspot user print count-only where profile="Quick_Surf_1GB"
```

---

## Step 2 — Export from MikroTik

```text
/ip hotspot user export file=tesnet-refill-quick-surf where profile="Quick_Surf_1GB"
```

Download **`tesnet-refill-quick-surf.rsc`** from the router (Files).

---

## Step 3 — Convert `.rsc` → CSV

**Python (recommended):**

```powershell
cd C:\Apache24\htdocs\hotspot-pay\scripts
python rsc-to-csv.py "C:\path\to\tesnet-refill-quick-surf.rsc" -o "C:\path\to\refill-quick-surf.csv"
```

**PHP:**

```powershell
php scripts/rsc-to-csv.php "C:\path\to\tesnet-refill-quick-surf.rsc" "C:\path\to\refill-quick-surf.csv"
```

Output format:

```csv
code,profile
TNPMZBY84G4H,Quick_Surf_1GB
```

You can use `code,package_slug` instead of `profile` if you prefer (`quick-surf`, etc.).

---

## Step 4 — Import in admin

1. Open **`https://pay.tesnet.xyz/admin/`** (or `/hotspot-pay/admin/` on local dev).
2. Login → **Import CSV**.
3. Upload the CSV.

The importer reports: imported / skipped (duplicates) / invalid rows.

**Skipped** means the code was already in the pool (safe). **Invalid** usually means unknown profile or slug.

---

## Step 5 — Verify

| Check | Expected |
|-------|----------|
| Admin stock | **Available** increased for that package |
| `buy.php?pkg=quick-surf` | Opens Paystack (not 503 out of stock) |
| Test payment | New code on success page; MikroTik login works |

---

## Profile → slug reference

Must match **`hotspot-pay/config.php`**:

| MikroTik profile | Package slug |
|------------------|--------------|
| `Quick_Surf_1GB` | `quick-surf` |
| `Student_Choice_3GB` | `student-choice` |
| `Big_Bundle_7GB` | `big-bundle` |
| `Heavy_User_15GB` | `heavy-user` |
| `Hostel_Legend_45GB` | `hostel-legend` |
| `Two_Hour` | `2-hour` |
| `Four_Hour` | `4-hour` |
| `Eight_Hour` | `8-hour` |
| `Full_Day` | `full-day` |
| `Two_Week` | `2-week` |
| `Month` | `month` |

---

## CLI import (optional)

```bash
php -r "
require 'lib/bootstrap.php';
\$r = hp_import_csv(hp_db(), 'data/refill-quick-surf.csv');
print_r(\$r);
"
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| All rows **invalid** | CSV `profile` does not match Winbox name, or package missing from `config.php` |
| All rows **skipped** | Codes already imported; export only **new** users or filter export |
| Payment OK, no code | Pool empty — import more; check **paid_no_stock** in admin / DB |
| Code works on MikroTik but not sold | Code never imported into SQLite — import CSV |
| `rsc-to-csv` finds 0 users | Export must include `limit-bytes-total` lines (standard hotspot user export) |

---

## Security

- Do not commit real voucher CSVs to git (`data/vouchers-import.csv` is gitignored).
- Keep **`storage/pool.sqlite`** and **`config.local.php`** off the web (Apache vhost denies direct access).

---

## Related

- [**README.md**](../README.md) — setup and troubleshooting
- [**docs/README.md**](README.md) — documentation index
- [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md) — new profile + price + login card
- [**HOTSPOT_VOUCHER_PAY.md**](HOTSPOT_VOUCHER_PAY.md) — system design
- [**HOTSPOT.md**](HOTSPOT.md) — MikroTik integration
- [**PAYSTACK.md**](PAYSTACK.md) — webhooks and keys
- **`scripts/rsc-to-csv.py`** — converter source
