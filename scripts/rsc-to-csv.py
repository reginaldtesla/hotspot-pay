#!/usr/bin/env python3
"""
Convert MikroTik /ip hotspot user export (.rsc) to hotspot-pay import CSV.

Usage:
  python rsc-to-csv.py path/to/export.rsc
  python rsc-to-csv.py path/to/export.rsc -o data/new-vouchers.csv

Output columns: code,profile
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path


def parse_rsc(text: str) -> list[tuple[str, str]]:
    text = re.sub(r"\\\s*\n\s*", " ", text)
    rows: list[tuple[str, str]] = []
    seen: set[str] = set()

    for line in text.splitlines():
        line = line.strip()
        if not line.startswith("add ") or "limit-bytes-total" not in line:
            continue
        if "profile=" not in line:
            continue

        name_match = re.search(r"name=\s*([A-Z0-9]+)", line)
        profile_match = re.search(r"profile=([^\s]+)", line)
        if not name_match or not profile_match:
            continue

        code = name_match.group(1).upper()
        profile = profile_match.group(1)
        if code in seen:
            continue
        seen.add(code)
        rows.append((code, profile))

    rows.sort(key=lambda r: (r[1], r[0]))
    return rows


def main() -> int:
    parser = argparse.ArgumentParser(description="MikroTik hotspot user .rsc → CSV")
    parser.add_argument("rsc_file", help="Path to MikroTik export .rsc file")
    parser.add_argument(
        "-o",
        "--output",
        help="Output CSV path (default: same name as input with .csv)",
    )
    args = parser.parse_args()

    src = Path(args.rsc_file)
    if not src.is_file():
        print(f"Error: file not found: {src}", file=sys.stderr)
        return 1

    rows = parse_rsc(src.read_text(encoding="utf-8", errors="replace"))
    if not rows:
        print("Error: no hotspot users found in file.", file=sys.stderr)
        return 1

    out = Path(args.output) if args.output else src.with_suffix(".csv")
    lines = ["code,profile"] + [f"{code},{profile}" for code, profile in rows]
    out.write_text("\n".join(lines) + "\n", encoding="utf-8")

    counts: dict[str, int] = {}
    for _, profile in rows:
        counts[profile] = counts.get(profile, 0) + 1

    print(f"Wrote {len(rows)} codes to {out}")
    for profile in sorted(counts):
        print(f"  {profile}: {counts[profile]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
