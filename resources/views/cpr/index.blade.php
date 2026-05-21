<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPR Expiry Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Inline dark mode init: runs before paint to prevent flicker --}}
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        .dark body                         { background-color: #111827; color: #f9fafb; }
        .dark .bg-white                    { background-color: #1f2937 !important; }
        .dark .bg-gray-100                 { background-color: #111827 !important; }
        .dark .bg-gray-50                  { background-color: #374151 !important; }
        .dark .text-gray-800               { color: #f9fafb !important; }
        .dark .text-gray-700               { color: #e5e7eb !important; }
        .dark .text-gray-600               { color: #d1d5db !important; }
        .dark .text-gray-400               { color: #9ca3af !important; }
        .dark .border-gray-300             { border-color: #4b5563 !important; }
        .dark .divide-gray-200 > *         { border-color: #374151 !important; }
        .dark .shadow                      { box-shadow: 0 1px 3px rgba(0,0,0,0.5) !important; }
        .dark input                        { background-color: #374151 !important; color: #f9fafb !important; border-color: #4b5563 !important; }
        .dark table thead                  { background-color: #374151 !important; }
        .dark th                           { color: #d1d5db !important; }
        .dark td                           { color: #e5e7eb !important; }
        .dark .bg-orange-50                { background-color: #1f2937 !important; }
        .dark .bg-orange-100               { background-color: #374151 !important; }
        .dark .hover\:bg-orange-100:hover  { background-color: #4b5563 !important; }
        .dark .bg-green-50                 { background-color: #064e3b !important; }
        .dark .bg-yellow-50                { background-color: #78350f !important; }
        .dark .bg-red-50                   { background-color: #7f1d1d !important; }
        .dark .border-green-200            { border-color: #065f46 !important; }
        .dark .border-yellow-200           { border-color: #92400e !important; }
        .dark .border-red-200              { border-color: #991b1b !important; }
        .dark .text-green-600              { color: #6ee7b7 !important; }
        .dark .text-green-800              { color: #a7f3d0 !important; }
        .dark .text-yellow-600             { color: #fde68a !important; }
        .dark .text-yellow-800             { color: #fef3c7 !important; }
        .dark .text-red-600                { color: #fca5a5 !important; }
        .dark .text-red-800                { color: #fee2e2 !important; }
        .dark .bg-green-100                { background-color: #065f46 !important; }
        .dark .bg-yellow-100               { background-color: #92400e !important; }
        .dark .bg-red-100                  { background-color: #991b1b !important; }
        .dark .bg-blue-50                  { background-color: #1e3a5f !important; }
        .dark .border-blue-200             { border-color: #1e40af !important; }
        .dark .text-blue-600               { color: #93c5fd !important; }
        .dark .text-blue-800               { color: #bfdbfe !important; }
        .dark #dark-toggle                 { background-color: #374151; color: #f9fafb; border-color: #4b5563; }
        .dark #dark-toggle:hover           { background-color: #4b5563; }
        .dark #loading-overlay             { background-color: rgba(17, 24, 39, 0.95) !important; }
        .dark #loading-msg                 { color: #d1d5db !important; }
        .dark .border-gray-300 button      { background-color: #374151 !important; color: #e5e7eb !important; border-color: #4b5563 !important; }
        .dark #duplicates-modal .bg-white        { background-color: #1f2937 !important; }
        .dark #duplicates-modal .border-gray-200 { border-color: #374151 !important; }
        .dark #duplicates-modal .bg-gray-50      { background-color: #374151 !important; }
        .dark #duplicates-modal .divide-gray-100 > * { border-color: #374151 !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">

    {{-- ── Loading Overlay ───────────────────────────────────────── --}}
    <div id="loading-overlay" style="display:none;" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white bg-opacity-90">
        <div class="text-center w-80">
            <div class="text-6xl animate-bounce mb-6">💊</div>
            {{-- <div id="stopwatch-display" style="font-variant-numeric:tabular-nums;letter-spacing:0.08em;"
                class="text-3xl font-mono font-medium text-gray-700 dark:text-gray-200">
                00:00
            </div> --}}
            <p id="loading-msg" class="text-gray-700 font-medium text-sm mb-1 truncate w-64 mx-auto"></p>
        </div>
    </div>

    {{-- ── Re-scan Success Notice ─────────────────────────────────── --}}
    @if(session('rescan_success'))
    <div id="rescan-notice" class="fixed top-6 right-6 z-40 w-96 bg-white border border-green-300 rounded-xl shadow-lg p-5">
        <div class="flex items-start gap-3">
            <div class="text-2xl">✅</div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-gray-800">Re-scan Complete</h3>
                <p class="text-xs text-gray-500 mt-1">{{ session('rescan_success') }}</p>
            </div>
            <button onclick="dismissRescanNotice()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
    </div>
    @endif

    

    {{-- ── Duplicates Modal ──────────────────────────────────────── --}}
    @if(isset($duplicates) && count($duplicates) > 0)
    <div id="duplicates-modal" class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-4 flex flex-col" style="max-height: 90vh;">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Previously Scanned Files Detected</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        <span class="font-semibold text-blue-600">{{ count($duplicates) }} file(s)</span> already existed in the database and were loaded instantly.
                        @if(isset($fromPdf) && $fromPdf > 0)
                            <span class="font-semibold text-green-600">{{ $fromPdf }} new file(s)</span> were freshly parsed.
                        @endif
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 px-6 pt-4 shrink-0">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ count($duplicates) }}</div>
                    <div class="text-xs text-blue-800 mt-0.5">Loaded from Database</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $fromPdf ?? 0 }}</div>
                    <div class="text-xs text-green-800 mt-0.5">Freshly Parsed</div>
                </div>
            </div>

            <div class="flex-1 overflow-hidden flex flex-col px-6 py-4">
                {{-- Fixed header --}}
                <table class="w-full text-sm table-fixed mb-0">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-60">File</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Reg. Number</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Brand Name</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Generic Name</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Expiry Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                        </tr>
                    </thead>
                </table>
                {{-- Scrollable body --}}
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-sm table-fixed">
                        <tbody class="divide-y divide-gray-100">
                            @foreach($duplicates as $dup)
                                <tr class="{{ $loop->even ? 'bg-orange-50' : 'bg-white' }}">
                                    <td class="px-3 py-2 text-gray-800 w-60">
                                        {{ $dup['normalized_filename'] ?? $dup['filename'] }}
                                        <div class="text-xs text-gray-400">{{ $dup['filename'] }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">{{ $dup['registration_number'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $dup['brand_name'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 text-gray-600">{{ $dup['generic_name'] ?? 'N/A' }}</td>
                                    <td class="px-3 py-2 text-gray-600">
                                        {{ $dup['expiry_date'] ? \Carbon\Carbon::parse($dup['expiry_date'])->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @php
                                            $dupStatusClasses = match($dup['status'] ?? '') {
                                                'Valid'         => 'bg-green-100 text-green-800',
                                                'Expiring Soon' => 'bg-yellow-100 text-yellow-800',
                                                'Expired'       => 'bg-red-100 text-red-800',
                                                default         => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $dupStatusClasses }}">
                                            {{ $dup['status'] ?? 'Unknown' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="modal-footer-default" class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 shrink-0">
                <button onclick="showRescanConfirm()"
                    class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                    Update
                </button>
                <button onclick="closeDuplicatesModal()"
                    class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                    Retain
                </button>
            </div>

            <div id="modal-footer-confirm" style="display:none;" class="px-6 py-4 border-t border-gray-200 shrink-0">
                <div class="flex items-start gap-3 mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="text-xl">⚠️</div>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Are you sure you want to re-scan all files?</p>
                        <p class="text-xs text-red-600 mt-1">This will <strong>delete all existing records</strong> for this folder from the database and re-parse every PDF from scratch. This cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="hideRescanConfirm()"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                        ← Cancel
                    </button>
                    <button onclick="forceFreshScan()"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition">
                        Confirm Re-scan
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif


    {{-- Success Toast --}}
    @if(session('success'))
    <div id="success-notice" class="fixed top-6 left-1/2 -translate-x-1/2 z-40 bg-green-50 border border-green-200 rounded-xl shadow-lg px-6 py-4 text-green-800 text-sm font-medium">
        {{ session('success') }}
    </div>
    <script>
        setTimeout(() => {
            const n = document.getElementById('success-notice');
            if (n) { n.style.opacity = '0'; setTimeout(() => n.remove(), 300); }
        }, 3000);
    </script>
    @endif


    {{-- ── Main Content ───────────────────────────────────────────── --}}
    <div class="max-w-7xl mx-auto">

        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">CPR Expiry Tracker</h1>
            <button
                onclick="toggleDarkMode()"
                id="dark-toggle"
                class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium bg-white text-gray-700 hover:bg-gray-100 transition"
            >
                🌙 Dark Mode
            </button>
        </div>

        <form id="scan-form" action="{{ route('cpr.scan') }}" method="POST" class="bg-white rounded-lg shadow p-6 mb-8">
            @csrf
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Folder Path (containing CPR PDFs)
                    </label>
                    <input
                        type="text"
                        name="folder_path"
                        value="{{ old('folder_path', $folderPath ?? '') }}"
                        placeholder="e.g. E:\CPR Files"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Scan Folder
                </button>
            </div>
            @error('folder_path')
                <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
            @enderror
        </form>

        @if(count($results) > 0)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Actions</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">File</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Reg. Number</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Brand Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Generic Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Expiry Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Days Left</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                            <!-- <th>OCR Confidence</th> -->
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($results as $cpr)
                            <tr
                                class="{{ $loop->even ? 'bg-orange-50' : 'bg-white' }} hover:bg-orange-100 cursor-pointer"
                                onclick="window.open('{{ route('cpr.open', ['folder_path' => $folderPath, 'filename' => $cpr['filename']]) }}', '_blank')"
                            >
                         <td class="px-4 py-3" onclick="event.stopPropagation()">
                            <a href="{{ route('cpr.edit', ['id' => $cpr['id'], 'page' => $page, 'per_page' => $perPage]) }}"
                                class="px-3 py-1 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 transition">
                                Edit
                            </a>
                        </td>
                                <td class="px-4 py-3 text-sm text-gray-800">
                                    {{ $cpr['normalized_filename'] ?? $cpr['filename'] }}
                                    <div class="text-xs text-gray-400">{{ $cpr['filename'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $cpr['registration_number'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $cpr['brand_name'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $cpr['generic_name'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $cpr['expiry_date'] ? \Carbon\Carbon::parse($cpr['expiry_date'])->format('M d, Y') : 'N/A' }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($cpr['days_remaining'] !== null)
                                        <span class="{{ $cpr['days_remaining'] < 0 ? 'text-red-600' : 'text-gray-600' }}">
                                            {{ $cpr['days_remaining'] }} days
                                        </span>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusClasses = match($cpr['status'] ?? '') {
                                            'Valid'          => 'bg-green-100 text-green-800',
                                            'Expiring Soon'  => 'bg-yellow-100 text-yellow-800',
                                            'Expired'        => 'bg-red-100 text-red-800',
                                            default          => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusClasses }}">
                                        {{ $cpr['status'] ?? 'Unknown' }}
                                    </span>
                                </td>
                                <!-- <td class="px-4 py-3 text-sm">
                            @php
                                $confidence = $cpr['ocr_confidence'] ?? 0;

                                $badge = match(true) {
                                    $confidence >= 90 => 'bg-green-100 text-green-800',
                                    $confidence >= 75 => 'bg-yellow-100 text-yellow-800',
                                    default           => 'bg-red-100 text-red-800',
                                };
                            @endphp

                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $badge }}">
                                {{ $confidence }}%
                            </span>
                            </td> -->

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{--
                Pagination forms no longer include folder_path as a hidden input.
                The controller reads it from session on paginated requests.
            --}}
            <div class="mt-4 flex items-center justify-between flex-wrap gap-2">

                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">Rows per page:</span>
                    @foreach([10, 20, 30] as $size)
                        @php $isDisabled = $total < $size && $perPage != $size; @endphp
                        @if($perPage == $size)
                            <span class="px-3 py-1 text-sm rounded-lg border bg-blue-600 text-white border-blue-600 cursor-default">{{ $size }}</span>
                        @elseif($isDisabled)
                            <span class="px-3 py-1 text-sm rounded-lg border bg-gray-100 text-gray-300 border-gray-200 cursor-not-allowed">{{ $size }}</span>
                        @else
                            <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="per_page" value="{{ $size }}">
                                <input type="hidden" name="page" value="1">
                                <button type="submit" class="px-3 py-1 text-sm rounded-lg border transition bg-white text-gray-600 border-gray-300 hover:bg-gray-50">{{ $size }}</button>
                            </form>
                        @endif
                    @endforeach
                    <span class="text-sm text-gray-400 ml-2">
                        Showing {{ ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $total) }} of {{ $total }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    @if($page > 1)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $page - 1 }}">
                            <button type="submit" class="px-3 py-1 text-sm rounded-lg border bg-white text-gray-600 border-gray-300 hover:bg-gray-50">← Prev</button>
                        </form>
                    @endif

                    @foreach(range(1, $lastPage) as $pageNum)
                        @if($page == $pageNum)
                            <span class="px-3 py-1 text-sm rounded-lg border bg-blue-600 text-white border-blue-600 cursor-default">{{ $pageNum }}</span>
                        @else
                            <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="per_page" value="{{ $perPage }}">
                                <input type="hidden" name="page" value="{{ $pageNum }}">
                                <button type="submit" class="px-3 py-1 text-sm rounded-lg border transition bg-white text-gray-600 border-gray-300 hover:bg-gray-50">{{ $pageNum }}</button>
                            </form>
                        @endif
                    @endforeach

                    @if($page < $lastPage)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $page + 1 }}">
                            <button type="submit" class="px-3 py-1 text-sm rounded-lg border bg-white text-gray-600 border-gray-300 hover:bg-gray-50">Next →</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid grid-cols-4 gap-4">
                @php
                    $filters = [
                        ['key' => 'Valid',         'count' => $summaryValid,        'bg' => 'bg-green-50',  'border' => 'border-green-200',  'text' => 'text-green-600',  'label' => 'text-green-800'],
                        ['key' => 'Expiring Soon', 'count' => $summaryExpiringSoon, 'bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'text' => 'text-yellow-600', 'label' => 'text-yellow-800'],
                        ['key' => 'Expired',       'count' => $summaryExpired,      'bg' => 'bg-red-50',    'border' => 'border-red-200',    'text' => 'text-red-600',    'label' => 'text-red-800'],
                        ['key' => 'Unknown',       'count' => $summaryErrors,       'bg' => 'bg-gray-50',   'border' => 'border-gray-200',   'text' => 'text-gray-600',   'label' => 'text-gray-800'],
                    ];
                @endphp

                @foreach($filters as $f)
                    @php
                        $isActive  = ($filterStatus ?? '') === $f['key'];
                        $ringClass = $isActive ? 'ring-2 ring-offset-2 ring-blue-500 scale-105' : 'opacity-60 hover:opacity-100';
                    @endphp
                    <form action="{{ route('cpr.scan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="per_page"      value="{{ $perPage }}">
                        <input type="hidden" name="page"          value="1">
                        <input type="hidden" name="filter_status" value="{{ $f['key'] }}">
                        <button type="submit"
                            class="w-full {{ $f['bg'] }} {{ $f['border'] }} border rounded-lg p-4 text-center transition-all duration-150 {{ $ringClass }} cursor-pointer">
                            <div class="text-2xl font-bold {{ $f['text'] }}">{{ $f['count'] }}</div>
                            <div class="text-sm {{ $f['label'] }}">{{ $f['key'] }}</div>
                        </button>
                    </form>
                @endforeach
            </div>

        @elseif(isset($filterStatus) && $filterStatus)
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <div class="text-4xl mb-3">
                    @php
                        echo match($filterStatus) {
                            'Valid'         => '✅',
                            'Expiring Soon' => '⚠️',
                            'Expired'       => '❌',
                            default         => '🔍',
                        };
                    @endphp
                </div>
                <p class="text-gray-600 font-medium">
                    @php
                        echo match($filterStatus) {
                            'Valid'         => 'No valid CPRs found.',
                            'Expiring Soon' => 'No expiring soon CPRs found.',
                            'Expired'       => 'No expired CPRs found.',
                            default         => "No records found for \"$filterStatus\".",
                        };
                    @endphp
                </p>
            </div>

            {{-- Keep summary cards visible so user can switch filters --}}
            <div class="mt-6 grid grid-cols-4 gap-4">
                @php
                    $filters = [
                        ['key' => 'Valid',         'count' => $summaryValid,        'bg' => 'bg-green-50',  'border' => 'border-green-200',  'text' => 'text-green-600',  'label' => 'text-green-800'],
                        ['key' => 'Expiring Soon', 'count' => $summaryExpiringSoon, 'bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'text' => 'text-yellow-600', 'label' => 'text-yellow-800'],
                        ['key' => 'Expired',       'count' => $summaryExpired,      'bg' => 'bg-red-50',    'border' => 'border-red-200',    'text' => 'text-red-600',    'label' => 'text-red-800'],
                        ['key' => 'Unknown',       'count' => $summaryErrors,       'bg' => 'bg-gray-50',   'border' => 'border-gray-200',   'text' => 'text-gray-600',   'label' => 'text-gray-800'],
                    ];
                @endphp
                @foreach($filters as $f)
                    @php $isActive = ($filterStatus ?? '') === $f['key']; @endphp
                    <form action="{{ route('cpr.scan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="per_page"      value="{{ $perPage }}">
                        <input type="hidden" name="page"          value="1">
                        <input type="hidden" name="filter_status" value="{{ $f['key'] }}">
                        <button type="submit"
                            class="w-full {{ $f['bg'] }} {{ $f['border'] }} border rounded-lg p-4 text-center transition-all duration-150 {{ $isActive ? 'ring-2 ring-offset-2 ring-blue-500 scale-105' : 'opacity-60 hover:opacity-100' }} cursor-pointer">
                            <div class="text-2xl font-bold {{ $f['text'] }}">{{ $f['count'] }}</div>
                            <div class="text-sm {{ $f['label'] }}">{{ $f['key'] }}</div>
                        </button>
                    </form>
                @endforeach
            </div>

        @elseif(isset($folderPath) && $folderPath)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <p class="text-yellow-800">No PDF files found in the specified folder.</p>
            </div>
        @endif

    </div>

    <script>
        let _swInterval = null;
        let _swStart    = null;

        // function startStopwatch() {
        //     _swStart = Date.now();
        //     const el = document.getElementById('stopwatch-display');
        //     if (el) el.textContent = '00:00';
        //     _swInterval = setInterval(() => {
        //         if (!el) return;
        //         const ms      = Date.now() - _swStart;
        //         const totalSec = Math.floor(ms / 1000);
        //         const min      = Math.floor(totalSec / 60);
        //         const sec      = totalSec % 60;
        //         el.textContent = String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        //     }, 500);
        // }

        // function stopStopwatch() {
        //     clearInterval(_swInterval);
        //     _swInterval = null;
        //     _swStart    = null;
        // }
        function toggleDarkMode() {
            const html = document.documentElement;
            const btn  = document.getElementById('dark-toggle');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                btn.textContent = '🌙';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                btn.textContent = '☀️';
                localStorage.setItem('darkMode', 'true');
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('dark-toggle');
            if (btn) btn.textContent = localStorage.getItem('darkMode') === 'true' ? '☀️' : '🌙';
        });

        const loadingMessages = [
            "🔍 Hunting for expired meds...",
            "📄 Checking database records...",
            "💊 Parsing PDF files...",
            "🧪 Running lab tests on your files...",
            "📋 Filing paperwork at FDA speed...",
            "☕ Grabbing coffee while parser works...",
            "🐌 OCR is doing its best, we promise...",
            "🕵️ Investigating suspicious expiry dates...",
            "📅 Asking the calendar nicely...",
        ];

        let sseSource = null;

        function showLoading(folderPath) {
            document.getElementById('loading-overlay').style.display = 'flex';

            let msgIndex = 0;
            document.getElementById('loading-msg').textContent = loadingMessages[0];

            window._msgInterval = setInterval(() => {
                msgIndex = (msgIndex + 1) % loadingMessages.length;
                document.getElementById('loading-msg').textContent = loadingMessages[msgIndex];
            }, 2500);

            if (!folderPath) return;

            if (sseSource) { sseSource.close(); sseSource = null; }

            sseSource = new EventSource(`/cpr/progress?folder_path=${encodeURIComponent(folderPath)}`);

            sseSource.onmessage = function (e) {
                const data = JSON.parse(e.data);
                document.getElementById('loading-msg').textContent = data.msg;
                if (data.done) {
                    sseSource.close();
                    sseSource = null;
                    if (window._msgInterval) { clearInterval(window._msgInterval); window._msgInterval = null; }
                    document.getElementById('loading-msg').textContent = '⏳ Finalizing records, almost there...';
                }
            };

            sseSource.onerror = function () {
                if (sseSource) { sseSource.close(); sseSource = null; }
            };
        }

        function hideLoading() {
            // stopStopwatch();
            if (window._msgInterval) { clearInterval(window._msgInterval); window._msgInterval = null; }
            if (sseSource)           { sseSource.close(); sseSource = null; }
            setTimeout(() => { document.getElementById('loading-overlay').style.display = 'none'; }, 400);
        }

        window.addEventListener('load', hideLoading);

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                const folderInput  = document.querySelector('#scan-form input[name="folder_path"]');
                const folderPath   = folderInput ? folderInput.value.trim() : null;
                const isPagination = this.querySelector('input[name="page"]') !== null
                                || this.querySelector('input[name="per_page"]') !== null;
                showLoading(isPagination ? null : folderPath);

                if (!isPagination) startStopwatch();  // ← only line added
            });
        });

        function closeDuplicatesModal() {
            const modal = document.getElementById('duplicates-modal');
            if (modal) {
                modal.style.transition = 'opacity 0.2s ease';
                modal.style.opacity    = '0';
                setTimeout(() => modal.remove(), 200);
            }
        }

        function showRescanConfirm() {
            document.getElementById('modal-footer-default').style.display = 'none';
            document.getElementById('modal-footer-confirm').style.display = 'block';
        }

        function hideRescanConfirm() {
            document.getElementById('modal-footer-confirm').style.display = 'none';
            document.getElementById('modal-footer-default').style.display = 'flex';
        }

        function forceFreshScan() {
            const form  = document.getElementById('scan-form');
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'force_rescan';
            input.value = '1';
            form.appendChild(input);
            closeDuplicatesModal();

            const folderInput = document.querySelector('#scan-form input[name="folder_path"]');
            const folderPath  = folderInput ? folderInput.value.trim() : null;
            showLoading(folderPath);
            form.submit();
        }

        function dismissRescanNotice() {
            const notice = document.getElementById('rescan-notice');
            if (notice) {
                notice.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                notice.style.opacity    = '0';
                notice.style.transform  = 'translateX(100%)';
                setTimeout(() => notice.remove(), 300);
            }
        }

        if (document.getElementById('rescan-notice')) {
            setTimeout(() => dismissRescanNotice(), 6000);
        }
    </script>
</body>
</html>