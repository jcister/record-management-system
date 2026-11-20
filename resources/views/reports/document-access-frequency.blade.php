@extends('reports.layout')

@section('title', !empty($report_title) ? $report_title : 'Document Access Frequency Report')
@section('subtitle-text', 'Documents ranked by how often they are opened and accessed')

@section('content')

@if(!empty($summary_stats))
<table class="stat-table">
    <tr>
        <td>
            <span class="stat-label">Documents Tracked</span>
            <span class="stat-value">{{ number_format($summary_stats['total_files'] ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Total Opens</span>
            <span class="stat-value">{{ number_format($summary_stats['total_downloads'] ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Most Opened</span>
            <span class="stat-value">{{ number_format($summary_stats['max_downloads'] ?? 0) }}</span>
        </td>
    </tr>
</table>
@endif

<table class="data-table">
    <thead>
        <tr>
            <th style="width:40px;">Rank</th>
            <th>Record ID</th>
            <th>Document Name</th>
            <th>Division</th>
            <th style="text-align:right;">Times Opened</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows ?? [] as $index => $row)
        @php
            $rank = $index + 1;
            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
        @endphp
        <tr>
            <td style="font-weight:700;">{{ $medal ?: $rank }}</td>
            <td>{{ $row[0] }}</td>
            <td style="font-weight:600;">{{ $row[1] }}</td>
            <td>{{ $row[2] ?? '—' }}</td>
            <td style="text-align:right;font-weight:700;color:#1e40af;">{{ number_format($row[3]) }}</td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:16px;">No data found.</td></tr>
        @endforelse
    </tbody>
</table>

@endsection

