<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>@yield('title', 'Report')</title>
    <style>
        @page { size: A4 {{ $orientation ?? 'landscape' }}; margin: 40px 40px; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #334155; background: #ffffff; text-align: left; line-height: 1.4; padding: 10px 20px; margin: 0; }

        /* ── Core Layout ── */
        .w-full { width: 100%; }
        .align-top { vertical-align: top; }
        .align-bottom { vertical-align: bottom; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }

        /* ── Cover page ─────────────────────────────────────────── */
        .cover-page {
            page-break-after: always;
            padding: 60px 20px;
        }
        .cover-agency { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 40px; }
        .cover-divider { width: 50px; height: 3px; background: #0f172a; margin-bottom: 30px; border-radius: 2px; }
        .cover-title { font-size: 32px; font-weight: bold; color: #0f172a; line-height: 1.2; margin-bottom: 10px; }
        .cover-subtitle { font-size: 14px; color: #64748b; margin-bottom: 60px; }
        .cover-confidential {
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: auto; /* Push meta table down if possible, but dompdf handles this differently */
        }

        .cover-meta-table { width: 100%; border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 80px; }
        .cover-meta-table td { vertical-align: top; padding-right: 40px; width: 33%; }
        .cover-meta-label { display: block; font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
        .cover-meta-value { display: block; font-size: 12px; color: #0f172a; font-weight: bold; }

        /* ── Filters summary ─────────────────────────────────────── */
        .filters-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .filters-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-bottom: 8px; }
        table.filters-table { width: 100%; border: none; margin-top: 0; }
        table.filters-table td { padding: 4px 8px 4px 0; border: none; font-size: 10px; vertical-align: top; }
        .filter-key { color: #94a3b8; font-weight: normal; }
        .filter-value { color: #0f172a; font-weight: bold; }
        .filters-none { font-size: 10px; color: #94a3b8; font-style: italic; }

        /* ── Page header ─────────────────────────────────────────── */
        .page-header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; width: 100%; page-break-inside: avoid; }
        .page-header h1 { font-size: 18px; font-weight: bold; color: #0f172a; margin: 0; padding: 0; }
        .page-header .meta { font-size: 9px; color: #64748b; text-align: right; line-height: 1.3; }
        .header-table { width: 100%; border: none; margin: 0; }
        .header-table td { padding: 0; border: none; vertical-align: bottom; }

        /* ── Regular Tables ───────────────────────────────────────────────── */
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; page-break-inside: auto; }
        table.data-table thead { display: table-header-group; }
        table.data-table tr { page-break-inside: avoid; }
        table.data-table th { background-color: #f1f5f9; color: #334155; font-weight: bold; padding: 8px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; border-top: 1px solid #e2e8f0; }
        table.data-table tbody tr:nth-child(even) { background-color: #fcfcfd; }
        table.data-table tbody td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; color: #0f172a; vertical-align: middle; }

        /* ── Badges ──────────────────────────────────────────────── */
        .badge { display: inline-block; padding: 3px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.02em; }
        .badge-green { background-color: #dcfce7; color: #15803d; }
        .badge-yellow { background-color: #fef9c3; color: #a16207; }
        .badge-orange { background-color: #ffedd5; color: #c2410c; }
        .badge-red { background-color: #fee2e2; color: #b91c1c; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }

        /* ── Stat grid via Table ───────────────────────────────────────────── */
        .section-title { font-size: 12px; font-weight: bold; color: #0f172a; margin: 24px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table.stat-table { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin: 0 -12px 24px -12px; table-layout: fixed; page-break-inside: avoid; }
        table.stat-table td { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; background-color: #ffffff; vertical-align: top; }
        .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: block; }
        .stat-value { font-size: 20px; font-weight: bold; color: #0f172a; display: block; }
        .stat-value.red { color: #dc2626; }
        .stat-value.amber { color: #d97706; }
        .stat-value.green { color: #16a34a; }

        /* ── Progress bar for dompdf (inline blocks hack) ────────────────── */
        .progress-wrap { background-color: #e2e8f0; border-radius: 3px; height: 6px; width: 60px; display: inline-block; position: relative; margin-right: 6px; vertical-align: middle; }
        .progress-bar { background-color: #2563eb; border-radius: 3px; height: 6px; display: inline-block; position: absolute; left: 0; top: 0; }

        /* ── Footer ──────────────────────────────────────────────── */
        .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 9px; text-align: center; page-break-inside: avoid; }
    </style>
</head>
<body>

    {{-- ── Cover page (optional) ── --}}
    @if(!empty($include_cover))
    <div class="cover-page">
        <div class="cover-agency">{{ $agency_name ?? 'Records Management System' }}</div>
        <div class="cover-divider"></div>
        <div class="cover-title">@yield('title', 'Report')</div>
        <div class="cover-subtitle">@yield('subtitle-text', 'Automatically generated report')</div>
        <div class="cover-confidential">Confidential — For Official Use Only</div>

        <table class="cover-meta-table">
            <tr>
                <td>
                    <span class="cover-meta-label">Generated On</span>
                    <span class="cover-meta-value">{{ now()->format('F j, Y') }}</span>
                </td>
                <td>
                    <span class="cover-meta-label">Time</span>
                    <span class="cover-meta-value">{{ now()->format('g:i A') }}</span>
                </td>
                @if(!empty($report_title))
                <td>
                    <span class="cover-meta-label">Report Name</span>
                    <span class="cover-meta-value">{{ $report_title }}</span>
                </td>
                @endif
            </tr>
        </table>
    </div>
    @endif

    {{-- ── Page header ── --}}
    <div class="page-header">
        <table class="header-table">
            <tr>
                <td class="text-left align-bottom">
                    <h1>@yield('title', 'Report')</h1>
                    @yield('subtitle')
                </td>
                <td class="meta align-bottom">
                    Generated: {{ now()->format('M j, Y  g:i A') }}<br />
                    {{ $agency_name ?? 'Record Management System' }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Filters summary (optional) ── --}}
    @if(!empty($include_filters) && !empty($filter_labels))
    <div class="filters-section">
        <div class="filters-title">Filters Applied</div>
        @if(count($filter_labels) > 0)
            <table class="filters-table">
                <tr>
                    @foreach($filter_labels as $key => $value)
                    <td>
                        <span class="filter-key">{{ $key }}:</span>
                        <span class="filter-value">{{ $value }}</span>
                    </td>
                    @endforeach
                </tr>
            </table>
        @else
            <p class="filters-none">No filters applied — showing all data.</p>
        @endif
    </div>
    @endif

    @yield('content')

    <div class="footer">
        This report was automatically generated by the Records Management System. For official use only.
    </div>
</body>
</html>
