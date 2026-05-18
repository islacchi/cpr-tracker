<?php

namespace App\Http\Controllers;

use App\Models\CprRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CprController extends Controller
{
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

        // On pagination always read folder_path from session — never trust
        // the hidden form input (prevents folder_path tampering).
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

        $isFreshScan  = !$isPagination;
        $fromDb       = 0;
        $fromPdf      = 0;
        $duplicates   = [];

        if ($isFreshScan) {
            $parser = new \App\Services\CprParser();

            $filenames       = collect($files)->map(fn($f) => basename($f))->toArray();
            $existingRecords = CprRecord::whereIn('filename', $filenames)
                ->whereNotNull('registration_number')
                ->whereNotNull('expiry_date')
                ->get()
                ->keyBy('filename');

            // Collect every row that needs writing into one array, then
            // write them all in a single upsert call inside one transaction.
            // Previously: N files = N×2 individual DB round trips.
            // Now: always exactly 1 DB write regardless of file count.
            $rowsToUpsert = [];

            DB::transaction(function () use (
                $files, $parser, $folderPath, $existingRecords,
                &$fromDb, &$fromPdf, &$duplicates, &$rowsToUpsert
            ) {
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
                    $parsed   = null;

                    if ($existing) {
                        // Force both timestamps to UTC before comparing so
                        // timezone mismatches don't cause false cache misses.
                        $fileModified  = Carbon::createFromTimestamp(filemtime($file))->utc();
                        $recordUpdated = Carbon::parse($existing->updated_at)->utc();

                        if ($fileModified->lte($recordUpdated)) {
                            // File unchanged — queue a folder_path refresh in
                            // the batch instead of a standalone UPDATE per file.
                            $rowsToUpsert[] = $this->existingToRow($existing, $folderPath);

                            $duplicates[] = $this->existingToDuplicate($existing, $filename);
                            $fromDb++;
                            continue;
                        }

                        $parsed      = $parser->parse($file);
                        $valuesMatch =
                            $existing->registration_number === $parsed['registration_number'] &&
                            Carbon::parse($existing->expiry_date)->toDateString() === Carbon::parse($parsed['expiry_date'])->toDateString();

                        if ($valuesMatch) {
                            $rowsToUpsert[] = $this->existingToRow($existing, $folderPath);
                            $duplicates[]   = $this->existingToDuplicate($existing, $filename);
                            $fromDb++;
                            continue;
                        }

                    } else {
                        $parsed = $parser->parse($file);
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
                        $status        = match(true) {
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

                    // Extract the leading number from the filename once at
                    // parse time and store it as sort_order. ORDER BY on this
                    // plain integer column uses the index directly — replaces
                    // the per-query REGEXP_SUBSTR that couldn't use any index.
                    preg_match('/^(\d+)/', $filename, $matches);
                    $sortOrder = isset($matches[1]) ? (int) $matches[1] : 0;

                    $now = now();

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

                // Single upsert for ALL rows.
                if (!empty($rowsToUpsert)) {
                    DB::table('cpr_records')->upsert(
                        $rowsToUpsert,
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
            });

            // Compute summary counts once after the fresh scan and cache them.
            // Pagination requests will read from session — no re-query per page.
            $summaryCounts = CprRecord::where('folder_path', $folderPath)
                ->selectRaw("
                    SUM(status = 'Valid') as valid,
                    SUM(status = 'Expiring Soon') as expiring_soon,
                    SUM(status = 'Expired') as expired,
                    SUM(status IN ('Parse Error', 'Unknown')) as errors
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

        // ORDER BY sort_order hits the index — replaces the old REGEXP_SUBSTR
        // expression that forced a full-table scan + sort on every page load.
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

    // ── Private helpers to de-duplicate the "existing record → row/duplicate"
    //   mapping that was repeated verbatim four times in the original loop.
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
            'updated_at'          => $existing->updated_at, // preserve — file didn't change
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

        header('X-Accel-Buffering: no');

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

            // usleep removed — 300ms × 500 files was 150 seconds of mandatory
            // waiting before the actual scan could write anything to the DB.
        }

        $sendEvent(['msg' => '✅ Scan complete!', 'done' => true]);
    }
}