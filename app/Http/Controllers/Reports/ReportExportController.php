<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\CustomReportExport;
use App\Exports\Reports\DocumentAccessFrequencyExport;
use App\Exports\Reports\RecordsInventoryExport;
use App\Exports\Reports\StorageUtilizationExport;
use App\Exports\Reports\SummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Models\Department;
use App\Models\SystemConfiguration;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function __invoke(ReportExportRequest $request): BinaryFileResponse|StreamedResponse|Response
    {
        $type = $request->input('type');
        $format = $request->input('format');
        $filters = $request->except(['type', 'format', 'report_title', 'include_cover', 'include_filters', 'orientation']);

        $this->audit->log(
            'report_exported',
            $request->user()->name.' exported a '.str($type)->replace('-', ' ')->title().' report as '.strtoupper($format).'.',
            properties: [
                'report_type' => $type,
                'format' => $format,
            ]
        );

        if ($format === 'excel') {
            return $this->exportExcel($type, $filters);
        }

        return $this->exportPdf($type, $filters, $request);
    }

    private function exportExcel(string $type, array $filters): BinaryFileResponse
    {
        $export = match ($type) {
            'records-inventory' => new RecordsInventoryExport($filters),
            'summary' => new SummaryExport($filters),
            'storage-utilization' => new StorageUtilizationExport($filters),
            'document-access-frequency' => new DocumentAccessFrequencyExport($filters),
            'retrieval-frequency' => new DocumentAccessFrequencyExport($filters),
            'custom' => new CustomReportExport($filters),
            default => new RecordsInventoryExport($filters),
        };

        $filename = str($type)->replace('-', '_').'_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    private function exportPdf(string $type, array $filters, ReportExportRequest $request): Response
    {
        $view = "reports.{$type}";
        $data = $this->getDataForPdf($type, $filters);

        $orientation = $request->input('orientation', 'landscape');
        $config = SystemConfiguration::getInstance();

        // Merge export options into view data
        $data['orientation'] = $orientation;
        $data['agency_name'] = $config->agency_name;
        $data['report_title'] = $request->input('report_title', '');
        $data['include_cover'] = (bool) $request->input('include_cover', false);
        $data['include_filters'] = (bool) $request->input('include_filters', false);
        $data['filter_labels'] = $this->buildFilterLabels($type, $filters);

        $pdf = Pdf::loadView($view, $data)->setPaper('a4', $orientation);

        $filename = str($type)->replace('-', '_').'_'.now()->format('Ymd_His').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function getDataForPdf(string $type, array $filters): array
    {
        return match ($type) {
            'records-inventory' => (new RecordsInventoryExport($filters))->getPdfData(),
            'summary' => (new SummaryExport($filters))->getPdfData(),
            'storage-utilization' => (new StorageUtilizationExport($filters))->getPdfData(),
            'document-access-frequency' => (new DocumentAccessFrequencyExport($filters))->getPdfData(),
            'retrieval-frequency' => (new DocumentAccessFrequencyExport($filters))->getPdfData(),
            'custom' => (new CustomReportExport($filters))->getPdfData(),
            default => [],
        };
    }

    /**
     * Build a human-readable list of active filters for the PDF filters summary block.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function buildFilterLabels(string $type, array $filters): array
    {
        $labels = [];

        if (! empty($filters['department_id'])) {
            $dept = Department::find($filters['department_id']);
            $labels['Division'] = $dept?->name ?? 'ID '.$filters['department_id'];
        }

        if (! empty($filters['status'])) {
            $labels['Status'] = ucwords(str_replace('_', ' ', $filters['status']));
        }

        if (! empty($filters['retention_status'])) {
            $map = [
                'active' => 'ACTIVE',
                'archived' => 'ACTIVE',
                'ready_for_disposal' => 'READY TO BE DISPOSED',
                'disposed' => 'DISPOSED',
            ];
            $labels['Lifecycle Stage'] = $map[$filters['retention_status']] ?? ucwords(str_replace('_', ' ', $filters['retention_status']));
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = $filters['date_from'] ?? '—';
            $to = $filters['date_to'] ?? '—';
            $labels['Date Range'] = "{$from} to {$to}";
        }

        if (! empty($filters['period_from']) || ! empty($filters['period_to'])) {
            $from = $filters['period_from'] ?? '—';
            $to = $filters['period_to'] ?? '—';
            $labels['Period'] = "{$from} to {$to}";
        }

        if (! empty($filters['search'])) {
            $labels['Search'] = '"'.$filters['search'].'"';
        }

        if (! empty($filters['top_n'])) {
            $labels['Top Results'] = 'Top '.$filters['top_n'];
        }

        if (! empty($filters['source'])) {
            $sourceMap = [
                'files' => 'Files & Documents',
                'requests' => 'Retrieval Requests',
                'users' => 'Users',
                'disposal_batches' => 'Disposal Batches',
                'audit_logs' => 'Audit Logs',
            ];
            $labels['Data Source'] = $sourceMap[$filters['source']] ?? ucwords($filters['source']);
        }

        return $labels;
    }
}
