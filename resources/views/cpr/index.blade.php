<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPR Expiry Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ── Dark Mode Base ────────────────────────────────────── */
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

        /* ── Dark Mode Input ───────────────────────────────────── */
        .dark input                        { background-color: #374151 !important; color: #f9fafb !important; border-color: #4b5563 !important; }

        /* ── Dark Mode Table ───────────────────────────────────── */
        .dark table thead                  { background-color: #374151 !important; }
        .dark th                           { color: #d1d5db !important; }
        .dark td                           { color: #e5e7eb !important; }

        /* ── Dark Mode Alternating Rows ────────────────────────── */
        .dark .bg-orange-50                { background-color: #1f2937 !important; }
        .dark .bg-orange-100               { background-color: #374151 !important; }
        .dark .hover\:bg-orange-100:hover  { background-color: #4b5563 !important; }

        /* ── Dark Mode Summary Cards ───────────────────────────── */
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

        /* ── Dark Mode Status Badges ───────────────────────────── */
        .dark .bg-green-100                { background-color: #065f46 !important; }
        .dark .bg-yellow-100               { background-color: #92400e !important; }
        .dark .bg-red-100                  { background-color: #991b1b !important; }
        .dark .bg-gray-100                 { background-color: #374151 !important; }

        /* ── Dark Mode Buttons ─────────────────────────────────── */
        .dark #dark-toggle                 { background-color: #374151; color: #f9fafb; border-color: #4b5563; }
        .dark #dark-toggle:hover           { background-color: #4b5563; }

        /* ── Dark Mode Loading Overlay ─────────────────────────── */
        .dark #loading-overlay             { background-color: rgba(17, 24, 39, 0.95) !important; }
        .dark #loading-msg                 { color: #d1d5db !important; }

        /* ── Dark Mode Pagination Buttons ──────────────────────── */
        .dark .border-gray-300 button      { background-color: #374151 !important; color: #e5e7eb !important; border-color: #4b5563 !important; }

        /* ── Dark Mode Scan Notice ──────────────────────────────── */
        .dark #scan-notice                 { background-color: #1f2937 !important; border-color: #3b82f6 !important; }
        .dark #scan-notice h3              { color: #f9fafb !important; }
        .dark #scan-notice p               { color: #9ca3af !important; }

        /* ── Scan Notice Transition ─────────────────────────────── */
        #scan-notice { transition: opacity 0.3s ease, transform 0.3s ease; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">

    {{-- Loading Overlay --}}
    <div id="loading-overlay" style="display:none;" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-white bg-opacity-90">
        <div class="text-center w-80">
            <div class="text-6xl animate-bounce mb-4">💊</div>
            <div class="w-64 h-3 bg-gray-200 rounded-full overflow-hidden mb-4 mx-auto">
                <div id="loading-bar" class="h-full bg-blue-500 rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            <p id="loading-msg" class="text-gray-600 font-medium text-sm mt-2 h-6 truncate"></p>
            <p class="text-xs text-gray-400 mt-1">Please don't touch anything... 🙏</p>
        </div>
    </div>

    {{-- Previously Scanned Notification --}}
    @if(isset($fromDb) && $fromDb > 0)
    <div id="scan-notice" class="fixed top-6 right-6 z-40 w-96 bg-white border border-blue-200 rounded-xl shadow-lg p-5">
        <div class="flex items-start gap-3">
            <div class="text-2xl">📂</div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-gray-800">Previously Scanned Files Found</h3>
                <p class="text-xs text-gray-500 mt-1">
                    <span class="font-medium text-blue-600">{{ $fromDb }} file(s)</span> already scanned — displaying data from previous scan.
                    @if($fromPdf > 0)
                        <span class="font-medium text-green-600">{{ $fromPdf }} new file(s)</span> were freshly parsed.
                    @endif
                </p>
                <div class="flex gap-2 mt-3">
                    <button onclick="dismissNotice()"
                        class="px-3 py-1 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                        Got it ✓
                    </button>
                    <button onclick="forceFreshScan()"
                        class="px-3 py-1 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                        Re-scan All
                    </button>
                </div>
            </div>
            <button onclick="dismissNotice()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
    </div>
    @endif

    <div class="max-w-7xl mx-auto">

        {{-- Header --}}
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">📋 CPR Expiry Tracker</h1>
            <button
                onclick="toggleDarkMode()"
                id="dark-toggle"
                class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium bg-white text-gray-700 hover:bg-gray-100 transition"
            >
                🌙 Dark Mode
            </button>
        </div>

        {{-- Folder Selection Form --}}
        <form action="{{ route('cpr.scan') }}" method="POST" class="bg-white rounded-lg shadow p-6 mb-8">
            @csrf
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Folder Path (containing CPR PDFs)
                    </label>
                    <input
                        type="text"
                        name="folder_path"
                        value="{{ $folderPath ?? '' }}"
                        placeholder="e.g. E:\CPR Files"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                <button
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    Scan Folder
                </button>
            </div>
            @error('folder_path')
                <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
            @enderror
        </form>

        {{-- Results Table --}}
        @if(count($results) > 0)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">File</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Reg. Number</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Brand Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Generic Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Expiry Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Days Left</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($results as $cpr)
                            <tr
                                class="{{ $loop->even ? 'bg-orange-50' : 'bg-white' }} hover:bg-orange-100 cursor-pointer"
                                onclick="window.open('{{ route('cpr.open', ['folder_path' => $folderPath, 'filename' => $cpr['filename']]) }}', '_blank')"
                            >
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
                                        $statusClasses = match($cpr['status']) {
                                            'Valid'          => 'bg-green-100 text-green-800',
                                            'Expiring Soon'  => 'bg-yellow-100 text-yellow-800',
                                            'Expired'        => 'bg-red-100 text-red-800',
                                            default          => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusClasses }}">
                                        {{ $cpr['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-4 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600">Rows per page:</span>
                    @foreach([10, 20, 30] as $size)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="folder_path" value="{{ $folderPath }}">
                            <input type="hidden" name="per_page" value="{{ $size }}">
                            <input type="hidden" name="page" value="1">
                            <button type="submit"
                                class="px-3 py-1 text-sm rounded-lg border transition
                                    {{ $perPage == $size
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                                {{ $size }}
                            </button>
                        </form>
                    @endforeach
                    <span class="text-sm text-gray-400 ml-2">
                        Showing {{ ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $total) }} of {{ $total }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Previous --}}
                    @if($page > 1)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="folder_path" value="{{ $folderPath }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $page - 1 }}">
                            <button type="submit" class="px-3 py-1 text-sm rounded-lg border bg-white text-gray-600 border-gray-300 hover:bg-gray-50">
                                ← Prev
                            </button>
                        </form>
                    @endif

                    {{-- Page Numbers --}}
                    @for($i = 1; $i <= $lastPage; $i++)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="folder_path" value="{{ $folderPath }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $i }}">
                            <button type="submit"
                                class="px-3 py-1 text-sm rounded-lg border transition
                                    {{ $page == $i
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                                {{ $i }}
                            </button>
                        </form>
                    @endfor

                    {{-- Next --}}
                    @if($page < $lastPage)
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="folder_path" value="{{ $folderPath }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $page + 1 }}">
                            <button type="submit" class="px-3 py-1 text-sm rounded-lg border bg-white text-gray-600 border-gray-300 hover:bg-gray-50">
                                Next →
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="mt-6 grid grid-cols-4 gap-4">
                @php
                    $valid        = collect($results)->where('status', 'Valid')->count();
                    $expiringSoon = collect($results)->where('status', 'Expiring Soon')->count();
                    $expired      = collect($results)->where('status', 'Expired')->count();
                    $errors       = collect($results)->whereIn('status', ['Parse Error', 'Unknown'])->count();
                @endphp
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $valid }}</div>
                    <div class="text-sm text-green-800">Valid</div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $expiringSoon }}</div>
                    <div class="text-sm text-yellow-800">Expiring Soon</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $expired }}</div>
                    <div class="text-sm text-red-800">Expired</div>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-600">{{ $errors }}</div>
                    <div class="text-sm text-gray-800">Errors</div>
                </div>
            </div>

        @elseif(isset($folderPath) && $folderPath)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <p class="text-yellow-800">No PDF files found in the specified folder.</p>
            </div>
        @endif

    </div>

    <script>
        // ── Dark Mode ─────────────────────────────────────────────
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
            document.getElementById('dark-toggle').textContent = '☀️ Light Mode';
        }

        function toggleDarkMode() {
            const html = document.documentElement;
            const btn  = document.getElementById('dark-toggle');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                btn.textContent = '🌙 Dark Mode';
                localStorage.setItem('darkMode', 'false');
            } else {
                html.classList.add('dark');
                btn.textContent = '☀️ Light Mode';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // ── Loading Indicator ─────────────────────────────────────
        const messages = [
            "🔍 Hunting for expired meds...",
            "📄 Bribing the PDF parser...",
            "🤖 Teaching robots to read doctor's handwriting...",
            "💊 Counting pills virtually...",
            "🧪 Running lab tests on your files...",
            "📋 Filing paperwork at FDA speed...",
            "☕ Grabbing coffee while Tesseract works...",
            "🐌 OCR is doing its best, we promise...",
            "🕵️ Investigating suspicious expiry dates...",
            "📅 Asking the calendar nicely...",
        ];

        let msgIndex = 0, barWidth = 0;
        let msgInterval, barInterval;

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
            document.getElementById('loading-msg').textContent = messages[0];
            barWidth = 0;
            msgInterval = setInterval(() => {
                msgIndex = (msgIndex + 1) % messages.length;
                document.getElementById('loading-msg').textContent = messages[msgIndex];
            }, 2000);
            barInterval = setInterval(() => {
                if (barWidth < 90) {
                    barWidth += Math.random() * 5;
                    document.getElementById('loading-bar').style.width = barWidth + '%';
                }
            }, 500);
        }

        function hideLoading() {
            document.getElementById('loading-bar').style.width = '100%';
            clearInterval(msgInterval);
            clearInterval(barInterval);
            setTimeout(() => {
                document.getElementById('loading-overlay').style.display = 'none';
            }, 300);
        }

        document.querySelector('form').addEventListener('submit', function() {
            showLoading();
        });

        window.addEventListener('load', hideLoading);

        // ── Scan Notice ───────────────────────────────────────────
        function dismissNotice() {
            const notice = document.getElementById('scan-notice');
            if (notice) {
                notice.style.opacity = '0';
                notice.style.transform = 'translateX(100%)';
                setTimeout(() => notice.remove(), 300);
            }
        }

        function forceFreshScan() {
            const form  = document.querySelector('form');
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'force_rescan';
            input.value = '1';
            form.appendChild(input);
            showLoading();
            form.submit();
        }

        // Auto dismiss notice after 8 seconds
        setTimeout(() => dismissNotice(), 8000);
    </script>
</body>
</html>