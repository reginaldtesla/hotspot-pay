# MikroTik hotspot → Laravel portal

TesNet no longer relies on PHPNuxBill or voucher HTML for day-to-day login. The **authoritative** login and payment UI is the Laravel app on the billing server.

## Target flow

1. Student associates to Wi‑Fi and gets a hotspot login page from MikroTik.
2. MikroTik redirects (or links) to **`https://<your-server>/portal/login`** (walled garden must allow this host).
3. Student signs in with **phone + password** (same credentials synced to FreeRADIUS).
4. After purchase, **Connect Wi‑Fi** posts to `MIKROTIK_LOGIN_URL` with a **hidden per-purchase** hotspot username (`tn-{purchase_id}`) and password — students still only know their **phone + portal password** for sign-in.

## Walled garden

Add your Laravel host to MikroTik **IP → Hotspot → Walled Garden** (and DNS if you use a hostname), for example:

- Billing server IP: `192.168.88.2`
- Or FQDN used in `APP_URL`

Students must reach `/portal/login`, `/portal/register`, and Paystack-related paths **before** they have an active session.

## Retiring `login.html`

Legacy files in the repo (`login.html`, `TesNet/login.html`, `flash/hotspot/login.html`) redirected users to PHPNuxBill. For production:

1. **Do not** upload a custom `login.html` that points to the old billing UI unless you still need a transitional redirect.
2. Prefer MikroTik **Hotspot Server Profile** → **HTML** settings, or a minimal `login.html` that only redirects:

   ```html
   <meta http-equiv="refresh" content="0;url=https://YOUR_DOMAIN/portal/login?$(link-login-only)">
   ```

   Adjust for your captive portal variables (`$(link-login-only)`, `$(link-orig)`, etc.) per RouterOS docs.

3. Set **`MIKROTIK_LOGIN_URL`** in `.env` to the router’s HTTP login endpoint (e.g. `http://192.168.88.1/login`) used after the student is authorized in RADIUS.

## Per-purchase hotspot users (Model A)

When `TESNET_PER_PURCHASE_HOTSPOT=true` (default):

| Identity | Purpose |
|----------|---------|
| Phone + password | Portal login only (`users` table + `radcheck` for registration) |
| `tn-{purchase_id}` + random password | Hotspot data bucket for **that payment only** |

On Paystack success, Laravel creates `/ip/hotspot/user` **`tn-*`** (via API), syncs matching **`radcheck`/`radreply`**, and disables the previous purchase’s `tn-*` user.

**Router setup (once):** create profiles `tesnet-pkg` and `tesnet-custom` with `shared-users=1` and your rate limits. See `PROBOOK_MIKROTIK_FULL_SETUP.md` § Model A profiles.

**Cron:** `tesnet:cleanup-hotspot-users` weekly removes old `tn-*` users.

## One account, one device (no sharing)

- One **phone number** per registered student (duplicate registration blocked).
- **`device_limit`** is always **1** for students (RADIUS `Simultaneous-Use` + MikroTik `shared-users=1` on profiles).
- **New portal login** bumps `portal_session_version` — other browsers are signed out (`portal.single_session` middleware).
- **Connect** disconnects any other active hotspot session for that account before opening a new one.
- Students are told not to share passwords on login/register screens.

This stops two people using one account **at the same time**. It does not stop someone from handing over their password for use later (that is policy/trust).

Legacy purchases without `mikrotik_username` still use phone-based RADIUS limits until the student buys again.

## FreeRADIUS

- Portal accounts: `radcheck` on the **phone** (Cleartext-Password, Simultaneous-Use).
- Per-purchase data: `radcheck`/`radreply` on **`tn-{id}`** (password, `Mikrotik-Total-Limit`, rate limit).
- Dashboard usage uses **`radacct` + `package_purchases.bytes_consumed` + MikroTik API** scoped to the active **`tn-*`** username.
- Hotspot profile must have **`radius-accounting=yes`** so `radacct` fills in MariaDB.

## When a package runs out

1. Cron / **Connect** marks the purchase **depleted** and kicks the student off hotspot (**`MIKROTIK_API_ENABLED=true`** required for automatic kick).
2. The phone should show **Sign in to network** again → MikroTik **`login.html`** must redirect to **`/portal/login`** (use `TesNet/mikrotik/login.html`, not the legacy voucher page in `TesNet/login.html`).
3. Student signs in on the portal and buys a new package under **Buy Data**.
4. **Connect to Internet** applies the new quota — do **not** rely on `Auth-Type Reject` for quota (only for suspended accounts); reject blocks the captive-portal flow.

If students stay online after data ends, upload the redirect `login.html` and enable the MikroTik API on the ProBook.
- MikroTik hotspot auth for data sessions uses **`tn-{purchase_id}`** (RADIUS + optional local `/ip/hotspot/user` row for byte caps).

## Announcements

Admins post global notices under **Admin → Notifications**. Students see the latest active notice as a **modal** on the dashboard (“Got it” stores dismissal in `localStorage`).

## Checklist

- [ ] Laravel reachable from hotspot clients (walled garden)
- [ ] `APP_URL` matches the URL students use (HTTPS in production)
- [ ] FreeRADIUS + MikroTik RADIUS client configured
- [ ] Legacy PHPNuxBill `login.html` removed or replaced with Laravel redirect
