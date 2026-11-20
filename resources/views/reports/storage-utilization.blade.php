@extends('reports.layout')

@section('title', !empty($report_title) ? $report_title : 'Storage Utilization Report')
@section('subtitle-text', 'File size breakdown showing how storage is distributed across divisions and folders')

@section('content')

@if(!empty($total_size_formatted))
<table class="stat-table">
    <tr>
        <td>
            <span class="stat-label">Total Storage Used</span>
            <span class="stat-value">{{ $total_size_formatted }}</span>
        </td>
        <td>
            <span class="stat-label">Divisions Tracked</span>
            <span class="stat-value">{{ count($rows ?? []) }}</span>
        </td>
    </tr>
</table>
@endif

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ ucfirst($group_by_label ?? 'Group') }}</th>
            <th>Total Size</th>
            <th>Number of Files</th>
            <th style="text-align:right;">% of Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows ?? [] as $index => $row)
        @php
            $pct = is_array($row) ? ($row['percentage'] ?? $row[3] ?? 0) : 0;
            $label = is_array($row) ? ($row['label'] ?? $row[0] ?? '—') : $row;
            $size = is_array($row) ? ($row['total_size_formatted'] ?? $row[1] ?? '—') : '—';
            $count = is_array($row) ? ($row['file_count'] ?? $row[2] ?? 0) : 0;
        @endphp
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $label }}</td>
            <td>{{ $size }}</td>
            <td>{{ number_format($count) }}</td>
            <td style="text-align:right;">
                <div style="display:inline-block; vertical-align:middle; text-align:right;">
                    <div class="progress-wrap">
                        <div class="progress-bar" style="width:{{ min($pct, 100) }}%;"></div>
                    </div>
                    <span style="display:inline-block; width: 30px; text-align:right;">{{ $pct }}%</span>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">No data found.</td></tr>
        @endforelse
    </tbody>
</table>

@endsection
