# CPR Expiry Tracker

A Laravel-based internal tool for tracking the expiry status of Certificate of Product Registration (CPR) PDF files. The system scans a local folder of CPR documents, extracts registration data via PDF parsing, and presents a searchable, paginated dashboard with expiry status indicators.

---

## Features

- **Folder Scanning** — Point the system at any local directory containing CPR PDF files. It parses each file and extracts the registration number, brand name, generic name, and expiry date.
- **Smart Caching** — Previously scanned files are loaded instantly from the database. Only new or modified files are re-parsed, reducing scan time on subsequent runs.
- **Expiry Status Tracking** — Each record is automatically classified as `Valid`, `Expiring Soon` (within 90 days), or `Expired` based on the parsed expiry date.
- **Inline Record Editing** — Edit any CPR record directly from the results table via a modal. No page navigation required.
- **Directory as Source of Truth** — Records are only displayed if the corresponding PDF file still exists on disk. Deleted files are automatically excluded from results without touching the database.
- **Parallel PDF Parsing** — Files are parsed concurrently using configurable worker processes, significantly reducing scan time on large folders.
- **Real-time Scan Progress** — A Server-Sent Events (SSE) endpoint streams file classification status during a scan.
- **Force Re-scan** — Option to wipe cached records and re-parse all files from scratch.
- **Pagination** — Configurable rows per page (10, 20, 30) with full page navigation.
- **Dark Mode** — Toggle between light and dark themes, persisted via localStorage.
- **Summary Dashboard** — At-a-glance counts for Valid, Expiring Soon, Expired, and Error records.

---

## Requirements

- PHP 8.2+
- Laravel 12
- MySQL (or any Laravel-supported database)
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
5. Click any row to open the PDF directly in the browser
6. Click **Edit** on any row to correct parsed data via the inline modal

---

## Scheduled Commands

The system includes an Artisan command that refreshes `days_remaining` and `status` for all records daily, preventing values from going stale between scans.

Register it in `app/Console/Kernel.php`:

```php
$schedule->command('cpr:refresh-status')->dailyAt('00:05');
```

Or in `routes/console.php` (Laravel 10+):

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

## Security

- Folder path input is validated against a list of forbidden system paths
- PDF file access is restricted via `realpath()` path traversal checks — only files within the scanned folder can be opened
- Edit access is scoped to the current session's folder path, preventing cross-session record enumeration

---

## License

Internal use only. Not licensed for public distribution.