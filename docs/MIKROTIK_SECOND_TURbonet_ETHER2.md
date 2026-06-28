# Plug in the second Turbonet (ether2)

Quick guide for **TesNet-HAP** when you connect **Turbonet #3** to **MikroTik ether2** for WAN failover.

**Full site build:** [**MIKROTIK_SITE_SETUP.md**](MIKROTIK_SITE_SETUP.md)

---

## Your layout (3 Turbonets)

```text
HOME
  Turbonet #1 ──► ProBook (pay.tesnet.xyz via Cloudflare)

HOSTEL / SITE
  Turbonet #2 ──► MikroTik ether1  (WAN 1, primary)
  Turbonet #3 ──► MikroTik ether2  (WAN 2, backup)
                      │
                      └── Wi‑Fi hotspot 192.168.88.1
```

| Turbonet | Where | MikroTik port | Role |
|----------|--------|---------------|------|
| #1 | Home | — | Billing server only |
| #2 | Site | **ether1** | Primary internet |
| #3 | Site | **ether2** | Backup if #2 fails |

`pay.tesnet.xyz` runs on the **home** ProBook. Site Turbonets only give students an internet path to reach Cloudflare and Paystack.

---

## What was already preconfigured (TesNet-HAP)

If you followed [**MIKROTIK_SITE_SETUP.md**](MIKROTIK_SITE_SETUP.md) §7 with one Turbonet on ether1, you should already have:

| Item | Expected on router |
|------|---------------------|
| DHCP client ether1 | `Turbonet-1` · `status=bound` |
| DHCP client ether2 | `Turbonet-2` · `INVALID` until cable plugged — **normal** |
| NAT | `NAT-WAN1` on ether1, `NAT-WAN2` on ether2 |
| Default route | `Primary-WAN1` · `distance=1` only (until ether2 is live) |

**Verify now (one Turbonet on ether1):**

```routeros
/ip dhcp-client print detail
/ip firewall nat print where comment~"NAT-WAN"
/ip route print where dst-address=0.0.0.0/0
```

**Healthy output (before 2nd Turbonet):**

```text
client1 (ether1)  status=bound   gateway=192.168.0.1
client2 (ether2)  status=stopped  "Interface not active"  ← OK

NAT-WAN1  out-interface=ether1
NAT-WAN2  out-interface=ether2

0.0.0.0/0  gateway=192.168.0.1  distance=1  comment=Primary-WAN1
```

If **NAT-WAN2** is missing, add it before plugging ether2:

```routeros
/ip firewall nat add chain=srcnat out-interface=ether2 action=masquerade comment="NAT-WAN2"
```

---

## Plug in Turbonet #3 (step by step)

### 1. Physical wiring

```text
Turbonet #3  [LAN port] ── ethernet cable ── MikroTik ether2
```

- Use a **LAN** port on the Turbonet (not WAN if labelled separately).
- Power on Turbonet #3.
- Wait **30–60 seconds**.

### 2. Check DHCP on ether2

```routeros
/ip dhcp-client print detail where interface=ether2
```

**Success looks like:**

```text
status=bound
address=192.168.0.x/24
gateway=192.168.0.1    (or another 192.168.x.1 — use what print shows)
```

If still `INVALID` or `stopped`:

- Reseat the cable (Turbonet LAN → ether2).
- Confirm ether2 is not in `bridge-lan` (`/interface bridge port print`).
- Reboot Turbonet #3.

### 3. Add backup default route (one-time)

Use the **gateway** from step 2 (`print detail`):

```routeros
/ip route add dst-address=0.0.0.0/0 gateway=GATEWAY_FROM_ETHER2 check-gateway=ping distance=2 comment="Backup-WAN2"
```

**Example** (if gateway is `192.168.0.1`):

```routeros
/ip route add dst-address=0.0.0.0/0 gateway=192.168.0.1 check-gateway=ping distance=2 comment="Backup-WAN2"
```

Both Turbonets may use the same gateway IP (`192.168.0.1`). That is fine — RouterOS uses **distance** for failover (`1` = primary, `2` = backup).

### 4. Verify failover routes

```routeros
/ip route print where dst-address=0.0.0.0/0
/ping 8.8.8.8 count=5
```

**Expected:**

```text
0.0.0.0/0  gateway=...  distance=1  Primary-WAN1
0.0.0.0/0  gateway=...  distance=2  Backup-WAN2
```

### 5. Smoke test (phone on student Wi‑Fi)

| Test | Expected |
|------|----------|
| Hotspot login page | Loads |
| Buy Internet → package → Paystack | Checkout opens |
| Voucher login after payment | Works |
| Browse after login | Internet works |

---

## What you do **not** need to redo

| Already on router | Change for ether2? |
|-------------------|-------------------|
| Hotspot / Wi‑Fi SSID | No |
| Walled garden / Paystack | No |
| Portal files (`login.html`, etc.) | No |
| Voucher users | No |
| `pay.tesnet.xyz` config on ProBook | No |

Only **WAN path** changes — hotspot and pay stay the same.

---

## How failover works

```text
Normal:     students → MikroTik → ether1 (Turbonet #2) → internet
If #2 dies: students → MikroTik → ether2 (Turbonet #3) → internet
```

Routes use `check-gateway=ping`. When the primary gateway stops responding, traffic uses the `distance=2` route.

**Home server:** If Turbonet #1 at home is down, `pay.tesnet.xyz` is unreachable from anywhere — site WAN failover does not fix that. Keep home Turbonet + ProBook online.

---

## Troubleshooting

| Symptom | Check |
|---------|--------|
| ether2 stays `INVALID` | Cable; Turbonet powered; ether2 not in bridge |
| ether2 bound but no internet | `NAT-WAN2` exists; backup route added |
| Only one default route | Add `Backup-WAN2` with `distance=2` |
| Pay works on ether1 but not after failover | Walled garden on both WANs is the same — re-test pay after unplugging ether1 cable (simulate failover) |
| `bad command name print` | Paste one line at a time; start with `/ip` |

**Useful commands:**

```routeros
/ip dhcp-client print detail
/ip firewall nat print
/ip route print where dst-address=0.0.0.0/0
/interface print
/ping 8.8.8.8 count=5
```

---

## Checklist

```text
[ ] NAT-WAN1 + NAT-WAN2 present
[ ] Primary route distance=1 (ether1)
[ ] Turbonet #3 LAN → ether2
[ ] ether2 DHCP bound + gateway known
[ ] Backup route distance=2 added
[ ] ping 8.8.8.8 OK
[ ] Paystack checkout OK on Wi‑Fi (before login)
[ ] Export backup: /export file=tesnet-dual-wan
```

---

## Related

| Doc | Topic |
|-----|--------|
| [**MIKROTIK_SITE_SETUP.md**](MIKROTIK_SITE_SETUP.md) | Full factory-reset site build |
| [**HOTSPOT.md**](HOTSPOT.md) | Portal + walled garden |
| [**PAYSTACK.md**](PAYSTACK.md) | Paystack + walled garden hosts |
| [**INSTALL_UBUNTU_CLOUDFLARE.md**](INSTALL_UBUNTU_CLOUDFLARE.md) | Home server + tunnel |
