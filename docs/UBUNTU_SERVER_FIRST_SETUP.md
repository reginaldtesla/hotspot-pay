# Ubuntu Server — first-time setup (TesNet ProBook)

Guide for installing **Ubuntu Server 26.04 LTS or newer** on the HP ProBook from scratch, before TesNet pay/marketing software.

**After this doc**, continue with [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md) (Apache, git, Cloudflare tunnel, Paystack).

---

## What you need

| Item | Notes |
|------|--------|
| HP ProBook | Target machine (AC power) |
| USB stick | 8 GB or larger |
| Windows PC | To create the installer USB (Rufus) |
| Ethernet cable | ProBook → Turbonet/home router (recommended) |
| Internet | Turbonet at home (Layout B) |

**Layout B (TesNet):** server stays **at home** on Turbonet; MikroTik runs at the **hostel/site** on a different network. No `192.168.88.2` on the ProBook at home.

---

## 1. Back up before wiping

If the old system still boots, copy to a USB stick or your Windows PC:

- `config.local.php` (Paystack keys, admin password)
- `storage/pool.sqlite` (optional — sales/voucher history)
- `/etc/cloudflared/` (tunnel credentials — or recreate tunnel later)

Everything else can be restored from GitHub after install.

---

## 2. Download Ubuntu Server

1. Open https://ubuntu.com/download/server  
2. Download **Ubuntu Server 26.04 LTS** (64-bit `.iso`) or newer.

Use the **Server** image, not Desktop.

---

## 3. Create a bootable USB (Windows + Rufus)

1. Install **Rufus**: https://rufus.ie  
2. Insert the USB stick (all data on it will be erased).  
3. In Rufus:

   | Setting | Value |
   |---------|--------|
   | Device | Your USB drive |
   | Boot selection | Click **SELECT** → choose the Ubuntu `.iso` |
   | Partition scheme | **GPT** (typical for modern laptops) |
   | Target system | **UEFI** |

4. Click **START** → confirm erasing the USB → wait until finished.  
5. Safely eject the USB.

---

## 4. Boot the ProBook from USB

1. Shut down the ProBook.  
2. Plug in the Ubuntu USB (and Ethernet to Turbonet if you have a cable).  
3. Power on and open the **boot menu** immediately:

   | HP laptops | Try **F9**, **F12**, or **Esc** then Boot Menu |

4. Select the USB drive (often labeled with the stick’s brand or “UEFI: …”).  
5. On the Ubuntu menu, choose **Try or Install Ubuntu Server** → **Install Ubuntu Server**.

**If it always boots Windows:** disable **Fast Startup** in Windows (Control Panel → Power options → Choose what power buttons do), or use Windows **Advanced startup → Use a device**.

---

## 5. Ubuntu installer — step by step

Walk through the screens as follows.

### Language, keyboard, installer

- Language: **English** (or your preference)  
- Keyboard: match your layout  
- Installer: **Ubuntu Server** (default)

### Network

- If Ethernet is connected: you may see an IP from Turbonet — good.  
- Leave as **DHCP / automatic** (Layout B).  
- **Do not** set static `192.168.88.2` here — that is for the MikroTik site only.

### Proxy

- Leave **empty** unless you use a corporate proxy.

### Mirror

- Default Ubuntu archive mirror is fine.

### Storage — this wipes the drive

This is the **format / erase** step.

1. Choose **Use an entire disk** (or “Guided – use entire disk”).  
2. Select the **internal** drive:

   | Type | Usually shows as |
   |------|------------------|
   | SSD (NVMe) | `nvme0n1` |
   | Older HDD/SSD | `sda` |

   **Do not** select the USB stick.

3. Confirm **Erase** / **Yes** when warned that all existing partitions and data will be destroyed.  
4. Optional: installer may offer **LVM** — accepting default is fine for a single-disk server.

Boot loader installs to the **internal disk** (same device as above).

### Profile setup

| Field | Suggested |
|-------|-----------|
| Your name | Your name |
| Server name (hostname) | `tesnet-server` |
| Username | e.g. `tesnet` |
| Password | Strong password — you will use this for `sudo` |

### SSH

- Enable **Install OpenSSH server** ✅  
  This lets you manage the machine from your Windows PC later.

### Featured snaps

- Skip extra snaps (none required for TesNet).

### Finish

- Select **Reboot now**.  
- When prompted, **remove the USB** and press Enter.

---

## 6. First login

1. At the console, log in with the username and password you created.  
2. Run updates:

```bash
sudo apt update
sudo apt upgrade -y
```

Reboot if the kernel was updated:

```bash
sudo reboot
```

---

## 7. Network configuration

### Layout B — server at home on Turbonet (recommended for TesNet)

Find the Ethernet interface name:

```bash
ip link
```

Look for `enp…`, `eno…`, or `eth0` (ignore `lo`).

Edit Netplan:

```bash
sudo nano /etc/netplan/00-installer-config.yaml
```

Use DHCP (replace interface name if different):

```yaml
network:
  version: 2
  ethernets:
    enp0s31f6:
      dhcp4: true
```

Apply and test:

```bash
sudo netplan apply
ip a
ping -c 3 1.1.1.1
ping -c 3 google.com
```

You should see an address like `192.168.0.x` or `192.168.1.x` — **not** `192.168.88.x`.

**Optional:** In the Turbonet router admin, reserve a **DHCP lease** for the ProBook’s MAC address so the home IP stays stable.

### Layout A — server on MikroTik LAN (only if ProBook is at the site)

Use static `192.168.88.2`, gateway `192.168.88.1` — see [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md) §1A.

---

## 8. SSH from your Windows PC (optional)

1. Find the ProBook IP: `ip a` on the server.  
2. From PowerShell on Windows:

```powershell
ssh tesnet@192.168.1.XXX
```

Replace user and IP. Accept the host key on first connect.

You can install and manage the server over SSH instead of sitting at the ProBook.

---

## 9. Basic firewall (optional at home)

On a home Turbonet network, many people skip firewall until the tunnel is up. If you want a minimal setup:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw enable
sudo ufw status
```

`pay.tesnet.xyz` does **not** need inbound ports on Turbonet — **Cloudflare Tunnel** connects outward.

---

## 10. Set timezone (Ghana)

```bash
sudo timedatectl set-timezone Africa/Accra
timedatectl
```

---

## 11. Checklist — base OS ready

```text
[ ] Ubuntu Server 26.04+ installed (entire disk wiped)
[ ] User created, OpenSSH enabled
[ ] apt update && apt upgrade completed
[ ] Network: DHCP on Turbonet (Layout B), internet works (ping google.com)
[ ] Timezone Africa/Accra
[ ] (Optional) SSH works from Windows PC
[ ] PHP 8.5 + Apache — see install guide §2
```

---

## 12. Next steps — TesNet software

Continue with [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md):

1. Install Apache + PHP 8.5 (`pdo_sqlite`, `curl`)  
2. Clone `MiniISP-Landing-page` and `hotspot-pay`  
3. Apache vhosts for `tesnet.xyz` and `pay.tesnet.xyz`  
4. `config.local.php`  
5. Cloudflare Tunnel for **tesnet.xyz** and **pay.tesnet.xyz**  
6. Paystack webhook  
7. MikroTik at the site (separate visit) — [**HOTSPOT.md**](HOTSPOT.md)

---

## Troubleshooting

| Problem | What to try |
|---------|-------------|
| Won’t boot from USB | Try another USB port; disable Secure Boot in BIOS (F10) temporarily; recreate USB with Rufus |
| Wrong disk selected in installer | Power off, restart installer; pick `nvme0n1` or `sda`, not the USB |
| No network after install | Check Ethernet cable; `ip link` — interface up?; fix netplan and `sudo netplan apply` |
| `ping 1.1.1.1` fails | Turbonet/router issue; reboot router; try DHCP again |
| Forgot password | Boot single-user/recovery is advanced — avoid by writing password down securely during install |

---

## Related docs

| Doc | Purpose |
|-----|---------|
| [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md) | TesNet Apache, tunnel, Paystack |
| [**HOTSPOT.md**](HOTSPOT.md) | MikroTik portal at the site |
| [**README.md**](../README.md) | hotspot-pay overview |
