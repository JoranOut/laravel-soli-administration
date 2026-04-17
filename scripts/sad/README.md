# SAD Member Scraper

Scrapes member history from the legacy SAD administration system (`sad.soli.nl`) and outputs a `members.json` file for use with the `import:sad-members` artisan command.

## Prerequisites

- Python 3.6+
- Active credentials for `sad.soli.nl`

## Usage

Set credentials via environment variables:

```bash
export SAD_USERNAME="your_username"
export SAD_PASSWORD="your_password"
```

Scrape all members (downloads HTML files, then parses them):

```bash
python3 scrape.py
```

Scrape with a limit (useful for testing):

```bash
python3 scrape.py --limit 20
```

Parse already-downloaded HTML files without re-scraping:

```bash
python3 scrape.py --parse-only
```

Adjust delay between requests (default: 1 second):

```bash
python3 scrape.py --delay 2
```

## Output

- `html/` — Cached HTML files per member (one per `lid_id`). These are ephemeral and not committed to the repo.
- `members.json` — Parsed member data. This file contains personal data and should not be committed to the repo.

## Importing

After generating `members.json`, import into the Laravel application:

```bash
sail artisan import:sad-members scripts/sad/members.json
```

Use `--fresh` to clear existing data first, or `--dry-run` to validate without persisting.

## Reference

See `table_info.txt` for the SAD database schema documentation.
