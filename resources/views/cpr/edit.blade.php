<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit CPR Record</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
    </script>

    <style>
        * { font-family: Arial, sans-serif; }

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
            text-decoration: none;
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

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

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
            font-family: Arial, sans-serif;
        }
        .field:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .field::placeholder { color: var(--text-3); }

        .field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-3);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        .btn-primary:hover { background: var(--accent-h); }
        .btn-primary:active { transform: scale(0.98); }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-2);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            width: 100%;
            text-align: center;
            text-decoration: none;
            display: block;
            font-family: Arial, sans-serif;
        }
        .btn-ghost:hover { background: var(--surface-3); color: var(--text-1); }

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

        .file-info {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
        }

        .error-msg { color: #dc2626; font-size: 12px; margin-top: 5px; }
        .dark .error-msg { color: #f87171; }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeSlideUp 0.3s ease forwards; }
    </style>
</head>
<body>

    {{-- ── Header ── --}}
    <header class="app-header">
        <a href="{{ route('cpr.results') }}" class="app-logo">
            <div class="app-logo-icon">💊</div>
            CPR Expiry Tracker
        </a>
        <button class="theme-btn" onclick="toggleDarkMode()" id="dark-toggle">🌙</button>
    </header>

    {{-- ── Main ── --}}
    <main style="max-width:560px;margin:0 auto;padding:32px 24px;">

        {{-- Breadcrumb --}}
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
            <a href="{{ route('cpr.results') }}" style="font-size:13px;color:var(--text-3);text-decoration:none;transition:color .15s;" onmouseover="this.style.color='var(--text-1)'" onmouseout="this.style.color='var(--text-3)'">Results</a>
            <span style="color:var(--text-3);font-size:13px;">›</span>
            <span style="font-size:13px;color:var(--text-2);">Edit Record</span>
        </div>

        <div class="panel animate-in" style="padding:24px;">

            {{-- File info --}}
            <div class="file-info" style="margin-bottom:24px;">
                <p style="font-size:11px;font-weight:600;color:var(--text-3);letter-spacing:0.05em;text-transform:uppercase;margin-bottom:4px;">File</p>
                <p style="font-size:13px;color:var(--text-2);word-break:break-all;font-family:monospace;">{{ $cpr->filename }}</p>
            </div>

            <form action="{{ route('cpr.update', $cpr->id) }}" method="POST">
                @csrf
                <input type="hidden" name="page" value="{{ request('page', 1) }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">

                {{-- Registration Number --}}
                <div style="margin-bottom:16px;">
                    <label class="field-label">Registration Number</label>
                    <input type="text" name="registration_number"
                        value="{{ old('registration_number', $cpr->registration_number) }}"
                        class="field">
                    @error('registration_number')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Brand Name --}}
                <div style="margin-bottom:16px;">
                    <label class="field-label">Brand Name</label>
                    <input type="text" name="brand_name"
                        value="{{ old('brand_name', $cpr->brand_name) }}"
                        class="field">
                    @error('brand_name')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Generic Name --}}
                <div style="margin-bottom:16px;">
                    <label class="field-label">Generic Name</label>
                    <input type="text" name="generic_name"
                        value="{{ old('generic_name', $cpr->generic_name) }}"
                        class="field">
                    @error('generic_name')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Expiry Date --}}
                <div style="margin-bottom:28px;">
                    <label class="field-label">Expiry Date</label>
                    <input type="date" name="expiry_date"
                        value="{{ old('expiry_date', $cpr->expiry_date ? \Carbon\Carbon::parse($cpr->expiry_date)->format('Y-m-d') : '') }}"
                        class="field">
                    @error('expiry_date')
                        <p class="error-msg">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Actions --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <a href="{{ route('cpr.edit.cancel') }}" class="btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
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
    </script>
</body>
</html>