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
    .dark body             { background-color: #111827; color: #f9fafb; }
    .dark .bg-white        { background-color: #1f2937 !important; }
    .dark .bg-gray-100     { background-color: #111827 !important; }
    .dark .bg-gray-50      { background-color: #374151 !important; }
    .dark .text-gray-800   { color: #f9fafb !important; }
    .dark .text-gray-700   { color: #e5e7eb !important; }
    .dark .text-gray-600   { color: #d1d5db !important; }
    .dark .text-gray-500   { color: #9ca3af !important; }
    .dark .border-gray-300 { border-color: #4b5563 !important; }
    .dark .text-blue-600   { color: #93c5fd !important; }
    .dark .text-red-500    { color: #fca5a5 !important; }
    .dark input            { background-color: #374151 !important; color: #f9fafb !important; border-color: #4b5563 !important; }
</style>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto">

        <div class="flex items-center gap-4 mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Edit CPR Record</h1>
        </div>

        <!-- {{-- Success Message --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif -->

        <div class="bg-white rounded-lg shadow p-6">

            {{-- File Info --}}
            <div class="mb-6 p-3 bg-gray-50 rounded-lg">
                <p class="text-xs text-gray-500 font-medium">File</p>
                <p class="text-sm text-gray-700">{{ $cpr->filename }}</p>
            </div>

            <form action="{{ route('cpr.update', $cpr->id) }}" method="POST">
                @csrf
                <input type="hidden" name="page" value="{{ request('page', 1) }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                {{-- Registration Number --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Registration Number</label>
                    <input type="text" name="registration_number"
                        value="{{ old('registration_number', $cpr->registration_number) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('registration_number')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Brand Name --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand Name</label>
                    <input type="text" name="brand_name"
                        value="{{ old('brand_name', $cpr->brand_name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('brand_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Generic Name --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Generic Name</label>
                    <input type="text" name="generic_name"
                        value="{{ old('generic_name', $cpr->generic_name) }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('generic_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Expiry Date --}}
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                    <input type="date" name="expiry_date"
                        value="{{ old('expiry_date', $cpr->expiry_date ? \Carbon\Carbon::parse($cpr->expiry_date)->format('Y-m-d') : '') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('expiry_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Actions --}}
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                        Save Changes
                    </button>
                    <a href="{{ route('cpr.edit.cancel') }}"
                        class="flex-1 px-4 py-2 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition text-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>