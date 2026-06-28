# Ubuntu server install — tesnet.xyz + pay.tesnet.xyz (Cloudflare Tunnel)

Fresh **HP ProBook (Ubuntu Server 26.04 LTS or newer)** setup for:

| Hostname | Purpose | Apache docroot |
|----------|---------|----------------|
| **tesnet.xyz** / **www.tesnet.xyz** | Marketing site | `/var/www/MiniISP-Landing-page` |
| **pay.tesnet.xyz** | Paystack voucher pay + admin | `/var/www/MiniISP-Landing-page/hotspot-pay/public` |

**Prerequisite:** install Ubuntu on the ProBook first — [**UBUNTU_SERVER_FIRST_SETUP.md**](UBUNTU_SERVER_FIRST_SETUP.md) (USB, wipe disk, DHCP, SSH).

HTTPS is terminated at **Cloudflare**; the tunnel connects to Apache on **port 80** on the ProBook. No need to open port 443 on the MikroTik for the pay server.

MikroTik hotspot setup is a **separate step** after this — see [**HOTSPOT.md**](HOTSPOT.md).

---

## Choose your layout

| | **Layout A — Server at site** | **Layout B — Server at home** *(TesNet choice)* |
|---|------------------------------|--------------------------------------------------|
| ProBook network | MikroTik LAN `192.168.88.2` | Turbonet/home router (DHCP) |
| MikroTik | Same building, LAN cable | Hostel/site, own WAN |
| `pay.tesnet.xyz` | Works (Cloudflare tunnel) | Works (Cloudflare tunnel) |
| Router admin from server | Easy (`192.168.88.1` on LAN) | Use laptop on site or Winbox over WAN* |

\* Avoid exposing Winbox to the public internet; configure the router when you are on site.

**Layout B** is valid: students pay via **internet → Cloudflare → home server**. The ProBook does **not** need to be on the MikroTik LAN.

```text
  HOME                          SITE (hostel)
  ────                          ─────────────
  Turbonet ──► ProBook           ISP ──► MikroTik 192.168.88.1 ──► Wi‑Fi students
                  │                              │
                  └── cloudflared ──► Cloudflare ◄── students open pay.tesnet.xyz
```

Use **§1B** for netplan below, then continue from **§2** for all other steps (same for both layouts).

---

## Before you wipe the old server (if it still boots)

Copy to a USB stick or your PC:

```bash
# Adjust paths if your old layout differed
cp ~/.../hotspot-pay/config.local.php ~/backup/
cp ~/.../hotspot-pay/storage/pool.sqlite ~/backup/
sudo cp -r /etc/cloudflared ~/backup/
```

You need **`config.local.php`** (Paystack keys, admin password). SQLite is optional if you are starting with a new package catalog.

---

## 1. Network (ProBook)

### 1A — Server on MikroTik LAN (optional)

```text
[ Internet ] ──► MikroTik 192.168.88.1 ──► Wi‑Fi
                      │
                      └── LAN ──► ProBook 192.168.88.2
```

```yaml
# /etc/netplan/00-installer-config.yaml
network:
  version: 2
  ethernets:
    enp0s31f6:
      dhcp4: no
      addresses:
        - 192.168.88.2/24
      routes:
        - to: default
          via: 192.168.88.1
      nameservers:
        addresses:
          - 192.168.88.1
          - 1.1.1.1
```

```bash
sudo netplan apply
ping -c 3 192.168.88.1
ping -c 3 1.1.1.1
```

### 1B — Server at home on Turbonet *(Layout B)*

ProBook plugs into the **Turbonet/home router** (Wi‑Fi or Ethernet). **Do not** use `192.168.88.2` — that subnet only exists at the MikroTik site.

```yaml
# /etc/netplan/00-installer-config.yaml
network:
  version: 2
  ethernets:
    enp0s31f6:
      dhcp4: true
```

Or Wi‑Fi during install — ensure the server has a **stable** connection (Ethernet preferred; or reserve DHCP for the ProBook MAC in the Turbonet router).

```bash
sudo netplan apply
ip a
ping -c 3 1.1.1.1
curl -sI https://cloudflare.com | head -1
```

**`config.local.php`** — keep hotspot URL pointing at the **router at the site**, not the home server:

```php
'hotspot_login_url' => 'http://192.168.88.1',
```

Students on TesNet Wi‑Fi open that IP locally; it is correct even though the pay server is at home.

**MikroTik at the site** (when you configure it):

- LAN `192.168.88.1/24`, DHCP for clients, hotspot enabled
- WAN: Turbonet/ISP at the hostel (separate from home)
- Walled garden: `pay.tesnet.xyz`, `*.paystack.com`, `js.paystack.co`, `api.paystack.co`
- `login.html`: `PAY_BASE = 'https://pay.tesnet.xyz'`

**Voucher workflow (two places, no LAN link):**

1. Generate users on MikroTik (on site) → export → CSV  
2. Import CSV at `https://pay.tesnet.xyz/admin/import.php` (from home)  
3. Codes must match on **both** router and SQLite pool  

**Power:** ProBook at home should stay on **AC power** and the Turbonet link up — if home internet drops, `pay.tesnet.xyz` goes offline for everyone.

---

## 2. Base packages

Requires **Ubuntu 26.04+** (default PHP **8.5**). Confirm version:

```bash
lsb_release -a
sudo apt update && sudo apt upgrade -y
```

Install Apache and PHP:

```bash
sudo apt install -y curl git unzip apache2 \
  php8.5 php8.5-cli libapache2-mod-php8.5 php8.5-sqlite3 php8.5-curl php8.5-xml

sudo a2enmod rewrite
sudo systemctl enable apache2
sudo systemctl start apache2
```

Verify (expect PHP 8.5.x):

```bash
php -v
php -m | grep -E 'pdo_sqlite|curl|openssl'
```

If `php8.5` packages are missing, your Ubuntu is below 26.04 — upgrade the OS before continuing.

---

## 3. Clone repositories

```bash
sudo mkdir -p /var/www
sudo chown "$USER:$USER" /var/www
cd /var/www

git clone https://github.com/reginaldtesla/MiniISP-Landing-page.git
cd MiniISP-Landing-page
git clone https://github.com/reginaldtesla/hotspot-pay.git
```

If `git clone` fails, use a **GitHub personal access token** or **SSH deploy key** — fix auth on the new server, not the old one.

```bash
# Ownership for Apache
sudo chown -R www-data:www-data /var/www/MiniISP-Landing-page
sudo chmod 750 /var/www/MiniISP-Landing-page/hotspot-pay/storage
```

---

## 4. Apache virtual hosts

```bash
cd /var/www/MiniISP-Landing-page/hotspot-pay

sudo cp deploy/apache-tesnet.xyz.conf.example /etc/apache2/sites-available/tesnet.xyz.conf
sudo cp deploy/apache-pay.tesnet.xyz.conf.example /etc/apache2/sites-available/pay.tesnet.xyz.conf

# Confirm DocumentRoot paths inside both files match:
#   tesnet.xyz     → /var/www/MiniISP-Landing-page
#   pay.tesnet.xyz → /var/www/MiniISP-Landing-page/hotspot-pay/public

sudo a2dissite 000-default.conf
sudo a2ensite tesnet.xyz.conf pay.tesnet.xyz.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Local test on the ProBook:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: tesnet.xyz" http://127.0.0.1/
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: pay.tesnet.xyz" http://127.0.0.1/
```

Expect **200** for both.

---

## 5. hotspot-pay secrets (`config.local.php`)

```bash
cd /var/www/MiniISP-Landing-page/hotspot-pay
sudo cp config.local.php.example config.local.php
sudo nano config.local.php
sudo chown www-data:www-data config.local.php
sudo chmod 640 config.local.php
```

**Production values:**

```php
return [
    'app_url' => 'https://pay.tesnet.xyz',
    'dev_success_preview' => false,
    'paystack_public_key' => 'pk_live_xxxx',   // or pk_test_ for testing
    'paystack_secret_key' => 'sk_live_xxxx',
    'admin_password' => 'your-strong-password',
    'checkout_email' => 'checkout@tesnet.xyz',
    'hotspot_login_url' => 'http://192.168.88.1',
];
```

Restore **`storage/pool.sqlite`** from backup if you kept sales/stock; otherwise SQLite is created on first request.

---

## 6. Cloudflare Tunnel

Domain **tesnet.xyz** must use Cloudflare nameservers (already done on your account).

### 6.1 Install cloudflared

```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb
cloudflared --version
```

### 6.2 Login and create tunnel

```bash
cloudflared tunnel login
# Opens browser — authorize tesnet.xyz zone

cloudflared tunnel create tesnet-pay
# Note the tunnel UUID printed; credentials JSON is in ~/.cloudflared/
```

### 6.3 Config file

```bash
sudo mkdir -p /etc/cloudflared
sudo cp ~/.cloudflared/<TUNNEL-UUID>.json /etc/cloudflared/tesnet-pay.json
sudo nano /etc/cloudflared/config.yml
```

```yaml
tunnel: <TUNNEL-UUID>
credentials-file: /etc/cloudflared/tesnet-pay.json

ingress:
  - hostname: pay.tesnet.xyz
    service: http://localhost:80
  - hostname: tesnet.xyz
    service: http://localhost:80
  - hostname: www.tesnet.xyz
    service: http://localhost:80
  - service: http_status:404
```

### 6.4 DNS routes (Cloudflare)

```bash
cloudflared tunnel route dns tesnet-pay pay.tesnet.xyz
cloudflared tunnel route dns tesnet-pay tesnet.xyz
cloudflared tunnel route dns tesnet-pay www.tesnet.xyz
```

In **Cloudflare Dashboard → DNS**, each record should be a **CNAME** to `<uuid>.cfargotunnel.com` (Proxied / orange cloud).

### 6.5 Run as a service

```bash
sudo cloudflared service install
sudo systemctl enable cloudflared
sudo systemctl start cloudflared
sudo systemctl status cloudflared
```

### 6.6 Cloudflare SSL mode

**SSL/TLS → Overview → Full** (not “Full strict” unless you install a cert on Apache).

---

## 7. Paystack

In [Paystack Dashboard](https://dashboard.paystack.com) → **Settings → API Keys & Webhooks**:

| Setting | Value |
|---------|--------|
| Webhook URL | `https://pay.tesnet.xyz/webhook.php` |
| Event | `charge.success` |

Keys in `config.local.php` must match **test** or **live** mode.

---

## 8. Smoke tests (from any PC on the internet)

```text
https://tesnet.xyz/                    → marketing landing
https://pay.tesnet.xyz/                → “TesNet Pay is running” (plain text)
https://pay.tesnet.xyz/admin/          → admin login
https://pay.tesnet.xyz/success.php?preview=1   → should NOT work (dev_success_preview false)
```

Paystack **webhook test** from dashboard → expect **200 OK** in Paystack logs.

---

## 9. Packages and vouchers (when catalog is ready)

Catalog is currently **empty** in `config.php` until you add new packages.

1. Add packages to `config.php` + `profile_to_slug`
2. Update `Mikrotik pages/login.html` and marketing `index.html`
3. Generate MikroTik users → CSV → **Admin → Import**
4. Re-upload portal files to the router

Guides: [**ADD_NEW_PACKAGE.md**](ADD_NEW_PACKAGE.md), [**VOUCHER_REFILL_GUIDE.md**](VOUCHER_REFILL_GUIDE.md)

---

## 10. MikroTik (after server is live)

Follow the full on-site guide: [**MIKROTIK_SITE_SETUP.md**](MIKROTIK_SITE_SETUP.md) (factory reset, dual Turbonet WAN on ether1/ether2, hotspot, walled garden, portal upload).

Summary:

1. Reset / configure router — LAN `192.168.88.1`, DHCP, hotspot
2. Upload flat files from `Mikrotik pages/` (not `login-preview.html` or `connect.html`)
3. **Walled garden:** `pay.tesnet.xyz`, `*.paystack.com`, `js.paystack.co`, `api.paystack.co`
4. `login.html`: `PAY_BASE = 'https://pay.tesnet.xyz'`

Portal integration: [**HOTSPOT.md**](HOTSPOT.md)

---

## Install checklist

```text
[ ] Ubuntu 26.04+; Apache + PHP 8.5 (pdo_sqlite, curl)
[ ] MiniISP-Landing-page + hotspot-pay cloned under /var/www
[ ] tesnet.xyz + pay.tesnet.xyz vhosts enabled
[ ] config.local.php (app_url https://pay.tesnet.xyz, hotspot_login_url http://192.168.88.1, dev_success_preview false)
[ ] storage/ writable by www-data (750)
[ ] cloudflared tunnel + DNS for tesnet.xyz, www, pay.tesnet.xyz
[ ] https://pay.tesnet.xyz/admin/ loads
[ ] Paystack webhook registered
[ ] MikroTik at site: see MIKROTIK_SITE_SETUP.md (portal + walled garden + dual Turbonet WAN)
[ ] Packages + voucher import (when catalog ready)
```

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| 502 / tunnel error | `sudo journalctl -u cloudflared -f` |
| 404 on pay host | Apache `pay.tesnet.xyz` vhost DocumentRoot → `.../public` |
| Admin 500 / DB error | `php -m \| grep sqlite`; `storage/` permissions |
| Webhook 401 | `paystack_secret_key` matches Paystack mode |
| Paystack won’t open on phone | MikroTik walled garden (router step) |
| Wrong site on hostname | `/etc/cloudflared/config.yml` ingress order |

---

## Related docs

- [**UBUNTU_SERVER_FIRST_SETUP.md**](UBUNTU_SERVER_FIRST_SETUP.md) — install Ubuntu from USB (start here)
- [**README.md**](../README.md) — project overview
- [**PAYSTACK.md**](PAYSTACK.md) — keys and webhooks
- [**HOTSPOT.md**](HOTSPOT.md) — MikroTik portal
