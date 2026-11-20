@extends('reports.layout')

@section('title', !empty($report_title) ? $report_title : 'Custom Report')
@section('subtitle-text', 'Custom report generated using the Report Builder')
@section('subtitle')
    <p style="color:#64748b;font-size:9px;margin-top:2px;">
        {{ count($columns ?? []) }} field(s) &nbsp;|&nbsp; {{ count($rows ?? []) }} row(s) shown
    </p>
@endsection

@section('content')

<table class="data-table">
    <thead>
        <tr>
            @foreach($columns ?? [] as $col)
            <th>{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows ?? [] as $row)
        <tr>
            @foreach($columns ?? [] as $col)
            <td>{{ $row[$col['key']] ?? '' }}</td>
            @endforeach
        </tr>
        @empty
        <tr>
            <td colspan="{{ count($columns ?? [1]) }}" style="text-align:center;color:#94a3b8;padding:16px;">
                No results found. Try adjusting your filters.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

@endsection
