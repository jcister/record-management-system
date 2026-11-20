@extends('reports.layout')

@section('title', !empty($report_title) ? $report_title : 'Records Inventory')
@section('subtitle-text', 'Complete list of documents with status and retention information')
@section('subtitle')
    <p style="color:#64748b;font-size:9px;margin-top:2px;">
        @if(!empty($filters['department_id']))Division filter applied &nbsp;|&nbsp; @endif
        @if(!empty($filters['date_from']))Date: {{ $filters['date_from'] }} – {{ $filters['date_to'] ?? 'now' }}@endif
    </p>
@endsection

@section('content')

@if(!empty($summary_stats))
<table class="stat-table">
    <tr>
        <td>
            <span class="stat-label">Total Documents</span>
            <span class="stat-value">{{ number_format($summary_stats['total'] ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Active</span>
            <span class="stat-value green">{{ number_format($summary_stats['active'] ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Ready to Remove</span>
            <span class="stat-value amber">{{ number_format($summary_stats['ready_for_disposal'] ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Removed</span>
            <span class="stat-value red">{{ number_format($summary_stats['disposed'] ?? 0) }}</span>
        </td>
    </tr>
</table>
@endif

<table class="data-table">
    <thead>
        <tr>
            <th>Record ID</th>
            <th>Document Name</th>
            <th>Author</th>
            <th>Division</th>
            <th>Status</th>
            <th>Lifecycle Stage</th>
            <th>Created</th>
            <th>Deadline</th>
        </tr>
    </thead>
    <tbody>
        @forelse($records ?? [] as $r)
        @php
            $retentionMap = [
                'active' => 'ACTIVE',
                'archived' => 'ACTIVE',
                'ready_for_disposal' => 'READY TO BE DISPOSED',
                'disposed' => 'DISPOSED',
            ];
            $retentionLabel = $retentionMap[$r['retention_status'] ?? ''] ?? ucwords(str_replace('_', ' ', $r['retention_status'] ?? '—'));
            $statusClass = match(strtolower($r['status'] ?? '')) {
                'approved' => 'badge-green',
                'pending' => 'badge-yellow',
                'rejected' => 'badge-red',
                default => 'badge-gray',
            };
            $retentionClass = match(strtolower($r['retention_status'] ?? '')) {
                'active' => 'badge-green',
                'archived' => 'badge-green',
                'ready_for_disposal' => 'badge-orange',
                'disposed' => 'badge-red',
                default => 'badge-gray',
            };
        @endphp
        <tr>
            <td>{{ $r['record_id'] }}</td>
            <td>{{ $r['file_name'] }}</td>
            <td>{{ $r['author'] ?? '—' }}</td>
            <td>{{ $r['department'] ?? '—' }}</td>
            <td><span class="badge {{ $statusClass }}">{{ ucfirst($r['status'] ?? '—') }}</span></td>
            <td><span class="badge {{ $retentionClass }}">{{ $retentionLabel }}</span></td>
            <td>{{ $r['date_created'] ?? '—' }}</td>
            <td>{{ $r['retention_expires_at'] ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:16px;">No documents found.</td></tr>
        @endforelse
    </tbody>
</table>

@endsection
