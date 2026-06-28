# MikroTik site setup — from factory reset (Layout B)

Step-by-step guide to configure the **hostel/site MikroTik** for **hotspot-pay** when:

- The **ProBook billing server** stays **at home** on Turbonet (DHCP) and serves `https://pay.tesnet.xyz` via Cloudflare Tunnel.
- The **MikroTik** is **on site** with student Wi‑Fi and hotspot at `192.168.88.1`.
- **Two Turbonet routers** feed the MikroTik WAN (**ether1** + **ether2**); a **third Turbonet** at home feeds the ProBook only.

This stack uses **voucher users on MikroTik** (`/ip hotspot user`). It does **not** use FreeRADIUS or the legacy Laravel TesNet portal.

**Portal integration details:** [**HOTSPOT.md**](HOTSPOT.md)  
**Server install:** [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md)

---

## 1. Network layout

```text
HOME
────
Turbonet #1 ──► ProBook (Ubuntu)
                  Apache + hotspot-pay + cloudflared
                  https://pay.tesnet.xyz

HOSTEL / SITE
─────────────
Turbonet #2 ──► MikroTik ether1  (WAN 1, primary)
Turbonet #3 ──► MikroTik ether2  (WAN 2, backup)
                      │
                      └── bridge-lan + Wi‑Fi
                            192.168.88.1 (hotspot login)
                            students connect here
```

| Device | Role |
|--------|------|
| Turbonet #1 (home) | Internet for ProBook; keep on AC 24/7 |
| Turbonet #2 + #3 (site) | Internet for MikroTik; failover if one link drops |
| MikroTik | Gateway, DHCP, hotspot, captive portal |
| ProBook | Paystack checkout, webhooks, voucher pool (SQLite) |

There is **no Ethernet link** between ProBook and MikroTik. Payment traffic goes over the public internet:

```text
Student phone → MikroTik → Turbonet → Internet → Cloudflare → ProBook
```

---

## 2. Domains and addresses (what students use)

The MikroTik does **not** need its own public domain.

| Purpose | Address |
|---------|---------|
| Hotspot login (captive portal) | `http://192.168.88.1` |
| Buy / Paystack checkout | `https://pay.tesnet.xyz` |
| Marketing (optional) | `https://tesnet.xyz` |
| Wi‑Fi name (SSID) | `Tesnet@0200504248` (open, no password) |

**Server `config.local.php`:**

```php
'app_url' => 'https://pay.tesnet.xyz',
'hotspot_login_url' => 'http://192.168.88.1',
```

**`login.html` on the router:**

```javascript
var PAY_BASE = 'https://pay.tesnet.xyz';
```

---

## 3. Before you start

| Item | Notes |
|------|--------|
| Laptop + **Winbox** | [mikrotik.com/download](https://mikrotik.com/download) |
| Ethernet cable | First login after reset |
| Portal files (PC) | `MiniISP-Landing-page/Mikrotik pages/` |
| Server live | `https://pay.tesnet.xyz/` + Paystack webhook |
| Turbonet #2 + #3 | Powered; LAN ports → MikroTik ether1 + ether2 |

**Optional backup before reset:**

```routeros
/export file=before-reset
```

---

## 4. Factory reset

1. Winbox → **System → Reset Configuration**
2. Enable **No Default Configuration** (cleanest)
3. Reset and reconnect at **`192.168.88.1`**
4. User **`admin`**, empty password → **set a strong password immediately**

```routeros
/system identity set name=TesNet-HAP
/user set admin password="STRONG_ROUTER_PASSWORD"
```

---

## 5. Interface plan

| Port | Role |
|------|------|
| **ether1** | Turbonet #2 (WAN 1) |
| **ether2** | Turbonet #3 (WAN 2) |
| **ether3–5 + wlan1** | LAN bridge (hotspot) |

After reset, verify names:

```routeros
/interface print
/interface bridge port print
```

Remove **ether1** and **ether2** from any default bridge:

```routeros
/interface bridge port remove [find interface=ether1]
/interface bridge port remove [find interface=ether2]
```

---

## 6. LAN bridge

```routeros
/interface bridge add name=bridge-lan
/interface bridge port add bridge=bridge-lan interface=ether3
/interface bridge port add bridge=bridge-lan interface=ether4
/interface bridge port add bridge=bridge-lan interface=ether5
/interface bridge port add bridge=bridge-lan interface=wlan1
/interface bridge port add bridge=bridge-lan interface=wlan2

/ip address add address=192.168.88.1/24 interface=bridge-lan network=192.168.88.0
```

---

## 7. Dual Turbonet WAN (failover)

Wire **Turbonet LAN → MikroTik ether1** and **Turbonet LAN → MikroTik ether2**.

### 7.1 One Turbonet now — second Turbonet later

**Plug-in guide (ether2 only):** [**MIKROTIK_SECOND_TURbonet_ETHER2.md**](MIKROTIK_SECOND_TURbonet_ETHER2.md)

You can run **all commands below** with only **ether1** connected. Pre-configure **ether2** anyway; when the second Turbonet arrives, plug it into **ether2** — no router reset needed.

| State | What happens |
|-------|----------------|
| **Now** (only Turbonet on ether1) | Internet works via WAN 1; ether2 DHCP shows “searching” — normal |
| **Later** (plug 2nd Turbonet into ether2) | ether2 gets an IP; backup route becomes active if WAN 1 fails |

**Now:** connect the single Turbonet to **ether1** only. Run the full block below. For the **ether2 default route**, either:

- Wait until the second Turbonet is plugged in, then add the `distance=2` route using its gateway from `/ip dhcp-client print detail`, or  
- Add the ether2 route later in one step:

```routeros
/ip dhcp-client print detail
# When ether2 shows a gateway, then:
/ip route add dst-address=0.0.0.0/0 gateway=GATEWAY_ON_ETHER2 check-gateway=ping distance=2 comment="Backup-WAN2"
```

Until ether2 is connected, you only need the **ether1** default route (`distance=1`). Hotspot, walled garden, and pay work with one WAN.

```routeros
/ip dhcp-client add interface=ether1 disabled=no add-default-route=no use-peer-dns=yes comment="Turbonet-1"
/ip dhcp-client add interface=ether2 disabled=no add-default-route=no use-peer-dns=no comment="Turbonet-2"

/ip firewall nat add chain=srcnat out-interface=ether1 action=masquerade comment="NAT-WAN1"
/ip firewall nat add chain=srcnat out-interface=ether2 action=masquerade comment="NAT-WAN2"
```

Wait ~30 seconds, then read gateway IPs:

```routeros
/ip dhcp-client print detail
```

Add default routes (replace `GATEWAY_ON_ETHER1` / `GATEWAY_ON_ETHER2` with values from `print detail`):

```routeros
/ip route add dst-address=0.0.0.0/0 gateway=GATEWAY_ON_ETHER1 check-gateway=ping distance=1 comment="Primary-WAN1"
/ip route add dst-address=0.0.0.0/0 gateway=GATEWAY_ON_ETHER2 check-gateway=ping distance=2 comment="Backup-WAN2"
```

**Single Turbonet only:** run the first line above; skip the `distance=2` route until ether2 has a gateway.

Test:

```routeros
/ping 8.8.8.8 count=5
```

When you plug in the second Turbonet, verify:

```routeros
/ip dhcp-client print
/ip route print where dst-address=0.0.0.0/0
/ping 8.8.8.8 count=3
```

If ether2 never got a route, add the `distance=2` route then (see above).

---

## 8. DNS and DHCP (students)

```routeros
/ip dns set servers=8.8.8.8,1.1.1.1 allow-remote-requests=yes

/ip pool add name=hotspot-pool ranges=192.168.88.10-192.168.88.254
/ip dhcp-server add name=dhcp-hotspot interface=bridge-lan address-pool=hotspot-pool disabled=no
/ip dhcp-server network add address=192.168.88.0/24 gateway=192.168.88.1 dns-server=192.168.88.1
```

---

## 9. Wi‑Fi (open — 2.4 GHz + 5 GHz, same SSID)

Students join **without a Wi‑Fi password**. SSID: **`Tesnet@0200504248`** on both bands. The **hotspot** shows the captive portal; they pay or enter a voucher before real internet access.

**Check interface names** (vary by model):

```routeros
/interface wireless print
```

Common on hAP dual-band: **`wlan1`** = 2.4 GHz, **`wlan2`** = 5 GHz. If you only have `wlan1`, use the 2.4 GHz block only.

### 9.1 Open security profile (once)

```routeros
/interface wireless security-profiles add name=tesnet-open mode=none authentication-types=""
```

If it already exists, skip or run:

```routeros
/interface wireless security-profiles set tesnet-open mode=none authentication-types=""
```

### 9.2 Add both radios to the LAN bridge

```routeros
/interface bridge port add bridge=bridge-lan interface=wlan1
/interface bridge port add bridge=bridge-lan interface=wlan2
```

(Skip lines that error with “already added”.)

### 9.3 2.4 GHz (`wlan1`)

```routeros
/interface wireless set wlan1 mode=ap-bridge band=2ghz-b/g/n \
    ssid="Tesnet@0200504248" security-profile=tesnet-open \
    country=ghana frequency-mode=regulatory-domain disabled=no
```

### 9.4 5 GHz (`wlan2`)

```routeros
/interface wireless set wlan2 mode=ap-bridge band=5ghz-a/n/ac \
    ssid="Tesnet@0200504248" security-profile=tesnet-open \
    country=ghana frequency-mode=regulatory-domain disabled=no
```

On older models without ac, try `band=5ghz-a/n` instead of `5ghz-a/n/ac`.

### 9.5 Verify

```routeros
/interface wireless print
/interface bridge port print where bridge=bridge-lan
```

Both WLANs should show the same SSID, `security-profile=tesnet-open`, `R` not running disabled.

**Flow:** join open Wi‑Fi → browser opens `http://192.168.88.1` (login page) → pay on `pay.tesnet.xyz` (walled garden) or enter code → then full internet.

### 9.6 RouterOS 7.x with `/interface wifi` (wifiwave2) only

If `/interface wireless` is missing and you have **`/interface wifi`** instead, use Winbox **WiFi** to create one **open** AP configuration with SSID `Tesnet@0200504248` on 2.4 GHz and 5 GHz, bound to `bridge-lan`. Hotspot stays on `bridge-lan` — same behaviour.

---

## 10. Hotspot (vouchers on router, no RADIUS)

```routeros
/ip hotspot profile add name=tesnet-profile \
    html-directory=hotspot \
    login-by=http-pap,cookie \
    http-cookie-lifetime=1d \
    use-radius=no \
    trial-uptime=0s \
    trial-uptime-limit=0s \
    open-status-page=http-login

/ip hotspot add name=tesnet-hotspot interface=bridge-lan \
    address-pool=hotspot-pool profile=tesnet-profile disabled=no
```

---

## 11. Walled garden (pay before login)

Students are **not** authenticated when they open Paystack. **HTTPS checkout needs both tables** on most RouterOS versions:

| Table | CLI path | Used for |
|-------|----------|----------|
| HTTP walled garden | `/ip hotspot walled-garden` | Hostname / redirect matching |
| IP walled garden | `/ip hotspot walled-garden ip` | **HTTPS** to Paystack (required) |

**Import script (recommended):** upload `MiniISP-Landing-page/mikrotik script for rsv-cvs/tesnet-walled-garden.rsc` → `/import file-name=tesnet-walled-garden.rsc`

**Or paste manually:**

```routeros
# HTTP walled garden
/ip hotspot walled-garden add dst-host=pay.tesnet.xyz action=allow comment="TesNet Pay"
/ip hotspot walled-garden add dst-host=checkout.paystack.com action=allow comment="Paystack checkout"
/ip hotspot walled-garden add dst-host=standard.paystack.co action=allow comment="Paystack standard"
/ip hotspot walled-garden add dst-host=api.paystack.co action=allow comment="Paystack API"
/ip hotspot walled-garden add dst-host=js.paystack.co action=allow comment="Paystack JS"
/ip hotspot walled-garden add dst-host=*.paystack.com action=allow comment="Paystack subdomains"

# IP walled garden (HTTPS — fixes ERR_CONNECTION_CLOSED on checkout)
/ip hotspot walled-garden ip add dst-host=pay.tesnet.xyz action=accept comment="TesNet Pay"
/ip hotspot walled-garden ip add dst-host=checkout.paystack.com action=accept comment="Paystack checkout"
/ip hotspot walled-garden ip add dst-host=standard.paystack.co action=accept comment="Paystack standard"
/ip hotspot walled-garden ip add dst-host=api.paystack.co action=accept comment="Paystack API"
/ip hotspot walled-garden ip add dst-host=js.paystack.co action=accept comment="Paystack JS"
```

Verify — **no** open-internet rules:

```routeros
/ip hotspot walled-garden print
/ip hotspot walled-garden ip print
```

### Paystack checkout fails on phone (`net::ERR_CONNECTION_CLOSED`)

| Cause | Fix |
|-------|-----|
| Walled garden not configured | Run `tesnet-walled-garden.rsc` above |
| Only HTTP table, no **IP** table | Add `/ip hotspot walled-garden ip` entries for `checkout.paystack.com` |
| Home server / tunnel down | `pay.tesnet.xyz` must load in phone browser **before** login |
| Paystack opens but page blank | Add `public-files-paystack-prod.s3.eu-west-1.amazonaws.com` to HTTP walled garden (in `.rsc` script) |

**Test before login (on student Wi‑Fi):**

1. Browser → `https://pay.tesnet.xyz/` — should load  
2. Tap a package → `https://checkout.paystack.com/...` — should load (not connection closed)

| Never add | Why |
|-----------|-----|
| `dst-address=0.0.0.0/0` | Free internet before login |
| Public DNS IPs as “allow all” | Same leak |

---

## 12. Upload portal files

**Winbox → Files → folder `hotspot`**

From `MiniISP-Landing-page/Mikrotik pages/` on your PC:

| Upload | Required |
|--------|----------|
| `login.html` | Yes |
| `logout.html` | Yes |
| `status.html` | Yes |
| `portal.css` | Yes |
| `portal-login.js` | Yes |
| `portal-packages.js` | Yes |
| `portal-theme.js` | Yes |
| `tesnet-logo.png` | Yes |

**Do not upload:** `login-preview.html`, `connect.html`

Confirm in `login.html`:

```javascript
var PAY_BASE = 'https://pay.tesnet.xyz';
```

Reload hotspot:

```routeros
/ip hotspot set tesnet-hotspot disabled=yes
/ip hotspot set tesnet-hotspot disabled=no
```

---

## 13. Security cleanup

```routeros
/ip hotspot ip-binding print
/ip hotspot ip-binding remove [find type=bypassed]
```

Remove trial or sample users you do not need. Production vouchers are `/ip hotspot user` entries you create or import.

---

## 14. Smoke test

### Test voucher (temporary)

```routeros
/ip hotspot user add name=TNTEST12345 password=TNTEST12345 profile=default \
    server=all limit-bytes-total=1073741824 disabled=no
```

On a phone:

1. Join **Tesnet@0200504248** Wi‑Fi.
2. Open hotspot login (`http://192.168.88.1`).
3. Enter **TNTEST12345** as voucher (username = password).
4. **Buy Internet** tab — shows “packages being updated” until catalog is configured in `config.php` and `login.html`.

### Pay reachability (walled garden)

On student Wi‑Fi (before login), browser should load `https://pay.tesnet.xyz/`.

---

## 15. Save backup

```routeros
/export file=tesnet-production
```

Download from Winbox **Files**.

---

## 16. Packages and vouchers (when catalog is ready)

1. Create hotspot **user profiles** on MikroTik (names must match `config.php` exactly).
2. Generate users — scripts in `MiniISP-Landing-page/mikrotik script for rsv-cvs/`.
3. Add packages to `hotspot-pay/config.php` and `profile_to_slug`.
4. Update `login.html` package cards and `PKG_SLUG`; re-upload to router.
5. Export CSV → import at `https://pay.tesnet.xyz/admin/import.php`.

Guides:

- [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md)
- [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md)

---

## 17. Troubleshooting

| Symptom | Check |
|---------|--------|
| No login page | Files in `hotspot/`; profile `html-directory=hotspot` |
| Paystack / pay page won’t load | Walled garden **HTTP + IP** tables; both Turbonets online |
| `checkout.paystack.com` ERR_CONNECTION_CLOSED | Add IP walled garden for `checkout.paystack.com` — see `tesnet-walled-garden.rsc` |
| Internet after login fails | NAT on ether1/ether2; default routes |
| `pay.tesnet.xyz` down | Home Turbonet #1 + ProBook + `cloudflared` |
| Paid but no code | Paystack webhook; voucher stock in admin |
| Code invalid on login | User missing on MikroTik; name = password |
| Buy tab does nothing | Empty `PKG_SLUG` — add packages first |
| One Turbonet dies | Failover route should use backup WAN |

---

## 18. Site checklist

```text
[ ] Factory reset + admin password
[ ] ether1 + ether2 → Turbonets (DHCP, NAT, failover routes)
[ ] bridge-lan → 192.168.88.1, DHCP, Wi‑Fi SSID
[ ] Hotspot on bridge-lan (no trial, no RADIUS)
[ ] Walled garden (pay.tesnet.xyz + Paystack)
[ ] Portal files uploaded to /hotspot
[ ] PAY_BASE = https://pay.tesnet.xyz
[ ] Server hotspot_login_url = http://192.168.88.1
[ ] Test voucher login
[ ] Export tesnet-production backup
[ ] (Later) profiles, vouchers, import, PKG_SLUG
```

---

## Related docs

| Doc | Topic |
|-----|--------|
| [**MIKROTIK_SECOND_TURbonet_ETHER2.md**](MIKROTIK_SECOND_TURbonet_ETHER2.md) | Plug in 2nd site Turbonet on ether2 |
| [**HOTSPOT.md**](HOTSPOT.md) | Portal JS, walled garden, package sync |
| [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md) | ProBook server + tunnel |
| [**PAYSTACK.md**](PAYSTACK.md) | Webhooks and keys |
| [**HOTSPOT_VOUCHER_PAY.md**](HOTSPOT_VOUCHER_PAY.md) | Payment architecture |
