@extends('reports.layout')

@section('title', !empty($report_title) ? $report_title : 'Summary Report')
@section('subtitle-text', 'High-level overview of document activity and compliance')
@section('subtitle')
    <p style="color:#64748b;font-size:9px;margin-top:2px;">Period: {{ $period_from ?? 'Start' }} – {{ $period_to ?? 'Now' }}</p>
@endsection

@section('content')

<p class="section-title">At a Glance</p>
<table class="stat-table">
    <tr>
        <td>
            <span class="stat-label">Total Documents</span>
            <span class="stat-value">{{ number_format($total_files ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Official Records</span>
            <span class="stat-value">{{ number_format($total_records ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Added in Period</span>
            <span class="stat-value">{{ number_format($added_in_period ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Removed in Period</span>
            <span class="stat-value">{{ number_format($disposed_in_period ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">Retrieval Requests</span>
            <span class="stat-value">{{ number_format($requests_in_period ?? 0) }}</span>
        </td>
    </tr>
</table>

<p class="section-title">Records Health</p>
<table class="stat-table">
    <tr>
        <td>
            <span class="stat-label">ACTIVE</span>
            <span class="stat-value green">{{ number_format($active ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">READY TO BE DISPOSED</span>
            <span class="stat-value amber">{{ number_format($ready_for_disposal ?? 0) }}</span>
        </td>
        <td>
            <span class="stat-label">DISPOSED</span>
            <span class="stat-value red">{{ number_format($disposed ?? 0) }}</span>
        </td>
    </tr>
</table>

@if(!empty($by_department) && count($by_department))
<p class="section-title">Documents by Division</p>
<table class="data-table">
    <thead>
        <tr>
            <th>Division</th>
            <th style="text-align:right;">Document Count</th>
        </tr>
    </thead>
    <tbody>
        @foreach($by_department as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td style="text-align:right;font-weight:600;">{{ number_format($row['count']) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

@endsection
