<?php

namespace App\Services;

use App\Models\CprRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CprScanService
 *
 * Owns the full scan lifecycle:
 *   1. Folder validation
 *   2. File classification (DB cache hit vs. needs parse)
 *   3. Parallel / sequential PDF parsing
 *   4. DB upsert
 *   5. Summary aggregation
 *   6. Paginated result retrieval
 *
 * The controller is a thin dispatcher — all branching lives here.
 */
class CprScanService
{
    /**
     * How many PDFs to parse in parallel via proc_open.
     * Set to 1 to force sequential parsing (useful for debugging).
     */
    private const PARSE_CONCURRENCY = 4;

    /**
     * Paths that must never be scanned regardless of what the user submits.
     */
    private const FORBIDDEN_PATHS = ['/', 'C:\\', 'C:/', '/tmp'];

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Validate that a folder path is safe and accessible.
     *
     * @return string|null  Error message, or null if the path is valid.
     */
    public function validateFolder(string $folderPath): ?string
    {
        if (!file_exists($folderPath))  return '❌ Folder not found. Please check the path and try again.';
        if (!is_dir($folderPath))       return '❌ The path points to a file, not a folder.';
        if (!is_readable($folderPath))  return '❌ Folder exists but cannot be read. Check permissions.';

        $normalized = rtrim($folderPath, '/\\');
        $forbidden  = array_map(fn($p) => rtrim($p, '/\\'), self::FORBIDDEN_PATHS);
        $forbidden[] = rtrim(sys_get_temp_dir(), '/\\');

        if (in_array($normalized, $forbidden, true)) {
            return '❌ Scanning this directory is not allowed.';
        }

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];

        if (empty($files))           return '⚠️ No PDF files found in this folder.';
        if (count($files) > 500)     return '⚠️ Too many files (' . count($files) . '). Maximum allowed is 500 PDFs per scan.';

        return null;
    }

    /**
     * Run a full scan of the folder.
     *
     * Classifies files into cache-hits (unchanged DB records) and files that
     * need (re-)parsing, then writes only the changed rows.
     *
     * Side-effects:
     *   - Writes session keys: scan_from_db, scan_from_pdf, scan_duplicates,
     *     summary_valid, summary_expiring, summary_expired, summary_errors
     *
     * @param  string $folderPath   Sanitised, validated folder path.
     * @param  bool   $forceRescan  When true, deletes existing DB records first.
     * @return array{int, int}      [$fromDb, $fromPdf]
     */
    public function runScan(string $folderPath, bool $forceRescan = false): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];

        if ($forceRescan) {
            CprRecord::whereIn('filename', collect($files)->map(fn($f) => basename($f))->toArray())->delete();
        }

        $filenames       = collect($files)->map(fn($f) => basename($f))->toArray();
        $existingRecords = CprRecord::whereIn('filename', $filenames)
            ->whereNotNull('registration_number')
            ->whereNotNull('expiry_date')
            ->get()
            ->keyBy('filename');

        [$rowsToUpsert, $filesToParse, $duplicates, $fromDb] = $this->classifyFiles(
            $files, $existingRecords, $folderPath
        );

        $parsedResults = $this->parseFiles($filesToParse);

        [$newRows, $fromPdf] = $this->buildUpsertRows(
            $filesToParse, $parsedResults, $existingRecords, $folderPath
        );

        $rowsToUpsert = array_merge($rowsToUpsert, $newRows);

        if (!empty($rowsToUpsert)) {
            $this->upsertChunked($rowsToUpsert);
        }

        $this->storeSessionSummary($folderPath, $fromDb, $fromPdf, $duplicates);

        return [$fromDb, $fromPdf];
    }

    /**
     * Fetch a paginated slice of records for the given folder.
     *
     * @return array{array, int, int}  [$records, $total, $lastPage]
     */
    public function paginateResults(string $folderPath, int $page, int $perPage, ?string $filterStatus = null, ?string $search = null): array
    {
        $diskFiles     = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        $diskFilenames = array_map('basename', $diskFiles);

        $query = CprRecord::where('folder_path', $folderPath)
            ->whereIn('filename', $diskFilenames);

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }
        if ($search) {
            $query->where('brand_name', 'LIKE', '%' . $search . '%');
        }

        $total    = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $records = (clone $query)
            ->orderByRaw("CAST(REGEXP_SUBSTR(filename, '^[0-9]+') AS UNSIGNED) ASC")
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        return [$records, $total, $lastPage];
    }

    /**
     * One aggregate query for all four summary counts.
     * Used by both scan results and the standalone results() page.
     *
     * @return array{valid: int, expiring: int, expired: int, errors: int}
     */
    public function summaryCounts(string $folderPath, ?string $search = null): array
    {
        $diskFilenames = array_map('basename', glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: []);

        $query = CprRecord::where('folder_path', $folderPath)
            ->whereIn('filename', $diskFilenames);

        if ($search) {
            $query->where('brand_name', 'LIKE', '%' . $search . '%');
        }

        $row = $query->selectRaw("
            SUM(status = 'Valid')                     as valid,
            SUM(status = 'Expiring Soon')             as expiring_soon,
            SUM(status = 'Expired')                   as expired,
            SUM(status IN ('Parse Error', 'Unknown')) as errors
        ")->first();

        return [
            'valid'    => (int) ($row->valid ?? 0),
            'expiring' => (int) ($row->expiring_soon ?? 0),
            'expired'  => (int) ($row->expired ?? 0),
            'errors'   => (int) ($row->errors ?? 0),
        ];
    }

    /**
     * Empty view-data bag for the initial (no-scan) page load.
     */
    public static function emptyViewData(): array
    {
        return [
            'results'             => [],
            'folder_path'         => null,
            'folderPath'          => null,
            'perPage'             => 10,
            'page'                => 1,
            'total'               => 0,
            'lastPage'            => 1,
            'fromDb'              => 0,
            'fromPdf'             => 0,
            'duplicates'          => [],
            'summaryValid'        => 0,
            'summaryExpiringSoon' => 0,
            'summaryExpired'      => 0,
            'summaryErrors'       => 0,
        ];
    }

    // ── Scan internals ───────────────────────────────────────────────────────

    /**
     * PASS 1 — Classify every file as a cache-hit or needing a parse.
     *
     * Cache-hits are files whose mtime is not newer than the DB record's
     * updated_at. These are skipped from parsing entirely, and only written
     * back to the DB if the folder_path has changed (i.e. the folder moved).
     *
     * @return array{array, array, array, int}
     *         [$rowsToUpsert, $filesToParse, $duplicates, $fromDb]
     */
    private function classifyFiles(array $files, $existingRecords, string $folderPath): array
    {
        $rowsToUpsert = [];
        $filesToParse = [];
        $duplicates   = [];
        $fromDb       = 0;

        foreach ($files as $file) {
            $filename = basename($file);

            if (!is_readable($file) || filesize($file) === 0) {
                Log::warning("Skipping unreadable or empty file: {$filename}");
                continue;
            }

            $existing = $existingRecords->get($filename);

            if ($existing) {
                $fileModified  = Carbon::createFromTimestamp(filemtime($file))->utc();
                $recordUpdated = Carbon::parse($existing->updated_at)->utc();

                if ($fileModified->lte($recordUpdated)) {
                    // File unchanged — only touch DB if folder_path moved.
                    if ($existing->folder_path !== $folderPath) {
                        $row               = $this->existingToRow($existing, $folderPath);
                        $row['updated_at'] = now();
                        $rowsToUpsert[]    = $row;
                    }
                    $duplicates[] = $this->existingToDuplicate($existing, $filename);
                    $fromDb++;
                    continue;
                }
            }

            $filesToParse[] = $file;
        }

        return [$rowsToUpsert, $filesToParse, $duplicates, $fromDb];
    }

    /**
     * PASS 2 — Convert raw parse results into upsert rows.
     *
     * Skips files where parsed values are identical to what's already stored
     * (avoids pointless writes when a file was modified but content didn't
     * actually change — e.g. a metadata-only save).
     *
     * @return array{array, int}  [$rows, $fromPdf]
     */
    private function buildUpsertRows(
        array $filesToParse,
        array $parsedResults,
        $existingRecords,
        string $folderPath
    ): array {
        $rows    = [];
        $fromPdf = 0;
        $now     = now();

        foreach ($filesToParse as $file) {
            $filename = basename($file);
            $parsed   = $parsedResults[$filename] ?? null;
            $existing = $existingRecords->get($filename);

            if ($parsed === null) {
                Log::warning("Skipping file — no parse result: {$filename}");
                continue;
            }

            // Skip write if content is identical and folder hasn't moved.
            if ($existing && $existing->folder_path === $folderPath) {
                $regMatch    = $existing->registration_number === $parsed['registration_number'];
                $expiryMatch = $parsed['expiry_date'] !== null
                    && Carbon::parse($existing->expiry_date)->toDateString()
                       === Carbon::parse($parsed['expiry_date'])->toDateString();

                if ($regMatch && $expiryMatch) {
                    continue;
                }
            }

            $rows[]  = $this->buildRow($file, $parsed, $folderPath, $now);
            $fromPdf++;
        }

        return [$rows, $fromPdf];
    }

    /**
     * Build a single upsert row from a parsed result.
     */
    private function buildRow(string $file, array $parsed, string $folderPath, $now): array
    {
        $filename  = basename($file);
        $computed = CprRecord::resolveStatus($parsed['expiry_date'] ?? null, 90, $parsed['brand_name'] ?? null);

        return [
            'filename'            => $filename,
            'folder_path'         => $folderPath,
            'normalized_filename' => CprRecord::buildNormalizedFilename(
                $parsed['generic_name'],
                $parsed['brand_name'],
                $parsed['expiry_date']
            ),
            'registration_number' => $parsed['registration_number'],
            'brand_name'          => $parsed['brand_name'],
            'generic_name'        => $parsed['generic_name'],
            'expiry_date'         => $parsed['expiry_date'],
            'days_remaining'      => $computed['days_remaining'],
            'status'              => $computed['status'],
            'updated_at'          => $now,
            'created_at'          => $now,
        ];
    }

    /**
     * Upsert rows in chunks inside a transaction.
     *
     * Chunked to stay under MySQL's max_allowed_packet.
     * Wrapped in a transaction so a mid-batch failure doesn't leave partial
     * writes in the DB.
     */
    private function upsertChunked(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('cpr_records')->upsert(
                    $chunk,
                    ['filename'],
                    [
                        'folder_path', 'normalized_filename', 'registration_number',
                        'brand_name', 'generic_name', 'expiry_date',
                        'days_remaining', 'status', 'updated_at',
                    ]
                );
            }
        });
    }

    /**
     * Aggregate summary counts and persist everything scan-related to session.
     */
    private function storeSessionSummary(
        string $folderPath,
        int $fromDb,
        int $fromPdf,
        array $duplicates
    ): void {
        $counts = CprRecord::where('folder_path', $folderPath)
            ->selectRaw("
                SUM(status = 'Valid')                     as valid,
                SUM(status = 'Expiring Soon')             as expiring_soon,
                SUM(status = 'Expired')                   as expired,
                SUM(status IN ('Parse Error', 'Unknown')) as errors
            ")
            ->first();

        session([
            'scan_from_db'     => $fromDb,
            'scan_from_pdf'    => $fromPdf,
            'scan_duplicates'  => $duplicates,
            'summary_valid'    => (int) ($counts->valid ?? 0),
            'summary_expiring' => (int) ($counts->expiring_soon ?? 0),
            'summary_expired'  => (int) ($counts->expired ?? 0),
            'summary_errors'   => (int) ($counts->errors ?? 0),
        ]);
    }

    // ── Parallel PDF parser ──────────────────────────────────────────────────
    //
    // Spawns up to PARSE_CONCURRENCY artisan workers, each parsing one file.
    // Falls back to sequential if proc_open is unavailable or concurrency = 1.
    //
    // Each worker executes:
    //   php artisan cpr:parse-file {filePath} {outputPath}
    // and writes JSON to a temp file. Results are collected once all workers
    // finish.
    //
    // To switch to synchronous parsing, set PARSE_CONCURRENCY = 1, or replace
    // the body of this method with the sequential fallback block below.
    //
    private function parseFiles(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        if (self::PARSE_CONCURRENCY <= 1 || !function_exists('proc_open')) {
            return $this->parseFilesSequential($files);
        }

        return $this->parseFilesParallel($files);
    }

    private function parseFilesSequential(array $files): array
    {
        $parser  = new \App\Services\CprParser();
        $results = [];

        foreach ($files as $file) {
            $results[basename($file)] = $parser->parse($file);
        }

        return $results;
    }

    private function parseFilesParallel(array $files): array
    {
        $phpBin  = PHP_BINARY;
        $artisan = base_path('artisan');
        $tempDir = sys_get_temp_dir();
        $results = [];
        $running = [];
        $queue   = $files;

        $launch = function () use (&$queue, &$running, $phpBin, $artisan, $tempDir) {
            if (empty($queue)) return;

            $file     = array_shift($queue);
            $filename = basename($file);
            $outFile  = $tempDir . DIRECTORY_SEPARATOR . 'cpr_parse_' . md5($file) . '.json';

            $cmd  = escapeshellcmd($phpBin) . ' '
                  . escapeshellarg($artisan)
                  . ' cpr:parse-file '
                  . escapeshellarg($file)
                  . ' '
                  . escapeshellarg($outFile);

            $proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

            if ($proc !== false) {
                $running[$filename] = ['proc' => $proc, 'output' => $outFile, 'pipes' => $pipes];
            }
        };

        for ($i = 0; $i < self::PARSE_CONCURRENCY; $i++) {
            $launch();
        }

        while (!empty($running)) {
            foreach ($running as $filename => $job) {
                if (proc_get_status($job['proc'])['running']) {
                    continue;
                }

                proc_close($job['proc']);

                foreach ([0, 1, 2] as $i) {
                    if (isset($job['pipes'][$i]) && is_resource($job['pipes'][$i])) {
                        fclose($job['pipes'][$i]);
                    }
                }

                if (file_exists($job['output'])) {
                    $json               = @file_get_contents($job['output']);
                    @unlink($job['output']);
                    $results[$filename] = $json ? json_decode($json, true) : null;
                } else {
                    $results[$filename] = null;
                    Log::warning("Parse worker produced no output for: {$filename}");
                }

                unset($running[$filename]);
                $launch();
            }

            if (!empty($running)) {
                usleep(20_000); // 20 ms poll — low CPU, minimal latency
            }
        }

        return $results;
    }

    // ── Row helpers ──────────────────────────────────────────────────────────

    private function existingToRow(CprRecord $existing, string $folderPath): array
    {
        return [
            'filename'            => $existing->filename,
            'folder_path'         => $folderPath,
            'normalized_filename' => $existing->normalized_filename,
            'registration_number' => $existing->registration_number,
            'brand_name'          => $existing->brand_name,
            'generic_name'        => $existing->generic_name,
            'expiry_date'         => $existing->expiry_date,
            'days_remaining'      => $existing->days_remaining,
            'status'              => $existing->status,
            'updated_at'          => $existing->updated_at,
            'created_at'          => $existing->created_at,
        ];
    }

    private function existingToDuplicate(CprRecord $existing, string $filename): array
    {
        return [
            'filename'            => $filename,
            'normalized_filename' => $existing->normalized_filename,
            'registration_number' => $existing->registration_number,
            'brand_name'          => $existing->brand_name,
            'generic_name'        => $existing->generic_name,
            'expiry_date'         => $existing->expiry_date,
            'status'              => $existing->status,
        ];
    }

}