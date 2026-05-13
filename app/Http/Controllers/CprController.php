<?php

namespace App\Http\Controllers;

use App\Models\CprRecord;
use Illuminate\Http\Request;

class CprController extends Controller
{
    public function index()
    {
        return view('cpr.index', ['results' => [], 'folder_path' => null]);
    }

    public function scan(Request $request)
{
    set_time_limit(300); 
    $request->validate([
        'folder_path' => 'required|string',
    ]);

    $folderPath = $request->input('folder_path');
    $results    = [];

    if (!is_dir($folderPath)) {
        return back()->withErrors(['folder_path' => 'Folder not found.'])->withInput();
    }

    $parser = new \App\Services\CprParser();
    $files  = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf');

foreach ($files as $file) {
    $filename = basename($file);

    // First check by original filename
    $existing = \App\Models\CprRecord::where('filename', $filename)
        ->where('folder_path', $folderPath)
        ->whereNotNull('registration_number')
        ->whereNotNull('expiry_date')
        ->first();

    if ($existing) {
        $results[] = $existing->toArray();
        continue;
    }

    $parsed   = $parser->parse($file);

    $daysRemaining = null;
    $status        = $parsed['status'];

    if ($parsed['expiry_date']) {
        $expiry        = \Carbon\Carbon::parse($parsed['expiry_date']);
        $daysRemaining = (int) now()->startOfDay()->diffInDays($expiry, false);

        $status = match(true) {
            $daysRemaining < 0    => 'Expired',
            $daysRemaining <= 90  => 'Expiring Soon',
            default               => 'Valid',
        };
    }

    // Generate normalized filename
    $normalizedFilename = $this->normalizeFilename(
        $parsed['generic_name'],
        $parsed['brand_name'],
        $parsed['expiry_date']
    );

    // Check if normalized filename already exists in DB
    $existingNormalized = \App\Models\CprRecord::where('normalized_filename', $normalizedFilename)
        ->whereNotNull('expiry_date')
        ->first();

    if ($existingNormalized) {
        // Use existing DB data but add to results
        $results[] = $existingNormalized->toArray();
        continue;
    }

    $record = \App\Models\CprRecord::updateOrCreate(
        ['filename' => $filename, 'folder_path' => $folderPath],
        [
            'normalized_filename' => $normalizedFilename,
            'registration_number' => $parsed['registration_number'],
            'brand_name'          => $parsed['brand_name'],
            'generic_name'        => $parsed['generic_name'],
            'expiry_date'         => $parsed['expiry_date'],
            'days_remaining'      => $daysRemaining,
            'status'              => $status,
        ]
    );

    $results[] = $record->toArray();
}

    usort($results, function ($a, $b) {
        return strnatcasecmp($a['filename'], $b['filename']);
    });

    return view('cpr.index', compact('results', 'folderPath'));
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