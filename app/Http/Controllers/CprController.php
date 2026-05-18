<?php

namespace App\Http\Controllers;

use App\Models\CprRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CprController extends Controller
{
    // ── How many files to parse in parallel via proc_open.
    //    Tune this to your CPU core count. 4 is safe on most servers.
    private const PARSE_CONCURRENCY = 4;

    public function index()
    {
        return view('cpr.index', [
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
        ]);
    }

    public function scan(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        $isPagination = $request->has('page') || $request->has('per_page');
        $folderPath   = $isPagination
            ? session('last_folder_path')
            : $request->input('folder_path');

        if (!$isPagination) {
            $request->validate(['folder_path' => 'required|string|max:500']);
        }

        $folderPath = rtrim(trim((string) $folderPath), DIRECTORY_SEPARATOR);

        if (empty($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => 'Please enter a folder path before scanning.'])
                ->withInput();
        }
        if (!file_exists($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Folder not found. Please check the path and try again.'])
                ->withInput();
        }
        if (!is_dir($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ The path points to a file, not a folder.'])
                ->withInput();
        }
        if (!is_readable($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Folder exists but cannot be read. Check permissions.'])
                ->withInput();
        }

        $dangerousPaths = ['/', 'C:\\', 'C:/', sys_get_temp_dir()];
        if (in_array(rtrim($folderPath, '/\\'), array_map(fn($p) => rtrim($p, '/\\'), $dangerousPaths), true)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Scanning this directory is not allowed.'])
                ->withInput();
        }

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];

        if (empty($files)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '⚠️ No PDF files found in this folder.'])
                ->withInput();
        }
        if (count($files) > 500) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '⚠️ Too many files (' . count($files) . '). Maximum allowed is 500 PDFs per scan.'])
                ->withInput();
        }

        session(['last_folder_path' => $folderPath]);

        if ($request->input('force_rescan')) {
            CprRecord::whereIn('filename', collect($files)->map(fn($f) => basename($f))->toArray())
                ->delete();
            session()->flash('rescan_success', 'All records have been wiped and re-parsed successfully.');
        }

        $isFreshScan = !$isPagination;
        $fromDb      = 0;
        $fromPdf     = 0;
        $duplicates  = [];

        if ($isFreshScan) {
            $filenames       = collect($files)->map(fn($f) => basename($f))->toArray();
            $existingRecords = CprRecord::whereIn('filename', $filenames)
                ->whereNotNull('registration_number')
                ->whereNotNull('expiry_date')
                ->get()
                ->keyBy('filename');

            // ── PASS 1: classify files into cache-hits vs needs-parse ──────────
            // Cache hits are skipped from the upsert entirely when folder_path
            // hasn't moved — eliminates the N-row no-op write the old code did.
            $rowsToUpsert = [];
            $filesToParse = [];

            foreach ($files as $file) {
                $filename = basename($file);

                if (!is_readable($file)) {
                    Log::warning('Skipping unreadable file: ' . $filename);
                    continue;
                }
                if (filesize($file) === 0) {
                    Log::warning('Skipping empty file: ' . $filename);
                    continue;
                }

                $existing = $existingRecords->get($filename);

                if ($existing) {
                    $fileModified  = Carbon::createFromTimestamp(filemtime($file))->utc();
                    $recordUpdated = Carbon::parse($existing->updated_at)->utc();

                    if ($fileModified->lte($recordUpdated)) {
                        // File unchanged. Only touch DB if the folder moved.
                        if ($existing->folder_path !== $folderPath) {
                            $row                = $this->existingToRow($existing, $folderPath);
                            $row['updated_at']  = now();
                            $rowsToUpsert[]     = $row;
                        }
                        $duplicates[] = $this->existingToDuplicate($existing, $filename);
                        $fromDb++;
                        continue;
                    }
                }

                $filesToParse[] = $file;
            }

            // ── PASS 2: parse only files that actually need it ─────────────────
            // Parallel when PARSE_CONCURRENCY > 1 and proc_open is available.
            $parsedResults = $this->parseFiles($filesToParse);

            $now = now();
            foreach ($filesToParse as $file) {
                $filename = basename($file);
                $parsed   = $parsedResults[$filename] ?? null;
                $existing = $existingRecords->get($filename);

                // File was re-read but values are identical — skip the write.
                if ($existing && $parsed !== null) {
                    $valuesMatch =
                        $existing->registration_number === $parsed['registration_number'] &&
                        Carbon::parse($existing->expiry_date)->toDateString()
                            === Carbon::parse($parsed['expiry_date'])->toDateString();

                    if ($valuesMatch && $existing->folder_path === $folderPath) {
                        $duplicates[] = $this->existingToDuplicate($existing, $filename);
                        $fromDb++;
                        continue;
                    }
                }

                if ($parsed === null) {
                    Log::warning('Skipping file — no parse result: ' . $filename);
                    continue;
                }

                $daysRemaining = null;
                $status        = $parsed['status'];

                if ($parsed['expiry_date']) {
                    $expiry        = Carbon::parse($parsed['expiry_date']);
                    $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);
                    $status        = match (true) {
                        $daysRemaining < 0   => 'Expired',
                        $daysRemaining <= 90 => 'Expiring Soon',
                        default              => 'Valid',
                    };
                }

                $normalizedFilename = $this->normalizeFilename(
                    $parsed['generic_name'],
                    $parsed['brand_name'],
                    $parsed['expiry_date']
                );

                preg_match('/^(\d+)/', $filename, $matches);
                $sortOrder = isset($matches[1]) ? (int) $matches[1] : 0;

                $rowsToUpsert[] = [
                    'filename'            => $filename,
                    'folder_path'         => $folderPath,
                    'normalized_filename' => $normalizedFilename,
                    'registration_number' => $parsed['registration_number'],
                    'brand_name'          => $parsed['brand_name'],
                    'generic_name'        => $parsed['generic_name'],
                    'expiry_date'         => $parsed['expiry_date'],
                    'days_remaining'      => $daysRemaining,
                    'status'              => $status,
                    'sort_order'          => $sortOrder,
                    'updated_at'          => $now,
                    'created_at'          => $now,
                ];

                $fromPdf++;
            }

            // ── Single write, no transaction needed for upsert on a unique key.
            // Chunked to stay under MySQL's max_allowed_packet on large folders.
            if (!empty($rowsToUpsert)) {
                foreach (array_chunk($rowsToUpsert, 100) as $chunk) {
                    DB::table('cpr_records')->upsert(
                        $chunk,
                        ['filename'],
                        [
                            'folder_path',
                            'normalized_filename',
                            'registration_number',
                            'brand_name',
                            'generic_name',
                            'expiry_date',
                            'days_remaining',
                            'status',
                            'sort_order',
                            'updated_at',
                        ]
                    );
                }
            }

            // Summary counts — one aggregate query, cached in session.
            $summaryCounts = CprRecord::where('folder_path', $folderPath)
                ->selectRaw("
                    SUM(status = 'Valid')                        as valid,
                    SUM(status = 'Expiring Soon')                as expiring_soon,
                    SUM(status = 'Expired')                      as expired,
                    SUM(status IN ('Parse Error', 'Unknown'))    as errors
                ")
                ->first();

            session([
                'scan_from_db'     => $fromDb,
                'scan_from_pdf'    => $fromPdf,
                'summary_valid'    => (int) ($summaryCounts->valid ?? 0),
                'summary_expiring' => (int) ($summaryCounts->expiring_soon ?? 0),
                'summary_expired'  => (int) ($summaryCounts->expired ?? 0),
                'summary_errors'   => (int) ($summaryCounts->errors ?? 0),
            ]);

        } else {
            $fromDb  = session('scan_from_db', 0);
            $fromPdf = session('scan_from_pdf', 0);
        }

        $total    = CprRecord::where('folder_path', $folderPath)->count();
        $lastPage = (int) ceil($total / $perPage);

        $records = CprRecord::where('folder_path', $folderPath)
            ->orderBy('sort_order', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();

        return view('cpr.index', [
            'results'             => $records,
            'folderPath'          => $folderPath,
            'perPage'             => $perPage,
            'page'                => $page,
            'total'               => $total,
            'lastPage'            => $lastPage,
            'fromDb'              => $fromDb,
            'fromPdf'             => $fromPdf,
            'duplicates'          => $duplicates,
            'summaryValid'        => session('summary_valid', 0),
            'summaryExpiringSoon' => session('summary_expiring', 0),
            'summaryExpired'      => session('summary_expired', 0),
            'summaryErrors'       => session('summary_errors', 0),
        ]);
    }

    // ── Parallel PDF parser ──────────────────────────────────────────────────
    //
    // Spawns up to PARSE_CONCURRENCY concurrent artisan workers, each parsing
    // one file at a time. Falls back to sequential if proc_open is unavailable
    // (e.g. disabled in php.ini) or PARSE_CONCURRENCY is 1.
    //
    // Each worker runs:
    //   php artisan cpr:parse-file {filePath} {outputPath}
    // and writes JSON to a temp file. The controller collects results once all
    // workers finish.
    //
    // If you don't want the artisan command approach, replace the body of this
    // method with a simple foreach that calls $parser->parse($file) directly —
    // everything else in the controller stays the same.
    //
    private function parseFiles(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        // Sequential fallback — use this if the artisan command isn't set up yet.
        if (self::PARSE_CONCURRENCY <= 1 || !function_exists('proc_open')) {
            $parser  = new \App\Services\CprParser();
            $results = [];
            foreach ($files as $file) {
                $results[basename($file)] = $parser->parse($file);
            }
            return $results;
        }

        // Parallel path ────────────────────────────────────────────────────
        $phpBin    = PHP_BINARY;
        $artisan   = base_path('artisan');
        $tempDir   = sys_get_temp_dir();
        $results   = [];
        $running   = [];   // [filename => ['proc', 'outputFile', 'pipes']]

        $queue = $files;   // mutable copy we pop from

        $launch = function () use (&$queue, &$running, $phpBin, $artisan, $tempDir) {
            if (empty($queue)) return;
            $file     = array_shift($queue);
            $filename = basename($file);
            $outFile  = $tempDir . DIRECTORY_SEPARATOR . 'cpr_parse_' . md5($file) . '.json';

            $cmd   = escapeshellcmd($phpBin) . ' '
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

        // Seed the pool.
        for ($i = 0; $i < self::PARSE_CONCURRENCY; $i++) {
            $launch();
        }

        // Drain: whenever a worker finishes, collect its result and launch the next.
        while (!empty($running)) {
            foreach ($running as $filename => $job) {
                $status = proc_get_status($job['proc']);
                if ($status['running']) {
                    continue;
                }

                // Worker finished.
                proc_close($job['proc']);
                foreach ([0, 1, 2] as $i) {
                    if (isset($job['pipes'][$i]) && is_resource($job['pipes'][$i])) {
                        fclose($job['pipes'][$i]);
                    }
                }

                if (file_exists($job['output'])) {
                    $json = @file_get_contents($job['output']);
                    @unlink($job['output']);
                    $decoded = $json ? json_decode($json, true) : null;
                    $results[$filename] = $decoded ?: null;
                } else {
                    $results[$filename] = null;
                    Log::warning("Parse worker produced no output for: {$filename}");
                }

                unset($running[$filename]);
                $launch(); // Start next file immediately.
            }
            if (!empty($running)) {
                usleep(20_000); // 20 ms poll interval — low CPU spin, minimal latency.
            }
        }

        return $results;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

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
            'sort_order'          => $existing->sort_order ?? 0,
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

    private function normalizeFilename(?string $genericName, ?string $brandName, ?string $expiryDate): string
    {
        $generic = $genericName ? strtolower(trim($genericName)) : 'unknown';
        $brand   = $brandName   ? strtoupper(trim($brandName))   : 'UNKNOWN';
        $expiry  = $expiryDate
            ? Carbon::parse($expiryDate)->format('M Y')
            : 'No Expiry';

        return ucwords($generic) . " - {$brand} - {$expiry}";
    }

    // ── openPdf ──────────────────────────────────────────────────────────────

    public function openPdf(Request $request)
    {
        $folderPath = $request->input('folder_path');
        $filename   = basename($request->input('filename', ''));

        if (empty($filename) || empty($folderPath)) {
            abort(400, 'Missing parameters.');
        }

        $folderReal = realpath($folderPath);
        $filePath   = realpath($folderPath . DIRECTORY_SEPARATOR . $filename);

        if (
            !$folderReal ||
            !$filePath   ||
            !str_starts_with($filePath, $folderReal . DIRECTORY_SEPARATOR)
        ) {
            abort(403, 'Access denied.');
        }

        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'pdf') {
            abort(403, 'Only PDF files can be opened.');
        }

        if (!file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        return response()->file($filePath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    // ── SSE Progress ─────────────────────────────────────────────────────────
    //
    // NOTE: This endpoint streams file *classification* status (DB hit vs parse
    // needed) — it does not track the actual parse progress of individual files.
    // To get true per-file progress you'd need shared state (Redis/cache) that
    // the parse workers write to as they finish, and this endpoint reads from.
    // That's a larger change; this keeps the existing SSE contract intact.
    //
    public function progress(Request $request)
    {
        $sessionPath = session('last_folder_path');
        $folderPath  = $request->input('folder_path');

        $sendEvent = function (array $payload) {
            echo "data: " . json_encode($payload) . "\n\n";
            flush();
        };

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        if (!$folderPath || !$sessionPath || $folderPath !== $sessionPath) {
            $sendEvent(['msg' => 'Invalid folder path.', 'done' => true]);
            return;
        }

        if (!is_dir($folderPath) || !is_readable($folderPath)) {
            $sendEvent(['msg' => 'Folder not accessible.', 'done' => true]);
            return;
        }

        $dangerousPaths = ['/', 'C:\\', 'C:/', sys_get_temp_dir()];
        if (in_array(rtrim($folderPath, '/\\'), array_map(fn($p) => rtrim($p, '/\\'), $dangerousPaths), true)) {
            $sendEvent(['msg' => 'Forbidden path.', 'done' => true]);
            return;
        }

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        $total = count($files);

        if ($total === 0) {
            $sendEvent(['msg' => 'No files found.', 'done' => true]);
            return;
        }

        $filenames       = collect($files)->map(fn($f) => basename($f))->toArray();
        $existingRecords = CprRecord::whereIn('filename', $filenames)
            ->whereNotNull('registration_number')
            ->whereNotNull('expiry_date')
            ->pluck('filename')
            ->flip();

        foreach ($files as $index => $file) {
            $filename = basename($file);
            $msg      = isset($existingRecords[$filename])
                ? "📂 Loading from DB: {$filename}"
                : "📄 Parsing: {$filename}";

            $sendEvent([
                'msg'     => $msg,
                'current' => $index + 1,
                'total'   => $total,
                'done'    => false,
            ]);
        }

        $sendEvent(['msg' => '✅ Scan complete!', 'done' => true]);
    }
}