<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPR Expiry Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        * { font-family: 'DM Sans', sans-serif; }
        code, .mono { font-family: 'DM Mono', monospace; }

        :root {
            --surface:    #ffffff;
            --surface-2:  #f8f9fb;
            --surface-3:  #f1f3f7;
            --border:     #e4e7ed;
            --text-1:     #0f1117;
            --text-2:     #4b5263;
            --text-3:     #8b94a6;
            --accent:     #2563eb;
            --accent-h:   #1d4ed8;
        }

        .dark {
            --surface:    #1a1f2e;
            --surface-2:  #222736;
            --surface-3:  #2a3042;
            --border:     #333d55;
            --text-1:     #d8dce8;
            --text-2:     #8d97ae;
            --text-3:     #5c6680;
            --accent:     #5b8dee;
            --accent-h:   #7aa3f5;
        }

        body {
            background-color: var(--surface-2);
            color: var(--text-1);
            min-height: 100vh;
        }

        /* ── Header bar ── */
        .app-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .app-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-1);
            letter-spacing: -0.3px;
        }

        .app-logo-icon {
            width: 28px;
            height: 28px;
            background: var(--accent);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* ── Cards / panels ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        /* ── Inputs ── */
        .field {
            width: 100%;
            padding: 9px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-1);
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .field:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .field::placeholder { color: var(--text-3); }

        /* ── Buttons ── */
        .btn-primary {
            background: var(--accent);
            color: #fff;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            white-space: nowrap;
        }
        .btn-primary:hover { background: var(--accent-h); }
        .btn-primary:active { transform: scale(0.98); }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-2);
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .btn-ghost:hover { background: var(--surface-3); color: var(--text-1); }

        /* ── Table ── */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            background: var(--surface-2);
            color: var(--text-3);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 10px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.1s;
        }
        .data-table tbody tr:hover { background: var(--surface-3); }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table td { padding: 12px 16px; font-size: 13.5px; color: var(--text-1); vertical-align: middle; }

        /* ── Status pills ── */
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .pill-valid        { background: #dcfce7; color: #15803d; }
        .pill-expiring     { background: #fef9c3; color: #a16207; }
        .pill-expired      { background: #fee2e2; color: #b91c1c; }
        .pill-unknown      { background: var(--surface-3); color: var(--text-2); }

        .dark .pill-valid    { background: #14532d; color: #86efac; }
        .dark .pill-expiring { background: #713f12; color: #fde68a; }
        .dark .pill-expired  { background: #7f1d1d; color: #fca5a5; }
        .dark .pill-unknown  { background: var(--surface-3); color: var(--text-2); }

        /* ── Summary cards ── */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
            width: 100%;
            text-align: left;
        }
        .stat-card:hover { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.08); }
        .stat-card.active { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.15); transform: translateY(-1px); }
        .stat-card .stat-value { font-size: 28px; font-weight: 700; line-height: 1; letter-spacing: -1px; }
        .stat-card .stat-label { font-size: 12px; font-weight: 500; color: var(--text-3); margin-top: 4px; letter-spacing: 0.03em; }
        .stat-card .stat-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }

        /* ── Dark toggle ── */
        .theme-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text-2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.15s;
        }
        .theme-btn:hover { background: var(--surface-3); }

        /* ── Edit link ── */
        .edit-link {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--accent);
            border: 1px solid var(--border);
            background: var(--surface);
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .edit-link:hover { background: var(--surface-3); border-color: var(--accent); }

        /* ── Pagination ── */
        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-2);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.12s, color 0.12s, border-color 0.12s;
        }
        .page-btn:hover { background: var(--surface-3); color: var(--text-1); }
        .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .page-btn.disabled { opacity: 0.35; cursor: not-allowed; }

        /* ── Loading overlay ── */
        #loading-overlay {
            background: rgba(15,17,23,0.7);
            backdrop-filter: blur(6px);
        }
        .dark #loading-overlay { background: rgba(10,12,18,0.8); }

        /* ── Modal ── */
        .modal-surface {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
        }

        /* ── Animations ── */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeSlideUp 0.3s ease forwards; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-3); }

        /* ── File subtext ── */
        .file-sub { font-size: 11.5px; color: var(--text-3); margin-top: 2px; font-family: 'DM Mono', monospace; }

        /* ── Search icon ── */
        .search-wrap { position: relative; }
        .search-wrap .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            pointer-events: none;
            font-size: 14px;
        }
        .search-wrap .field { padding-left: 36px; }

        /* ── Folder path row ── */
        .scan-row { display: flex; gap: 10px; align-items: center; }
        .scan-row .field { flex: 1; font-family: 'DM Mono', monospace; font-size: 13px; }

        /* ── Reg number mono ── */
        .reg-num { font-family: 'DM Mono', monospace; font-size: 12.5px; color: var(--text-2); }

        /* ── Days left ── */
        .days-ok  { color: #16a34a; font-weight: 500; }
        .days-warn { color: #d97706; font-weight: 500; }
        .days-bad  { color: #dc2626; font-weight: 500; }
        .dark .days-ok  { color: #4ade80; }
        .dark .days-warn { color: #fbbf24; }
        .dark .days-bad  { color: #f87171; }
    </style>
</head>
<body>

    {{-- ── Loading Overlay ── --}}
    <div id="loading-overlay" style="display:none;" class="fixed inset-0 z-50 flex flex-col items-center justify-center">
        <div class="text-center">
            <div class="text-5xl animate-bounce mb-5">💊</div>
            <p id="loading-msg" style="color:#9ba5bc; font-size:13px;" class="truncate w-72 mx-auto"></p>
        </div>
    </div>

    {{-- ── Re-scan Notice ── --}}
    @if(session('rescan_success'))
    <div id="rescan-notice" class="fixed top-4 right-4 z-40 panel px-5 py-4 flex items-start gap-3 animate-in" style="max-width:340px;">
        <span class="text-xl">✅</span>
        <div class="flex-1">
            <p style="font-size:13px;font-weight:600;color:var(--text-1);">Re-scan complete</p>
            <p style="font-size:12px;color:var(--text-3);margin-top:2px;">{{ session('rescan_success') }}</p>
        </div>
        <button onclick="dismissRescanNotice()" style="color:var(--text-3);font-size:18px;line-height:1;cursor:pointer;">×</button>
    </div>
    @endif

    {{-- ── Duplicates Modal ── --}}
    @if(isset($duplicates) && count($duplicates) > 0)
    <div id="duplicates-modal" class="fixed inset-0 z-40 flex items-center justify-center" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);">
        <div class="modal-surface w-full mx-4 flex flex-col animate-in" style="max-width:1100px;max-height:88vh;">

            <div class="modal-header">
                <h2 style="font-size:16px;font-weight:600;color:var(--text-1);">Previously Scanned Files Detected</h2>
                <p style="font-size:13px;color:var(--text-3);margin-top:4px;">
                    <span style="color:var(--accent);font-weight:600;">{{ count($duplicates) }} file(s)</span> loaded from cache instantly.
                    @if(isset($fromPdf) && $fromPdf > 0)
                        <span style="color:#16a34a;font-weight:600;">{{ $fromPdf }} new file(s)</span> freshly parsed.
                    @endif
                </p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px 24px;" class="shrink-0">
                <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(37,99,235,0.12);display:flex;align-items:center;justify-content:center;font-size:18px;">🗄️</div>
                    <div>
                        <div style="font-size:22px;font-weight:700;color:var(--accent);line-height:1;">{{ count($duplicates) }}</div>
                        <div style="font-size:11px;color:var(--text-3);margin-top:2px;">Loaded from Database</div>
                    </div>
                </div>
                <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;font-size:18px;">📄</div>
                    <div>
                        <div style="font-size:22px;font-weight:700;color:#16a34a;line-height:1;">{{ $fromPdf ?? 0 }}</div>
                        <div style="font-size:11px;color:var(--text-3);margin-top:2px;">Freshly Parsed</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-hidden flex flex-col" style="padding:0 24px 16px;">
                <table class="w-full text-sm table-fixed mb-0 data-table">
                    <thead>
                        <tr>
                            <th style="width:15rem;">File</th>
                            <th style="width:8rem;">Reg. Number</th>
                            <th style="width:10rem;">Brand Name</th>
                            <th style="width:12rem;">Generic Name</th>
                            <th style="width:9rem;">Expiry Date</th>
                            <th style="width:8rem;">Status</th>
                        </tr>
                    </thead>
                </table>
                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-sm table-fixed data-table">
                        <tbody>
                            @foreach($duplicates as $dup)
                                <tr>
                                    <td style="width:15rem;">
                                        <div style="font-weight:500;font-size:13px;">{{ $dup['normalized_filename'] ?? $dup['filename'] }}</div>
                                        <div class="file-sub">{{ $dup['filename'] }}</div>
                                    </td>
                                    <td style="width:8rem;" class="reg-num">{{ $dup['registration_number'] ?? 'N/A' }}</td>
                                    <td style="width:10rem;font-weight:600;">{{ $dup['brand_name'] ?? 'N/A' }}</td>
                                    <td style="width:12rem;color:var(--text-2);">{{ $dup['generic_name'] ?? 'N/A' }}</td>
                                    <td style="width:9rem;color:var(--text-2);">{{ $dup['expiry_date'] ? \Carbon\Carbon::parse($dup['expiry_date'])->format('M d, Y') : 'N/A' }}</td>
                                    <td style="width:8rem;">
                                        @php
                                            $dpill = match($dup['status'] ?? '') {
                                                'Valid'         => 'pill-valid',
                                                'Expiring Soon' => 'pill-expiring',
                                                'Expired'       => 'pill-expired',
                                                default         => 'pill-unknown',
                                            };
                                        @endphp
                                        <span class="pill {{ $dpill }}">{{ $dup['status'] ?? 'Unknown' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="modal-footer-default" class="modal-footer flex justify-end gap-2">
                <button onclick="showRescanConfirm()" class="btn-ghost">Update</button>
                <button onclick="closeDuplicatesModal()" class="btn-primary">Retain</button>
            </div>

            <div id="modal-footer-confirm" style="display:none;" class="modal-footer">
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;display:flex;gap:12px;align-items:flex-start;margin-bottom:14px;">
                    <span style="font-size:18px;">⚠️</span>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#b91c1c;">Re-scan all files?</p>
                        <p style="font-size:12px;color:#dc2626;margin-top:3px;">This will <strong>delete all cached records</strong> for this folder and re-parse every PDF from scratch. This cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button onclick="hideRescanConfirm()" class="btn-ghost">← Cancel</button>
                    <button onclick="forceFreshScan()" style="background:#dc2626;" class="btn-primary">Confirm Re-scan</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Success Toast ── --}}
    @if(session('success'))
    <div id="success-notice" style="position:fixed;top:68px;left:0;right:0;display:flex;justify-content:center;z-index:40;pointer-events:none;">
        <div style="background:var(--surface);border:1px solid #bbf7d0;border-radius:10px;padding:10px 20px;font-size:13px;font-weight:500;color:#15803d;box-shadow:0 4px 12px rgba(0,0,0,0.08);pointer-events:auto;" class="animate-in">
            {{ session('success') }}
        </div>
    </div>
    <script>
        setTimeout(() => {
            const n = document.getElementById('success-notice');
            if (n) { n.style.opacity = '0'; n.style.transition='opacity .3s'; setTimeout(() => n.remove(), 300); }
        }, 3000);
    </script>
    @endif

    {{-- ── Header ── --}}
    <header class="app-header">
        <div class="app-logo">
            <div class="app-logo-icon">💊</div>
            CPR Expiry Tracker
        </div>
        <button class="theme-btn" onclick="toggleDarkMode()" id="dark-toggle">🌙</button>
    </header>

    {{-- ── Main ── --}}
    <main style="max-width:1280px;margin:0 auto;padding:28px 24px;">

        {{-- Scan form --}}
        <form id="scan-form" action="{{ route('cpr.scan') }}" method="POST" class="panel" style="padding:20px 24px;margin-bottom:16px;">
            @csrf
            <label style="font-size:12px;font-weight:600;color:var(--text-3);letter-spacing:0.05em;text-transform:uppercase;display:block;margin-bottom:8px;">Folder Path</label>
            <div class="scan-row">
                <input
                    type="text"
                    name="folder_path"
                    value="{{ old('folder_path', $folderPath ?? '') }}"
                    placeholder="\\Kyle\bid docs  cpr  cgmp  br  product illustration etc"
                    class="field"
                >
                <button type="submit" class="btn-primary">Scan Folder</button>
            </div>
            @error('folder_path')
                <p style="color:#dc2626;font-size:12px;margin-top:8px;">{{ $message }}</p>
            @enderror
        </form>

        {{-- Search --}}
        <form id="search-form" action="{{ route('cpr.scan') }}" method="POST" class="panel" style="padding:12px 16px;margin-bottom:20px;">
            @csrf
            <input type="hidden" name="per_page" value="{{ $perPage ?? 10 }}">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="filter_status" value="{{ $filterStatus ?? '' }}">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input
                    id="search-input"
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    placeholder="Search by brand name…"
                    class="field"
                    autocomplete="off"
                >
            </div>
        </form>

        @if(count($results) > 0)

        {{-- Table --}}
        <div class="panel" style="overflow:hidden;margin-bottom:16px;">
            <table class="data-table table-fixed w-full">
                <thead>
                    <tr>
                        <th style="width:5rem;">Actions</th>
                        <th style="width:22rem;">File</th>
                        <th style="width:8rem;">Reg. No.</th>
                        <th style="width:9rem;">Brand</th>
                        <th style="width:12rem;">Generic</th>
                        <th style="width:8rem;">Expiry</th>
                        <th style="width:7rem;">Days Left</th>
                        <th style="width:8rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $cpr)
                    <tr onclick="window.open('{{ route('cpr.open', ['folder_path' => $folderPath, 'filename' => $cpr['filename']]) }}', '_blank')">
                        <td onclick="event.stopPropagation()">
                            <a href="{{ route('cpr.edit', ['id' => $cpr['id'], 'page' => $page, 'per_page' => $perPage]) }}" class="edit-link">Edit</a>
                        </td>
                        <td>
                            <div style="font-weight:500;font-size:13.5px;">{{ $cpr['normalized_filename'] ?? $cpr['filename'] }}</div>
                            <div class="file-sub">{{ $cpr['filename'] }}</div>
                        </td>
                        <td class="reg-num">{{ $cpr['registration_number'] ?? '—' }}</td>
                        <td style="font-weight:600;">{{ $cpr['brand_name'] ?? '—' }}</td>
                        <td style="color:var(--text-2);">{{ $cpr['generic_name'] ?? '—' }}</td>
                        <td style="color:var(--text-2);">{{ $cpr['expiry_date'] ? \Carbon\Carbon::parse($cpr['expiry_date'])->format('M d, Y') : '—' }}</td>
                        <td>
                            @if($cpr['days_remaining'] !== null)
                                @php
                                    $dc = $cpr['days_remaining'] < 0 ? 'days-bad' : ($cpr['days_remaining'] <= 90 ? 'days-warn' : 'days-ok');
                                @endphp
                                <span class="{{ $dc }}">{{ $cpr['days_remaining'] }}d</span>
                            @else
                                <span style="color:var(--text-3);">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $pill = match($cpr['status'] ?? '') {
                                    'Valid'         => 'pill-valid',
                                    'Expiring Soon' => 'pill-expiring',
                                    'Expired'       => 'pill-expired',
                                    default         => 'pill-unknown',
                                };
                            @endphp
                            <span class="pill {{ $pill }}">{{ $cpr['status'] ?? 'Unknown' }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination + rows per page --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:var(--text-3);">Rows</span>
                @foreach([10, 20, 30] as $size)
                    @php $isDisabled = $total < $size && $perPage != $size; @endphp
                    @if($perPage == $size)
                        <span class="page-btn active">{{ $size }}</span>
                    @elseif($isDisabled)
                        <span class="page-btn disabled">{{ $size }}</span>
                    @else
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="per_page" value="{{ $size }}">
                            <input type="hidden" name="page" value="1">
                            <input type="hidden" name="search" value="{{ $search ?? '' }}">
                            <button type="submit" class="page-btn">{{ $size }}</button>
                        </form>
                    @endif
                @endforeach
                <span style="font-size:12px;color:var(--text-3);margin-left:4px;">
                    {{ ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $total) }} of {{ $total }}
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:6px;">
                @if($page > 1)
                    <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <input type="hidden" name="page" value="{{ $page - 1 }}">
                        <input type="hidden" name="search" value="{{ $search ?? '' }}">
                        <button type="submit" class="page-btn">←</button>
                    </form>
                @endif

                @foreach(range(1, $lastPage) as $pageNum)
                    @if($page == $pageNum)
                        <span class="page-btn active">{{ $pageNum }}</span>
                    @else
                        <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="page" value="{{ $pageNum }}">
                            <input type="hidden" name="search" value="{{ $search ?? '' }}">
                            <button type="submit" class="page-btn">{{ $pageNum }}</button>
                        </form>
                    @endif
                @endforeach

                @if($page < $lastPage)
                    <form action="{{ route('cpr.scan') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <input type="hidden" name="page" value="{{ $page + 1 }}">
                        <input type="hidden" name="search" value="{{ $search ?? '' }}">
                        <button type="submit" class="page-btn">→</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Summary cards --}}
        @php
            $statCards = [
                ['key' => 'Valid',         'count' => $summaryValid,        'dot' => '#16a34a', 'val' => 'days-ok'],
                ['key' => 'Expiring Soon', 'count' => $summaryExpiringSoon, 'dot' => '#d97706', 'val' => 'days-warn'],
                ['key' => 'Expired',       'count' => $summaryExpired,      'dot' => '#dc2626', 'val' => 'days-bad'],
                ['key' => 'Unknown',       'count' => $summaryErrors,       'dot' => '#8b94a6', 'val' => ''],
            ];
        @endphp
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            @foreach($statCards as $s)
                @php $isActive = ($filterStatus ?? '') === $s['key']; @endphp
                <form action="{{ route('cpr.scan') }}" method="POST">
                    @csrf
                    <input type="hidden" name="per_page"      value="{{ $perPage }}">
                    <input type="hidden" name="page"          value="1">
                    <input type="hidden" name="filter_status" value="{{ $s['key'] }}">
                    <input type="hidden" name="search"        value="{{ $search ?? '' }}">
                    <button type="submit" class="stat-card {{ $isActive ? 'active' : '' }}">
                        <div class="stat-value {{ $s['val'] }} summary-count">{{ $s['count'] }}</div>
                        <div class="stat-label">
                            <span class="stat-dot" style="background:{{ $s['dot'] }};"></span>{{ $s['key'] }}
                        </div>
                    </button>
                </form>
            @endforeach
        </div>

        @elseif(isset($search) && $search)
            <div class="panel" style="padding:48px 24px;text-align:center;">
                <div style="font-size:36px;margin-bottom:12px;">🔍</div>
                <p style="font-weight:600;color:var(--text-1);font-size:15px;">No results for "{{ $search }}"</p>
                <p style="color:var(--text-3);font-size:13px;margin-top:4px;">Try a different brand name.</p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:16px;">
                @php $statCards = [['key'=>'Valid','count'=>$summaryValid,'dot'=>'#16a34a','val'=>'days-ok'],['key'=>'Expiring Soon','count'=>$summaryExpiringSoon,'dot'=>'#d97706','val'=>'days-warn'],['key'=>'Expired','count'=>$summaryExpired,'dot'=>'#dc2626','val'=>'days-bad'],['key'=>'Unknown','count'=>$summaryErrors,'dot'=>'#8b94a6','val'=>'']]; @endphp
                @foreach($statCards as $s)
                    @php $isActive = ($filterStatus ?? '') === $s['key']; @endphp
                    <form action="{{ route('cpr.scan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="per_page" value="{{ $perPage ?? 10 }}"><input type="hidden" name="page" value="1"><input type="hidden" name="filter_status" value="{{ $s['key'] }}"><input type="hidden" name="search" value="{{ $search ?? '' }}">
                        <button type="submit" class="stat-card {{ $isActive ? 'active' : '' }}">
                            <div class="stat-value {{ $s['val'] }} summary-count">{{ $s['count'] }}</div>
                            <div class="stat-label"><span class="stat-dot" style="background:{{ $s['dot'] }};"></span>{{ $s['key'] }}</div>
                        </button>
                    </form>
                @endforeach
            </div>

        @elseif(isset($filterStatus) && $filterStatus)
            <div class="panel" style="padding:48px 24px;text-align:center;">
                <div style="font-size:36px;margin-bottom:12px;">
                    @php echo match($filterStatus) { 'Valid'=>'✅','Expiring Soon'=>'⚠️','Expired'=>'❌',default=>'🔍' }; @endphp
                </div>
                <p style="font-weight:600;color:var(--text-1);font-size:15px;">
                    @php echo match($filterStatus) { 'Valid'=>'No valid CPRs found.','Expiring Soon'=>'No expiring soon CPRs found.','Expired'=>'No expired CPRs found.',default=>"No records for \"$filterStatus\"." }; @endphp
                </p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:16px;">
                @php $statCards = [['key'=>'Valid','count'=>$summaryValid,'dot'=>'#16a34a','val'=>'days-ok'],['key'=>'Expiring Soon','count'=>$summaryExpiringSoon,'dot'=>'#d97706','val'=>'days-warn'],['key'=>'Expired','count'=>$summaryExpired,'dot'=>'#dc2626','val'=>'days-bad'],['key'=>'Unknown','count'=>$summaryErrors,'dot'=>'#8b94a6','val'=>'']]; @endphp
                @foreach($statCards as $s)
                    @php $isActive = ($filterStatus ?? '') === $s['key']; @endphp
                    <form action="{{ route('cpr.scan') }}" method="POST">
                        @csrf
                        <input type="hidden" name="per_page" value="{{ $perPage ?? 10 }}"><input type="hidden" name="page" value="1"><input type="hidden" name="filter_status" value="{{ $s['key'] }}"><input type="hidden" name="search" value="{{ $search ?? '' }}">
                        <button type="submit" class="stat-card {{ $isActive ? 'active' : '' }}">
                            <div class="stat-value {{ $s['val'] }} summary-count">{{ $s['count'] }}</div>
                            <div class="stat-label"><span class="stat-dot" style="background:{{ $s['dot'] }};"></span>{{ $s['key'] }}</div>
                        </button>
                    </form>
                @endforeach
            </div>

        @elseif(isset($folderPath) && $folderPath)
            <div class="panel" style="padding:48px 24px;text-align:center;">
                <div style="font-size:36px;margin-bottom:12px;">📂</div>
                <p style="font-weight:600;color:var(--text-1);font-size:15px;">No PDF files found</p>
                <p style="color:var(--text-3);font-size:13px;margin-top:4px;">Check the folder path and try again.</p>
            </div>
        @endif

    </main>

    <script>
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

        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetch(`/search?search=${encodeURIComponent(this.value)}&per_page={{ $perPage ?? 10 }}`)
                        .then(r => r.json())
                        .then(data => {
                            updateTable(data.results);
                            updateCounts(data.counts);
                        });
                }, 400);
            });
        }

        function updateTable(results) {
            const tbody = document.querySelector('table tbody');
            if (!tbody) return;
            if (results.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="padding:32px;text-align:center;color:var(--text-3);font-size:13px;">No records found.</td></tr>`;
                return;
            }
            const pillClass = s => ({ 'Valid':'pill-valid','Expiring Soon':'pill-expiring','Expired':'pill-expired' }[s] ?? 'pill-unknown');
            const dayClass  = d => d === null ? '' : (d < 0 ? 'days-bad' : (d <= 90 ? 'days-warn' : 'days-ok'));
            tbody.innerHTML = results.map(cpr => {
                const expiry = cpr.expiry_date ? new Date(cpr.expiry_date).toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'}) : '—';
                const days   = cpr.days_remaining !== null ? `<span class="${dayClass(cpr.days_remaining)}">${cpr.days_remaining}d</span>` : `<span style="color:var(--text-3)">—</span>`;
                return `<tr>
                    <td><a href="/edit/${cpr.id}" class="edit-link">Edit</a></td>
                    <td><div style="font-weight:500;font-size:13.5px;">${cpr.normalized_filename ?? cpr.filename}</div><div class="file-sub">${cpr.filename}</div></td>
                    <td class="reg-num">${cpr.registration_number ?? '—'}</td>
                    <td style="font-weight:600;">${cpr.brand_name ?? '—'}</td>
                    <td style="color:var(--text-2);">${cpr.generic_name ?? '—'}</td>
                    <td style="color:var(--text-2);">${expiry}</td>
                    <td>${days}</td>
                    <td><span class="pill ${pillClass(cpr.status)}">${cpr.status ?? 'Unknown'}</span></td>
                </tr>`;
            }).join('');
        }

        function updateCounts(counts) {
            const cards = document.querySelectorAll('.summary-count');
            if (!cards.length) return;
            [counts.valid, counts.expiring, counts.expired, counts.errors].forEach((v,i) => { if(cards[i]) cards[i].textContent = v; });
        }

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
                    sseSource.close(); sseSource = null;
                    if (window._msgInterval) { clearInterval(window._msgInterval); window._msgInterval = null; }
                    document.getElementById('loading-msg').textContent = '⏳ Finalizing records, almost there...';
                }
            };
            sseSource.onerror = function () { if (sseSource) { sseSource.close(); sseSource = null; } };
        }

        function hideLoading() {
            if (window._msgInterval) { clearInterval(window._msgInterval); window._msgInterval = null; }
            if (sseSource) { sseSource.close(); sseSource = null; }
            setTimeout(() => { document.getElementById('loading-overlay').style.display = 'none'; }, 400);
        }

        window.addEventListener('load', hideLoading);

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function () {
                if (this.id === 'scan-form') {
                    const folderInput = document.querySelector('#scan-form input[name="folder_path"]');
                    showLoading(folderInput ? folderInput.value.trim() : null);
                }
            });
        });

        function closeDuplicatesModal() {
            const modal = document.getElementById('duplicates-modal');
            if (modal) { modal.style.opacity = '0'; modal.style.transition = 'opacity 0.2s'; setTimeout(() => modal.remove(), 200); }
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
            const form = document.getElementById('scan-form');
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'force_rescan'; input.value = '1';
            form.appendChild(input);
            closeDuplicatesModal();
            const folderInput = document.querySelector('#scan-form input[name="folder_path"]');
            showLoading(folderInput ? folderInput.value.trim() : null);
            form.submit();
        }
        function dismissRescanNotice() {
            const n = document.getElementById('rescan-notice');
            if (n) { n.style.opacity = '0'; n.style.transition = 'opacity .3s'; setTimeout(() => n.remove(), 300); }
        }
        if (document.getElementById('rescan-notice')) setTimeout(() => dismissRescanNotice(), 6000);
    </script>
</body>
</html>