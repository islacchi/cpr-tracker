<?php

namespace App\Http\Controllers;

use App\Models\CprRecord;
use Illuminate\Http\Request;

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
        set_time_limit(300);

        $folderPath = $request->input('folder_path');
        $perPage    = (int) $request->input('per_page', 10);
        $page       = (int) $request->input('page', 1);

        if ($request->input('page') || $request->input('per_page')) {
            $folderPath = $folderPath ?? session('last_folder_path');
        }

        // Guard 1: Empty path
        if (empty(trim((string) $folderPath))) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => 'Please enter a folder path before scanning.'])
                ->withInput();
        }

        $request->validate(['folder_path' => 'required|string']);

        $folderPath = rtrim(trim($folderPath), DIRECTORY_SEPARATOR);

        // Guard 2: Path doesn't exist
        if (!file_exists($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Folder not found. Please check the path and try again.'])
                ->withInput();
        }

        // Guard 3: Path is a file not a folder
        if (!is_dir($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ The path points to a file, not a folder.'])
                ->withInput();
        }

        // Guard 4: Folder not readable
        if (!is_readable($folderPath)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Folder exists but cannot be read. Check permissions.'])
                ->withInput();
        }

        // Guard 5: Dangerous system paths
        $dangerousPaths = ['/', 'C:\\', 'C:/', sys_get_temp_dir()];
        if (in_array(rtrim($folderPath, '/\\'), array_map(fn($p) => rtrim($p, '/\\'), $dangerousPaths), true)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Scanning this directory is not allowed.'])
                ->withInput();
        }

        // Define $files so Guards 6 & 7 can use it
        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];

        // Guard 6: No PDFs found
        if (empty($files)) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '⚠️ No PDF files found in this folder.'])
                ->withInput();
        }

        // Guard 7: Too many files
        if (count($files) > 500) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '⚠️ Too many files (' . count($files) . '). Maximum allowed is 500 PDFs per scan.'])
                ->withInput();
        }

        // Guard 8: Path too long
        if (strlen($folderPath) > 500) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => '❌ Folder path is too long.'])
                ->withInput();
        }

        // Store folder path in session
        session(['last_folder_path' => $folderPath]);

        // ── Force rescan ──────────────────────────────────────────
        if ($request->input('force_rescan')) {
            \App\Models\CprRecord::whereIn('filename', collect($files)->map(fn($f) => basename($f))->toArray())
                ->delete();
            session()->flash('rescan_success', 'All records have been wiped and re-parsed successfully.'); // ← add this
        }

        // ── Always initialize before if/else so view always gets them ──
        $isFreshScan = !$request->has('page') && !$request->has('per_page');
        $fromDb      = 0;
        $fromPdf     = 0;
        $duplicates  = [];

        if ($isFreshScan) {
            $parser = new \App\Services\CprParser();

            foreach ($files as $file) {
                $filename = basename($file);

                // Guard 9: Skip non-readable files
                if (!is_readable($file)) {
                    \Log::warning('Skipping unreadable file: ' . $filename);
                    continue;
                }

                // Guard 10: Skip empty files
                if (filesize($file) === 0) {
                    \Log::warning('Skipping empty file: ' . $filename);
                    continue;
                }

                $existing = \App\Models\CprRecord::where('filename', $filename)
                    ->whereNotNull('registration_number')
                    ->whereNotNull('expiry_date')
                    ->first();
                
                if ($existing) {
                    $parsed = $parser->parse($file);
                    $valuesMatch =
    $existing->registration_number === $parsed['registration_number'] &&
    \Carbon\Carbon::parse($existing->expiry_date)->toDateString() === \Carbon\Carbon::parse($parsed['expiry_date'])->toDateString();

                    if ($valuesMatch) {
                        // Direct query update guarantees folder_path is saved correctly
                        \App\Models\CprRecord::where('filename', $filename)
                            ->update(['folder_path' => $folderPath]);

                        $duplicates[] = [
                            'filename'            => $filename,
                            'normalized_filename' => $existing->normalized_filename,
                            'registration_number' => $existing->registration_number,
                            'brand_name'          => $existing->brand_name,
                            'generic_name'        => $existing->generic_name,
                            'expiry_date'         => $existing->expiry_date,
                            'status'              => $existing->status,
                        ];

                        $fromDb++;
                        continue;
                    }
                } else {
                    $parsed = $parser->parse($file);
                }

                $daysRemaining = null;
                $status        = $parsed['status'];

                if ($parsed['expiry_date']) {
                    $expiry        = \Carbon\Carbon::parse($parsed['expiry_date']);
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

                \App\Models\CprRecord::updateOrCreate(
                    ['filename' => $filename],
                    [
                        'folder_path'         => $folderPath,
                        'normalized_filename' => $normalizedFilename,
                        'registration_number' => $parsed['registration_number'],
                        'brand_name'          => $parsed['brand_name'],
                        'generic_name'        => $parsed['generic_name'],
                        'expiry_date'         => $parsed['expiry_date'],
                        'days_remaining'      => $daysRemaining,
                        'status'              => $status,
                    ]
                );

                $fromPdf++;
            }

            // ── Store in session so pagination doesn't lose them ──
            session([
                'scan_from_db'    => $fromDb,
                'scan_from_pdf'   => $fromPdf,
                'scan_duplicates' => $duplicates,
            ]);

        } else {
            // ── Restore from session on pagination / per-page change ──
            $fromDb     = session('scan_from_db', 0);
            $fromPdf    = session('scan_from_pdf', 0);
            $duplicates = session('scan_duplicates', []);
        }

        $total    = \App\Models\CprRecord::where('folder_path', $folderPath)->count();
        $lastPage = (int) ceil($total / $perPage);
        $records  = \App\Models\CprRecord::where('folder_path', $folderPath)
            ->orderByRaw('CAST(REGEXP_SUBSTR(filename, "^[0-9]+") AS UNSIGNED) ASC')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->toArray();
                
        // ── Total summary counts across ALL records, not just current page ──
        $summaryValid        = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Valid')->count();
        $summaryExpiringSoon = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Expiring Soon')->count();
        $summaryExpired      = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Expired')->count();
        $summaryErrors       = \App\Models\CprRecord::where('folder_path', $folderPath)->whereIn('status', ['Parse Error', 'Unknown'])->count();
        return view('cpr.index', [
            'results'    => $records,
            'folderPath' => $folderPath,
            'perPage'    => $perPage,
            'page'       => $page,
            'total'      => $total,
            'lastPage'   => $lastPage,
            'fromDb'     => $fromDb,
            'fromPdf'    => $fromPdf,
            'duplicates' => $duplicates,
            'summaryValid'       => $summaryValid,        // ← add
            'summaryExpiringSoon'=> $summaryExpiringSoon,  // ← add
            'summaryExpired'     => $summaryExpired,       // ← add
            'summaryErrors'      => $summaryErrors,        // ← add
        ]);
    }

    private function normalizeFilename(?string $genericName, ?string $brandName, ?string $expiryDate): string
    {
        $generic = $genericName ? strtolower(trim($genericName)) : 'unknown';
        $brand   = $brandName   ? strtoupper(trim($brandName))   : 'UNKNOWN';
        $expiry  = $expiryDate
            ? \Carbon\Carbon::parse($expiryDate)->format('M Y')
            : 'No Expiry';

        $generic = ucwords($generic);

        return "{$generic} - {$brand} - {$expiry}";
    }

    public function openPdf(Request $request)
    {
        $folderPath = $request->input('folder_path');
        $filename   = $request->input('filename');
        $filePath   = $folderPath . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        return response(file_get_contents($filePath), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache');
    }
public function edit($id)
{
    $cpr = \App\Models\CprRecord::findOrFail($id);
    return view('cpr.edit', compact('cpr'));
}

public function update(Request $request, $id)
{
    $cpr = \App\Models\CprRecord::findOrFail($id);

    $request->validate([
        'registration_number' => 'nullable|string',
        'brand_name'          => 'nullable|string',
        'generic_name'        => 'nullable|string',
        'expiry_date'         => 'nullable|date',
    ]);

    $expiryDate    = $request->input('expiry_date');
    $daysRemaining = null;
    $status        = 'Unknown';

    if ($expiryDate) {
        $expiry        = \Carbon\Carbon::parse($expiryDate);
        $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);
        $status        = match(true) {
            $daysRemaining < 0    => 'Expired',
            $daysRemaining <= 90  => 'Expiring Soon',
            default               => 'Valid',
        };
    }

    $normalizedFilename = $this->normalizeFilename(
        $request->input('generic_name'),
        $request->input('brand_name'),
        $expiryDate
    );

    $cpr->update([
        'registration_number' => $request->input('registration_number'),
        'brand_name'          => $request->input('brand_name'),
        'generic_name'        => $request->input('generic_name'),
        'expiry_date'         => $expiryDate,
        'days_remaining'      => $daysRemaining,
        'status'              => $status,
        'normalized_filename' => $normalizedFilename,
    ]);

    // Store success message
session(['success' => '✅ CPR record updated successfully!']);
    return redirect()->route('cpr.results');
}

public function progress(Request $request)
    {
        $folderPath = $request->input('folder_path') ?? session('last_folder_path');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        $total = count($files);

        if ($total === 0) {
            echo "data: " . json_encode(['percent' => 100, 'msg' => 'No files found.', 'done' => true]) . "\n\n";
            flush();
            return;
        }

        $parser = new \App\Services\CprParser();

        foreach ($files as $index => $file) {
            $filename = basename($file);
            $percent  = (int) round((($index + 1) / $total) * 100);

            $existing = \App\Models\CprRecord::where('filename', $filename)
                ->whereNotNull('registration_number')
                ->whereNotNull('expiry_date')
                ->first();

            if ($existing) {
                $msg = "Loading from DB: {$filename}";
            } else {
                $msg = "Parsing: {$filename}";
            }

            echo "data: " . json_encode([
                'percent'  => $percent,
                'msg'      => $msg,
                'current'  => $index + 1,
                'total'    => $total,
                'done'     => false,
            ]) . "\n\n";

            flush();
            usleep(300000); // small delay so frontend can render each step
        }

        echo "data: " . json_encode([
            'percent' => 100,
            'msg'     => 'Scan complete!',
            'done'    => true,
        ]) . "\n\n";
        flush();
    }
    public function results(Request $request)
{
    $folderPath = session('last_folder_path');

    if (!$folderPath) {
        return redirect()->route('cpr.index');
    }

    $perPage  = (int) $request->input('per_page', 10);
    $page     = (int) $request->input('page', 1);
    $total    = \App\Models\CprRecord::where('folder_path', $folderPath)->count();
    $lastPage = (int) ceil($total / $perPage);
    $records  = \App\Models\CprRecord::where('folder_path', $folderPath)
        ->orderByRaw('CAST(REGEXP_SUBSTR(filename, "^[0-9]+") AS UNSIGNED) ASC')
        ->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get()
        ->toArray();

    $summaryValid        = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Valid')->count();
    $summaryExpiringSoon = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Expiring Soon')->count();
    $summaryExpired      = \App\Models\CprRecord::where('folder_path', $folderPath)->where('status', 'Expired')->count();
    $summaryErrors       = \App\Models\CprRecord::where('folder_path', $folderPath)->whereIn('status', ['Parse Error', 'Unknown'])->count();

    return view('cpr.index', [
        'results'             => $records,
        'folderPath'          => $folderPath,
        'perPage'             => $perPage,
        'page'                => $page,
        'total'               => $total,
        'lastPage'            => $lastPage,
        'fromDb'              => 0,
        'fromPdf'             => 0,
        'duplicates'          => [],
        'summaryValid'        => $summaryValid,
        'summaryExpiringSoon' => $summaryExpiringSoon,
        'summaryExpired'      => $summaryExpired,
        'summaryErrors'       => $summaryErrors,
    ]);
}
}