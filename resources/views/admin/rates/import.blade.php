@extends('layouts.dashboard')

@section('title', 'Rate Import')
@section('page-title', 'Monthly Rate Upload')
@section('page-subtitle', 'Upload DHL and Master files and process automatically')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white">One-click monthly update</h2>
        <p class="text-sm text-gray-400 mt-1">
            Upload the DHL zone list, DHL rate sheet, and Master sheet. The system will parse, validate, map countries, and update rates automatically.
        </p>

        <form action="{{ route('admin.rates.import.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="dhl_zone_file" class="block text-sm font-medium text-gray-300">DHL Bangladesh Zone List</label>
                <input
                    id="dhl_zone_file"
                    name="dhl_zone_file"
                    type="file"
                    accept=".xlsx"
                    required
                    class="mt-2 block w-full rounded-xl border border-gray-700 bg-gray-800 text-gray-200 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-emerald-500"
                >
                @error('dhl_zone_file')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="dhl_file" class="block text-sm font-medium text-gray-300">DHL Bangladesh Rate File</label>
                <input
                    id="dhl_file"
                    name="dhl_file"
                    type="file"
                    accept=".xlsx"
                    required
                    class="mt-2 block w-full rounded-xl border border-gray-700 bg-gray-800 text-gray-200 file:mr-4 file:rounded-lg file:border-0 file:bg-violet-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-violet-500"
                >
                @error('dhl_file')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="master_file" class="block text-sm font-medium text-gray-300">Master Air Agent Rate File</label>
                <input
                    id="master_file"
                    name="master_file"
                    type="file"
                    accept=".xlsx"
                    required
                    class="mt-2 block w-full rounded-xl border border-gray-700 bg-gray-800 text-gray-200 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-500"
                >
                @error('master_file')
                    <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 transition-colors"
            >
                Process Rates
            </button>
        </form>
    </div>

    @if(session('import_summary'))
        @php($summary = session('import_summary'))
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <h3 class="text-base font-semibold text-white">Import Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm">
                <div class="rounded-xl border border-gray-800 p-4 bg-gray-950/50">
                    <p class="text-gray-400">DHL zones inserted</p>
                    <p class="text-xl font-bold text-white">{{ $summary['dhl_zones']['inserted'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-800 p-4 bg-gray-950/50">
                    <p class="text-gray-400">DHL deleted old rows</p>
                    <p class="text-xl font-bold text-white">{{ $summary['dhl']['deleted'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-800 p-4 bg-gray-950/50">
                    <p class="text-gray-400">DHL inserted rows</p>
                    <p class="text-xl font-bold text-white">{{ $summary['dhl']['inserted'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-800 p-4 bg-gray-950/50">
                    <p class="text-gray-400">Master deleted old rows</p>
                    <p class="text-xl font-bold text-white">{{ $summary['master']['deleted'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl border border-gray-800 p-4 bg-gray-950/50">
                    <p class="text-gray-400">Master inserted rows</p>
                    <p class="text-xl font-bold text-white">{{ $summary['master']['inserted'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
