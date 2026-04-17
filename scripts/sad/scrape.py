#!/usr/bin/env python3
"""
Scrape member history from sad.soli.nl (l_tinfo.php).

Usage:
    python3 scrape.py                    # scrape all members
    python3 scrape.py --limit 20         # scrape first 20 only
    python3 scrape.py --parse-only       # parse already-downloaded HTML files
    python3 scrape.py --delay 2          # 2 seconds between requests (default: 1)
"""
import argparse
import http.cookiejar
import json
import os
import re
import sys
import time
import urllib.parse
import urllib.request

BASE_URL = "https://sad.soli.nl/admin"
OUTPUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "html")
DATA_DIR = os.path.dirname(os.path.abspath(__file__))

USERNAME = os.environ.get("SAD_USERNAME", "")
PASSWORD = os.environ.get("SAD_PASSWORD", "")

# Dutch name prefixes for approximate name splitting
PREFIXES = [
    "van der", "van de", "van den", "van het", "van 't",
    "in de", "in het", "in 't", "op de", "op den", "op het",
    "aan de", "aan den", "aan het", "bij de", "uit de", "uit den",
    "voor de", "voor den", "over de",
    "van", "de", "den", "der", "het", "ter", "ten",
]


def split_name(full_name):
    """Approximate split of a Dutch full name into vnaam, tussen, anaam."""
    full_name = re.sub(r"\s+", " ", full_name).strip()
    parts = full_name.split(" ", 1)
    if len(parts) == 1:
        return parts[0], None, None

    vnaam = parts[0]
    rest = parts[1]

    for prefix in PREFIXES:
        if rest.lower().startswith(prefix + " ") and len(rest) > len(prefix) + 1:
            tussen = rest[: len(prefix)]
            anaam = rest[len(prefix) + 1 :]
            return vnaam, tussen, anaam

    return vnaam, None, rest


def create_session():
    """Create an authenticated session, returns an opener with cookies."""
    if not USERNAME or not PASSWORD:
        print("ERROR: Set SAD_USERNAME and SAD_PASSWORD environment variables")
        sys.exit(1)

    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    opener.open(f"{BASE_URL}/l_bar.php")
    sess_id = None
    for cookie in cj:
        if cookie.name == "PHPSESSID":
            sess_id = cookie.value
            break

    if not sess_id:
        print("ERROR: Could not get session ID")
        sys.exit(1)

    data = urllib.parse.urlencode({
        "luser": USERNAME,
        "pw": PASSWORD,
        "aktie": "Login",
        "sess_ret": sess_id,
    }).encode()
    resp = opener.open(f"{BASE_URL}/l_bar.php", data)
    html = resp.read().decode("latin-1")

    if "Uitloggen" not in html:
        print("ERROR: Login failed")
        sys.exit(1)

    print("Logged in successfully")
    return opener


def get_all_member_ids(opener):
    """Fetch active + ex-member IDs."""
    ids = set()

    for ex in [0, 1]:
        label = "ex-members" if ex else "active members"
        resp = opener.open(f"{BASE_URL}/l_lijst.php?sel=%25&ex={ex}")
        html = resp.read().decode("latin-1")
        found = set(int(x) for x in re.findall(r"newWin\((\d+)\)", html))
        print(f"  {label}: {len(found)}")
        ids.update(found)
        time.sleep(1)

    return sorted(ids)


def scrape_member(opener, lid_id):
    """Fetch l_tinfo.php for a single member, return HTML."""
    resp = opener.open(f"{BASE_URL}/l_tinfo.php?lid_id={lid_id}")
    return resp.read().decode("latin-1")


def parse_section_rows(html, section_name, next_separator="_________________"):
    """Extract rows from a named section in the HTML table."""
    if section_name not in html:
        return []
    section = html.split(section_name)[1].split(next_separator)[0]
    rows = []
    for row in re.finditer(
        r"<tr><td>\s*([\d-]*)\s*</td><td>\s*([\d-]*)\s*</td><td>\s*(.+?)\s*</td></tr>",
        section,
    ):
        rows.append({
            "van": row.group(1).strip() or None,
            "tot": row.group(2).strip() or None,
            "value": re.sub(r"<[^>]+>", " ", row.group(3)).strip(),
        })
    return rows


def parse_member_html(html):
    """Parse a single member overview HTML file."""
    result = {}

    # lid_id
    m = re.search(r"Lid_id:\s*(\d+)", html)
    if not m:
        return None
    result["lid_id"] = int(m.group(1))

    # Header block: name <br> street <br> postcode city <br> phone <br> gebdat <br>
    header = re.search(
        r"Lid_id:\s*\d+\s*<br>\s*(.+?)<br><br>",
        html,
        re.DOTALL,
    )
    if header:
        # Strip tags and split on <br>
        block = header.group(1)
        parts = [re.sub(r"<[^>]+>", "", p).strip() for p in block.split("<br>")]
        parts = [p for p in parts if p]

        if len(parts) >= 1:
            result["naam"] = parts[0]
            vnaam, tussen, anaam = split_name(parts[0])
            result["vnaam"] = vnaam
            result["tussen"] = tussen
            result["anaam"] = anaam

        # Parse remaining header fields by pattern
        for p in parts[1:]:
            if re.match(r"\d{2}-\d{2}-\d{4}$", p):
                result["gebdat"] = p
            elif re.match(r"\d{4}\s*[A-Z]{2}\s", p) or re.match(r"\d{4}\s+", p):
                # "2071 BP SANTPOORT NOORD" — postcode + plaats
                pc_match = re.match(r"(\d{4}\s*[A-Z]{2})\s+(.*)", p)
                if pc_match:
                    result["postcode"] = pc_match.group(1)
                    result["plaats"] = pc_match.group(2)
                else:
                    result["postcode"] = p
            elif re.match(r"[\d\s+()-]{7,}", p):
                result["telefoon"] = p
            elif "@" not in p and not p.startswith("E-mail"):
                result["straat"] = p

    # Email
    m = re.search(r'E-mail adres:\s*<a href="mailto:([^"]+)">', html)
    if m:
        result["email"] = m.group(1)

    # Lidmaatschap (van/tot only, no value column)
    result["lidmaatschap"] = []
    if "Lidmaatschap" in html:
        section = html.split("Lidmaatschap")[1].split("_________________")[0]
        for row in re.finditer(
            r"<tr><td>\s*([\d-]*)\s*</td><td>\s*([\d-]*)\s*</td></tr>",
            section,
        ):
            van, tot = row.group(1).strip(), row.group(2).strip()
            result["lidmaatschap"].append({"van": van or None, "tot": tot or None})

    # Onderdeel
    result["onderdeel"] = []
    if "Onderdeel" in html:
        section = html.split("Onderdeel")[1].split("_________________")[0]
        for row in re.finditer(
            r"<tr><td>\s*([\d-]*)\s*</td><td>\s*([\d-]*)\s*</td><td>\s*(\w+)",
            section,
        ):
            result["onderdeel"].append({
                "van": row.group(1).strip() or None,
                "tot": row.group(2).strip() or None,
                "onderdeel": row.group(3).strip(),
            })

    # Instrument
    result["instrument"] = []
    if "Instrument" in html:
        section = html.split("Instrument")[1].split("_________________")[0]
        for row in re.finditer(
            r"<tr><td>\s*([\d-]*)\s*</td><td>\s*([\d-]*)\s*</td><td>\s*(.+?)\s*</td></tr>",
            section,
        ):
            val = re.sub(r"<[^>]+>", "", row.group(3)).strip()
            if val:
                result["instrument"].append({
                    "van": row.group(1).strip() or None,
                    "tot": row.group(2).strip() or None,
                    "instrument": val,
                })

    return result


def main():
    parser = argparse.ArgumentParser(description="Scrape SAD member history")
    parser.add_argument("--limit", type=int, default=0, help="Max members to scrape (0=all)")
    parser.add_argument("--delay", type=float, default=1.0, help="Seconds between requests")
    parser.add_argument("--parse-only", action="store_true", help="Only parse existing HTML files")
    args = parser.parse_args()

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    if not args.parse_only:
        opener = create_session()

        print("Fetching member IDs...")
        all_ids = get_all_member_ids(opener)
        print(f"Total unique members: {len(all_ids)}")

        if args.limit:
            all_ids = all_ids[: args.limit]
            print(f"Limiting to first {args.limit}")

        # Skip already downloaded
        existing = set()
        for f in os.listdir(OUTPUT_DIR):
            if f.endswith(".html"):
                existing.add(int(f.replace(".html", "")))

        to_scrape = [lid for lid in all_ids if lid not in existing]
        print(f"Already downloaded: {len(existing)}, remaining: {len(to_scrape)}")

        for i, lid_id in enumerate(to_scrape):
            html = scrape_member(opener, lid_id)
            filepath = os.path.join(OUTPUT_DIR, f"{lid_id}.html")
            with open(filepath, "w", encoding="latin-1") as f:
                f.write(html)

            if (i + 1) % 50 == 0 or i == len(to_scrape) - 1:
                print(f"  [{i+1}/{len(to_scrape)}] lid_id={lid_id} ({len(html)} bytes)")

            time.sleep(args.delay)

        print(f"Download complete: {len(to_scrape)} new files")

    # Parse all HTML files
    print("\nParsing HTML files...")
    members = []
    for filepath in sorted(
        f for f in os.listdir(OUTPUT_DIR) if f.endswith(".html")
    ):
        with open(os.path.join(OUTPUT_DIR, filepath), "r", encoding="latin-1") as f:
            html = f.read()
        member = parse_member_html(html)
        if member:
            members.append(member)

    # Stats
    total_lid = sum(len(m["lidmaatschap"]) for m in members)
    total_ond = sum(len(m["onderdeel"]) for m in members)
    total_ins = sum(len(m["instrument"]) for m in members)
    print(f"Parsed {len(members)} members:")
    print(f"  {total_lid} lidmaatschap records")
    print(f"  {total_ond} onderdeel records")
    print(f"  {total_ins} instrument records")

    # Save JSON
    outfile = os.path.join(DATA_DIR, "members.json")
    with open(outfile, "w") as f:
        json.dump(members, f, indent=2, ensure_ascii=False)
    print(f"Saved to {outfile}")


if __name__ == "__main__":
    main()
