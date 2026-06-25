PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS packages (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    data_label TEXT NOT NULL,
    amount_pesewas INTEGER NOT NULL,
    mikrotik_profile TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS voucher_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    package_slug TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'available',
    paystack_reference TEXT,
    buyer_email TEXT,
    buyer_phone TEXT,
    assigned_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (package_slug) REFERENCES packages (slug)
);

CREATE INDEX IF NOT EXISTS idx_voucher_available
    ON voucher_codes (package_slug, status, id);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    access_token TEXT NOT NULL DEFAULT '',
    package_slug TEXT NOT NULL,
    amount_pesewas INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    voucher_code_id INTEGER,
    buyer_email TEXT,
    buyer_phone TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    paid_at TEXT,
    FOREIGN KEY (package_slug) REFERENCES packages (slug),
    FOREIGN KEY (voucher_code_id) REFERENCES voucher_codes (id)
);

CREATE INDEX IF NOT EXISTS idx_payments_reference ON payments (reference);
