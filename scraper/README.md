# Incidents Mini (Nigeria Incident Discovery Scraper)

Tiny PHP MVP that scrapes configured Nigerian news listing pages, extracts article content, stores raw NDJSON items, and flags likely incident candidates.

## Requirements

- PHP 8+
- cURL extension enabled
- DOM extension enabled

## Folder Layout

- `config` - app, source, keyword and place configuration
- `src` - modular scraper classes
- `root (index.php, run.php, assets/)` - dashboard and run route
- `data` - append-only NDJSON/text files

## Run Locally

1. Point your web server document root to `root (index.php, run.php, assets/)`.
2. Open `/index.php` in browser.
3. Click **Run scraper now**.
4. Dashboard refreshes with latest counts, tables and run logs.

If using XAMPP under this workspace, an example URL is:

- `http://localhost/safer/scraper/root (index.php, run.php, assets/)/index.php`

## Add/Edit Sources

Edit `config/sources.php`.

Each source supports:

- `name`
- `domain`
- `enabled`
- `list_urls` (direct listing/archive/category URLs)
- `allowed_path_hints`
- `blocked_path_hints`

No database is used for source management.

## Duplicate Detection

Two layers are used:

1. Exact duplicate:
- `sha1(lowercase(trim(article_url . '|' . title)))`
- checked against `data/seen_hashes.txt` and `data/seen_urls.txt`

2. Near duplicate:
- normalize text (`lowercase`, punctuation removed, whitespace collapsed)
- compare reduced text fingerprint against recent fingerprints using `similar_text`
- if similarity exceeds `near_duplicate_similarity_threshold`, item is skipped

## Candidate Scoring Rules

`src/CandidateDetector.php` uses rule-based scoring only:

- incident keyword match in title
- incident keyword match in snippet/content
- Nigeria place match in title/content
- recency hints (`today`, `yesterday`, etc.)
- recent published date bonus
- strong source bonus

If score >= `candidate_threshold`, candidate is appended to `data/candidates.txt`.

## Storage Format (NDJSON)

Append-only files in `data`:

- `raw_items.txt` - one raw item JSON object per line
- `candidates.txt` - one candidate JSON object per line
- `seen_hashes.txt` - one exact hash per line
- `seen_urls.txt` - one URL per line
- `run_log.txt` - one log line per line

Files are lock-written and auto-created if missing.


