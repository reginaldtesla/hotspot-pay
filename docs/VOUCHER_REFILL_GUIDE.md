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

```text quick surf
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Quick_Surf_1GB server=all limit-bytes-total=1073741824 comment=Quick_Surf_1GB disabled=no; :set code $c } } }
```
```text student choice
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Student_Choice_3GB server=all limit-bytes-total=3221225472 comment=Student_Choice_3GB disabled=no; :set code $c } } }
```
```` text  big bundle
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Big_Bundle_7GB server=all limit-bytes-total=7516192768 comment=Big_Bundle_7GB disabled=no; :set code $c } } }
````
``` heavy user
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Heavy_User_15GB server=all limit-bytes-total=16106127360 comment=Heavy_User_15GB disabled=no; :set code $c } } }
```
```` hostel legend
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Hostel_Legend_45GB server=all limit-bytes-total=48318382080 comment=Hostel_Legend_45GB disabled=no; :set code $c } } }
````

for time packages

```2hrs
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Two_Hour server=all limit-uptime=2h comment=Two_Hour disabled=no; :set code $c } } }
```
``` 4hrs
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Four_Hour server=all limit-uptime=4h comment=Four_Hour disabled=no; :set code $c } } }
```
```8hrs
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Eight_Hour server=all limit-uptime=8h comment=Eight_Hour disabled=no; :set code $c } } }
```
```full day
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Full_Day server=all limit-uptime=1d comment=Full_Day disabled=no; :set code $c } } }
```
```2weeks
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Two_Week server=all limit-uptime=2w comment=Two_Week disabled=no; :set code $c } } }
```
```1 month
:local digits "0123456789"; :local letters "ABCDEFGHIJKLMNOPQRSTUVWXYZ"; :local n 0; :for n from=1 to=100 do={ :local code ""; :local tries 0; :while ([:len $code] = 0) do={ :set tries ($tries + 1); :if ($tries > 100) do={ :error "duplicate" }; :local c "TN"; :local i 0; :for i from=1 to=5 do={ :set c ($c . [:pick $letters [:rndnum from=0 to=25]]) }; :for i from=1 to=2 do={ :set c ($c . [:pick $digits [:rndnum from=0 to=9]]) }; :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :set c ($c . [:pick $digits [:rndnum from=0 to=9]]); :set c ($c . [:pick $letters [:rndnum from=0 to=25]]); :if ([:len [/ip hotspot user find name=$c]] = 0) do={ /ip hotspot user add name=$c password=$c profile=Month server=all limit-uptime=30d comment=Month disabled=no; :set code $c } } }
```
Change `profile=`, `limit-bytes-total=`, and `comment=` for your package. Profile name must match **`config.php`** → `mikrotik_profile` exactly.

Verify count:

```text
/ip hotspot user print count-only where profile="Quick_Surf_1GB"
```

---

## Step 2 — Export from MikroTik

Run on the router terminal (one file per package). Download each **`.rsc`** from Winbox → **Files**.

**Data packages**

```routeros
/ip hotspot user export file=tesnet-quick-surf where profile="Quick_Surf_1GB"
/ip hotspot user export file=tesnet-student-choice where profile="Student_Choice_3GB"
/ip hotspot user export file=tesnet-big-bundle where profile="Big_Bundle_7GB"
/ip hotspot user export file=tesnet-heavy-user where profile="Heavy_User_15GB"
/ip hotspot user export file=tesnet-hostel-legend where profile="Hostel_Legend_45GB"
```

**Time packages**

```routeros
/ip hotspot user export file=tesnet-2hour where profile="Two_Hour"
/ip hotspot user export file=tesnet-4hour where profile="Four_Hour"
/ip hotspot user export file=tesnet-8hour where profile="Eight_Hour"
/ip hotspot user export file=tesnet-full-day where profile="Full_Day"
/ip hotspot user export file=tesnet-2week where profile="Two_Week"
/ip hotspot user export file=tesnet-month where profile="Month"
```

Quick count before export (expect **100** each):

```routeros
:foreach p in={"Quick_Surf_1GB";"Student_Choice_3GB";"Big_Bundle_7GB";"Heavy_User_15GB";"Hostel_Legend_45GB";"Two_Hour";"Four_Hour";"Eight_Hour";"Full_Day";"Two_Week";"Month"} do={ :put ($p . ": " . [/ip hotspot user print count-only where profile=$p]) }
```

**Optional — one combined export** (includes every hotspot user; skip test/trial users manually):

```routeros
/ip hotspot user export file=tesnet-all-vouchers
```

---

## Step 3 — Convert `.rsc` → CSV

**Python (recommended)** — single file:

```powershell
cd C:\Apache24\htdocs\hotspot-pay\scripts
python rsc-to-csv.py "C:\path\to\tesnet-quick-surf.rsc" -o "C:\path\to\refill-quick-surf.csv"
```

**Convert all 11 exports** (after downloading `.rsc` files into one folder):

```powershell
cd C:\Apache24\htdocs\hotspot-pay\scripts
$src = "C:\path\to\mikrotik-exports"
Get-ChildItem $src -Filter *.rsc | ForEach-Object {
  python rsc-to-csv.py $_.FullName -o ($_.FullName -replace '\.rsc$','.csv')
}
```

**PHP:**

```powershell
php scripts/rsc-to-csv.php "C:\path\to\tesnet-quick-surf.rsc" "C:\path\to\refill-quick-surf.csv"
```

Works for **data** (`limit-bytes-total`) and **time** (`limit-uptime`) user exports.

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
| `rsc-to-csv` finds 0 users | Export must include `limit-bytes-total` or `limit-uptime` (standard hotspot user export) |

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
