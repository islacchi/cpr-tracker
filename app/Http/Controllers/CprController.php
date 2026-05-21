<?php

namespace App\Http\Controllers;

use App\Http\Requests\CprScanRequest;
use App\Http\Requests\CprUpdateRequest;
use App\Models\CprRecord;
use App\Services\CprScanService;
use Illuminate\Http\Request;

class CprController extends Controller
{
    public function __construct(private readonly CprScanService $scanService) {}

    // ── Index ────────────────────────────────────────────────────────────────

    public function index()
    {
        $folderPath = session('last_folder_path');

        if ($folderPath && !session()->has('errors')) {
            return redirect()->route('cpr.results');
        }

        return view('cpr.index', CprScanService::emptyViewData());
    }

    // ── Scan ─────────────────────────────────────────────────────────────────

    public function scan(CprScanRequest $request)
    {
        $folderPath = $this->resolveFolderPath($request);

        if (!$folderPath) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => 'Please enter a folder path before scanning.'])
                ->withInput();
        }

        $validationError = $this->scanService->validateFolder($folderPath);
        if ($validationError) {
            return redirect()->route('cpr.index')
                ->withErrors(['folder_path' => $validationError])
                ->withInput();
        }

        session(['last_folder_path' => $folderPath]);

        $isPagination = $request->isPagination();
        $perPage      = (int) $request->input('per_page', 10);
        $page         = (int) $request->input('page', 1);

        // ── Filter resolution ────────────────────────────────────────────────
        $incomingFilter = $request->input('filter_status');
        if (!$isPagination) {
            $filterStatus = null;
            session()->forget('cpr_filter_status');
        } elseif ($incomingFilter !== null) {
            $current      = session('cpr_filter_status');
            $filterStatus = ($incomingFilter === $current) ? null : $incomingFilter;
            session(['cpr_filter_status' => $filterStatus]);
        } else {
            $filterStatus = session('cpr_filter_status');
        }
        // ────────────────────────────────────────────────────────────────────
        
        // ── Search resolution ────────────────────────────────────────────────
        $incomingSearch = $request->input('search');
        if (!$isPagination) {
            $search = null;
            session()->forget('cpr_search');
        } elseif ($incomingSearch !== null) {
            $search = $incomingSearch === '' ? null : $incomingSearch;
            session(['cpr_search' => $search]);
        } else {
            $search = session('cpr_search');
        }
        // ────────────────────────────────────────────────────────────────────

        if ($isPagination) {
            $fromDb  = session('scan_from_db', 0);
            $fromPdf = session('scan_from_pdf', 0);
            session()->forget('scan_duplicates');
        } else {
            [$fromDb, $fromPdf] = $this->scanService->runScan($folderPath, (bool) $request->input('force_rescan'));

            if ($request->input('force_rescan')) {
                session()->flash('rescan_success', 'All records have been wiped and re-parsed successfully.');
            }
        }

        [$records, $total, $lastPage] = $this->scanService->paginateResults($folderPath, $page, $perPage, $filterStatus, $search);

        session([
            'cpr_per_page' => $perPage,
            'cpr_page'     => $page,
        ]);

        return redirect()->route('cpr.results', [
            'page'          => $page,
            'per_page'      => $perPage,
            'filter_status' => $filterStatus,
            'search'        => $search,
        ]);
    }

    // ── Results (post-edit redirect target) ──────────────────────────────────

    /**
     * Display the current folder's results without re-scanning.
     * Used as the redirect target after edit/update so the user
     * lands back on the table they came from.
     */
    public function results(Request $request)
    {
        $folderPath = session('last_folder_path');

        if (!$folderPath) {
            return redirect()->route('cpr.index');
        }

        $perPage      = (int) $request->input('per_page', 10);
        $page         = (int) $request->input('page', 1);
        $filterStatus = $request->input('filter_status', session('cpr_filter_status'));
        $search       = $request->input('search', session('cpr_search'));

        [$records, $total, $lastPage] = $this->scanService->paginateResults($folderPath, $page, $perPage, $filterStatus, $search);
        $counts = $this->scanService->summaryCounts($folderPath,$search);

        return view('cpr.index', [
            'results'             => $records,
            'folderPath'          => $folderPath,
            'perPage'             => $perPage,
            'page'                => $page,
            'total'               => $total,
            'lastPage'            => $lastPage,
            'fromDb'              => 0,
            'fromPdf'             => 0,
            'duplicates'          => session('scan_duplicates', []),
            'summaryValid'        => $counts['valid'],
            'summaryExpiringSoon' => $counts['expiring'],
            'summaryExpired'      => $counts['expired'],
            'summaryErrors'       => $counts['errors'],
            'filterStatus'        => $filterStatus,
            'search'              => $search,
        ]);
    }

    // ── Edit / Update ────────────────────────────────────────────────────────

    public function edit(int $id)
    {
        $cpr = CprRecord::findOrFail($id);
        $this->authorizeRecord($cpr);

        return view('cpr.edit', compact('cpr'));
    }

    public function cancelEdit()
    {
        session()->forget('scan_duplicates');
        return redirect()->route('cpr.results');
    }

    public function search(Request $request)
    {
        $folderPath   = session('last_folder_path');
        $search       = $request->input('search', '');
        $filterStatus = session('cpr_filter_status');
        $perPage      = (int) $request->input('per_page', session('cpr_per_page', 10));

        if (!$folderPath) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        session(['cpr_search' => $search === '' ? null : $search]);

        [$records, $total, $lastPage] = $this->scanService->paginateResults(
            $folderPath, 1, $perPage, $filterStatus, $search === '' ? null : $search
        );

        $counts = $this->scanService->summaryCounts($folderPath, $search === '' ? null : $search);

        return response()->json([
            'results'      => $records,
            'total'        => $total,
            'lastPage'     => $lastPage,
            'counts'       => $counts,
        ]);
    }

    public function update(CprUpdateRequest $request, int $id)
    {
        $cpr = CprRecord::findOrFail($id);
        $this->authorizeRecord($cpr);

        $expiryDate = $request->input('expiry_date');
        $computed = CprRecord::resolveStatus($expiryDate, 90, $request->input('brand_name'));

        $cpr->update([
            'registration_number' => $request->input('registration_number'),
            'brand_name'          => $request->input('brand_name'),
            'generic_name'        => $request->input('generic_name'),
            'expiry_date'         => $expiryDate,
            'days_remaining'      => $computed['days_remaining'],
            'status'              => $computed['status'],
            'normalized_filename' => CprRecord::buildNormalizedFilename(
                $request->input('generic_name'),
                $request->input('brand_name'),
                $expiryDate
            ),
        ]);

        session()->forget('scan_duplicates');
        session()->flash('success', '✅ CPR record updated successfully!');

        return redirect()->route('cpr.results');
    }

    // ── Open PDF ─────────────────────────────────────────────────────────────

    public function openPdf(Request $request)
    {
        $folderPath = $request->input('folder_path');
        $filename   = basename($request->input('filename', ''));

        if (empty($filename) || empty($folderPath)) {
            abort(400, 'Missing parameters.');
        }

        $folderReal = realpath($folderPath);
        $filePath   = realpath($folderPath . DIRECTORY_SEPARATOR . $filename);

        if (!$folderReal || !$filePath || !str_starts_with($filePath, $folderReal . DIRECTORY_SEPARATOR)) {
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

    /**
     * Streams file classification status (DB hit vs. needs parse).
     *
     * NOTE: This reflects pre-scan classification only — not actual parse
     * completion. True per-file progress would require parse workers writing
     * to a shared cache (e.g. Redis) that this endpoint polls.
     */
    public function progress(Request $request)
    {
        $folderPath  = $request->input('folder_path');
        $sessionPath = session('last_folder_path');

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $send = fn(array $payload) => print("data: " . json_encode($payload) . "\n\n") && ob_flush() && flush();

        if (!$folderPath || $folderPath !== $sessionPath) {
            $send(['msg' => 'Invalid folder path.', 'done' => true]);
            return;
        }

        $validationError = $this->scanService->validateFolder($folderPath);
        if ($validationError) {
            $send(['msg' => 'Folder not accessible.', 'done' => true]);
            return;
        }

        $files = glob($folderPath . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
        $total = count($files);

        if ($total === 0) {
            $send(['msg' => 'No files found.', 'done' => true]);
            return;
        }

        $existingFilenames = CprRecord::whereIn('filename', array_map('basename', $files))
            ->whereNotNull('registration_number')
            ->whereNotNull('expiry_date')
            ->pluck('filename')
            ->flip();

        foreach ($files as $index => $file) {
            $filename = basename($file);
            $msg      = isset($existingFilenames[$filename])
                ? "📂 Loading from DB: {$filename}"
                : "📄 Parsing: {$filename}";

            $send(['msg' => $msg, 'current' => $index + 1, 'total' => $total, 'done' => false]);
        }

        $send(['msg' => '✅ Scan complete!', 'done' => true]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function resolveFolderPath(CprScanRequest $request): ?string
    {
        $raw = $request->isPagination()
            ? session('last_folder_path')
            : $request->input('folder_path');

        if (empty($raw)) {
            return null;
        }

        return rtrim(trim((string) $raw), DIRECTORY_SEPARATOR);
    }

    /**
     * Ensure the record belongs to the current session's folder.
     * Prevents cross-session ID enumeration (e.g. user guesses /edit/1234).
     */
    private function authorizeRecord(CprRecord $cpr): void
    {
        $sessionPath = session('last_folder_path');

        if (!$sessionPath || $cpr->folder_path !== $sessionPath) {
            abort(403, 'You do not have access to this record.');
        }
    }
}