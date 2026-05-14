<?php

namespace App\Http\Controllers;

use App\Models\CprRecord;
use Illuminate\Http\Request;

class CprController extends Controller
{
public function index()
{
    return view('cpr.index', [
        'results'     => [],
        'folder_path' => null,
        'folderPath'  => null,
        'perPage'     => 10,
        'page'        => 1,
        'total'       => 0,
        'lastPage'    => 1,
        'fromDb'      => 0,
        'fromPdf'     => 0,
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

    $request->validate(['folder_path' => 'required|string']);

    if (!is_dir($folderPath)) {
        return back()->withErrors(['folder_path' => 'Folder not found.'])->withInput();
    }

    session(['last_folder_path' => $folderPath]);

    // Force rescan — clear existing records for this folder
    if ($request->input('force_rescan')) {
        \App\Models\CprRecord::where('filename', function($query) use ($folderPath) {
            $query->select('filename')
                  ->from('cpr_records')
                  ->where('folder_path', $folderPath);
        })->delete();
    }

    $isFreshScan = !$request->has('page') && !$request->has('per_page');

    $fromDb  = 0;
    $fromPdf = 0;

    if ($isFreshScan) {
        $parser = new \App\Services\CprParser();
        $files  = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf');

        foreach ($files as $file) {
            $filename = basename($file);

            // Check if already in DB with matching values
            $existing = \App\Models\CprRecord::where('filename', $filename)
                ->whereNotNull('registration_number')
                ->whereNotNull('expiry_date')
                ->first();

            if ($existing) {
                $parsed = $parser->parse($file);

                $valuesMatch =
                    $existing->registration_number === $parsed['registration_number'] &&
                    $existing->expiry_date         == $parsed['expiry_date'];

                if ($valuesMatch) {
                    // Same data — update folder_path in case it moved
                    $existing->update(['folder_path' => $folderPath]);
                    $fromDb++;
                    continue;
                }
            } else {
                $parsed = $parser->parse($file);
            }

            // New or updated file — parse and store
            $daysRemaining = null;
            $status        = $parsed['status'];

            if ($parsed['expiry_date']) {
                $expiry        = \Carbon\Carbon::parse($parsed['expiry_date']);
                $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);
                $status        = match(true) {
                    $daysRemaining < 0    => 'Expired',
                    $daysRemaining <= 90  => 'Expiring Soon',
                    default               => 'Valid',
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

        // Store counts in session
        session([
            'scan_from_db'  => $fromDb,
            'scan_from_pdf' => $fromPdf,
        ]);

    } else {
        // Paginating — pull counts from session
        $fromDb  = session('scan_from_db', 0);
        $fromPdf = session('scan_from_pdf', 0);
    }

    // Load from DB for display
    $total    = \App\Models\CprRecord::where('folder_path', $folderPath)->count();
    $lastPage = (int) ceil($total / $perPage);
    $records  = \App\Models\CprRecord::where('folder_path', $folderPath)
        ->orderByRaw('CAST(REGEXP_SUBSTR(filename, "^[0-9]+") AS UNSIGNED) ASC')
        ->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get()
        ->toArray();

    return view('cpr.index', [
        'results'    => $records,
        'folderPath' => $folderPath,
        'perPage'    => $perPage,
        'page'       => $page,
        'total'      => $total,
        'lastPage'   => $lastPage,
        'fromDb'     => $fromDb,
        'fromPdf'    => $fromPdf,
    ]);
}
private function normalizeFilename(?string $genericName, ?string $brandName, ?string $expiryDate): string
{
    $generic = $genericName ? strtolower(trim($genericName)) : 'unknown';
    $brand   = $brandName   ? strtoupper(trim($brandName))   : 'UNKNOWN';
    $expiry  = $expiryDate
        ? \Carbon\Carbon::parse($expiryDate)->format('M Y')
        : 'No Expiry';

    // Capitalize first letter of each word for generic name
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
}