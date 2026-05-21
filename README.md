# CPR Expiry Tracker

A Laravel-based internal tool for tracking the expiry status of Certificate of Product Registration (CPR) PDF files. The system scans a local folder of CPR documents, extracts registration data via PDF parsing, and presents a paginated dashboard with expiry status indicators and filtering.

---

## Features

- **Folder Scanning** — Point the system at any local directory containing CPR PDF files. It parses each file and extracts the registration number, brand name, generic name, and expiry date.
- **Smart Caching** — Previously scanned files are loaded instantly from the database. Only new or modified files are re-parsed, reducing scan time on subsequent runs.
- **Expiry Status Tracking** — Each record is automatically classified as `Valid`, `Expiring Soon` (within 90 days), `Expired`, or `Unknown` based on the parsed expiry date and completeness of the record. Records with a missing brand name are always marked `Unknown` regardless of expiry date.
- **Status Filtering** — Click any summary card (Valid, Expiring Soon, Expired, Unknown) to filter the results table to that status. Click a different card to switch filters. Filters reset on a new scan.
- **Filename-based Sorting** — Results are sorted numerically by the leading number in the filename (e.g. `1`, `2`, `10`, `11`) rather than lexicographically, matching the order files appear in the directory.
- **Inline Record Editing** — Edit any CPR record directly from the results table. No page navigation required.
- **Directory as Source of Truth** — Records are only displayed if the corresponding PDF file still exists on disk. Deleted files are automatically excluded from results without touching the database.
- **Parallel PDF Parsing** — Files are parsed concurrently using configurable worker processes, significantly reducing scan time on large folders.
- **Real-time Scan Progress** — A Server-Sent Events (SSE) endpoint streams file classification status during a scan, with an elapsed time stopwatch displayed in the loading overlay.
- **Force Re-scan** — Option to wipe cached records and re-parse all files from scratch.
- **Previously Scanned Files Modal** — When re-scanning a folder, a modal surfaces all files loaded from cache vs. freshly parsed, with options to retain existing records or trigger a full re-scan.
- **Pagination** — Configurable rows per page (10, 20, 30) with full page navigation.
- **Dark Mode** — Toggle between light and dark themes, persisted via localStorage.
- **Summary Dashboard** — At-a-glance counts for Valid, Expiring Soon, Expired, and Unknown records. Counts always reflect the full unfiltered dataset so all statuses remain visible while a filter is active.

---

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL 8.0+ (required for `REGEXP_SUBSTR` used in filename-based sorting)
- Composer
- A PDF parsing library configured in `App\Services\CprParser`

---

## Installation

```bash
git clone https://github.com/your-org/cpr-tracker.git
cd cpr-tracker
composer install
cp .env.example .env
php artisan key:generate
```

Configure your database in `.env`, then run migrations:

```bash
php artisan migrate
```

Start the development server:

```bash
php artisan serve
```

---

## Usage

1. Open the app in your browser at `http://127.0.0.1:8000`
2. Enter the full path to the folder containing your CPR PDF files (e.g. `E:\CPR Files`)
3. Click **Scan Folder**
4. Results are displayed in a paginated table with expiry status for each file
5. Click any summary card to filter results by status; click a different card to switch
6. Click any row to open the PDF directly in the browser
7. Click **Edit** on any row to correct parsed data

---

## Scheduled Commands

The system includes an Artisan command that refreshes `days_remaining` and `status` for all records daily, preventing values from going stale between scans.

Register it in `routes/console.php` (Laravel 10+):

```php
Schedule::command('cpr:refresh-status')->dailyAt('00:05');
```

---

## Configuration

| Setting | Location | Default |
|---|---|---|
| Parse concurrency (parallel workers) | `CprScanService::PARSE_CONCURRENCY` | `4` |
| Expiring Soon threshold | `CprRecord::resolveStatus()` `$warningDays` | `90` days |
| Max files per scan | `CprScanService::validateFolder()` | `500` |

---

## Project Structure

```
app/
├── Console/Commands/
│   └── RefreshCprStatus.php       # Daily status refresh command
├── Http/
│   ├── Controllers/
│   │   └── CprController.php      # Thin dispatcher — no business logic
│   └── Requests/
│       ├── CprScanRequest.php     # Scan form validation
│       └── CprUpdateRequest.php   # Edit modal validation
├── Models/
│   └── CprRecord.php              # Status calculation, normalized filename
└── Services/
    └── CprScanService.php         # All scan business logic
resources/views/cpr/
└── index.blade.php                # Main dashboard view
```

---

## Status Classification Rules

| Condition | Status |
|---|---|
| Brand name is missing | `Unknown` |
| No expiry date | `Unknown` |
| Expiry date has passed | `Expired` |
| Expiry date within 90 days | `Expiring Soon` |
| Expiry date beyond 90 days | `Valid` |

Brand name absence takes precedence over expiry date — a record with a valid expiry but no brand name is always `Unknown`.

---

## Security

- Folder path input is validated against a list of forbidden system paths
- PDF file access is restricted via `realpath()` path traversal checks — only files within the scanned folder can be opened
- Edit access is scoped to the current session's folder path, preventing cross-session record enumeration

---

## License

Internal use only. Not licensed for public distribution.