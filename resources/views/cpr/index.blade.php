<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPR Expiry Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">
            📋 CPR Expiry Tracker
        </h1>

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
                        value="{{ $folder_path ?? '' }}"
                        placeholder="/path/to/cpr/folder"
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

        @elseif(isset($folder_path) && $folder_path)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <p class="text-yellow-800">No PDF files found in the specified folder.</p>
            </div>
        @endif
    </div>
</body>
</html>