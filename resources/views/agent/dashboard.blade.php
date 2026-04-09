@extends('layouts.dashboard')

@section('title', 'Agent Dashboard')
@section('page-title', 'Delivery Dashboard')
@section('page-subtitle', 'Your assigned shipments and delivery queue')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
    @foreach([
        ['label' => 'Total Assigned',     'value' => $stats['total'],            'color' => 'violet'],
        ['label' => 'Pending',            'value' => $stats['pending'],           'color' => 'yellow'],
        ['label' => 'In Transit',         'value' => $stats['in_transit'],        'color' => 'blue'],
        ['label' => 'Out for Delivery',   'value' => $stats['out_for_delivery'],  'color' => 'purple'],
        ['label' => 'Delivered Today',    'value' => $stats['delivered_today'],   'color' => 'emerald'],
    ] as $stat)
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 hover:border-{{ $stat['color'] }}-600/40 transition-colors">
        <p class="text-2xl font-bold text-white">{{ $stat['value'] }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- Shipment Queue --}}
<div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-base font-semibold text-white">Delivery Queue</h3>
        <a href="{{ route('agent.shipments.index') }}"
           class="text-xs text-violet-400 hover:text-violet-300 transition-colors">View all →</a>
    </div>

    @if($shipments->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-gray-600">
            <svg class="w-12 h-12 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm">No assigned shipments</p>
        </div>
    @else
    <div class="overflow-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-gray-800">
                    <th class="pb-3 text-xs font-medium text-gray-500">Tracking #</th>
                    <th class="pb-3 text-xs font-medium text-gray-500">Sender</th>
                    <th class="pb-3 text-xs font-medium text-gray-500">Recipient</th>
                    <th class="pb-3 text-xs font-medium text-gray-500">Destination</th>
                    <th class="pb-3 text-xs font-medium text-gray-500">Status</th>
                    <th class="pb-3 text-xs font-medium text-gray-500">ETA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($shipments->take(10) as $shipment)
                <tr class="hover:bg-gray-800/50 transition-colors">
                    <td class="py-3 font-mono text-violet-400 text-xs">{{ $shipment->tracking_number }}</td>
                    <td class="py-3 text-gray-300">{{ $shipment->sender->name ?? '—' }}</td>
                    <td class="py-3 text-gray-300">{{ $shipment->receiver_name }}</td>
                    <td class="py-3 text-gray-400 text-xs">{{ $shipment->receiver_city }}, {{ $shipment->receiver_country }}</td>
                    <td class="py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            @if($shipment->status === 'delivered') bg-emerald-900/40 text-emerald-400
                            @elseif($shipment->status === 'in_transit') bg-blue-900/40 text-blue-400
                            @elseif($shipment->status === 'out_for_delivery') bg-purple-900/40 text-purple-400
                            @elseif($shipment->status === 'pending') bg-yellow-900/40 text-yellow-400
                            @else bg-gray-800 text-gray-400
                            @endif">
                            {{ $shipment->status_label }}
                        </span>
                    </td>
                    <td class="py-3 text-gray-500 text-xs">
                        {{ $shipment->estimated_delivery?->format('M d, Y') ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
