<script setup lang="ts">
import { ref, computed } from 'vue';
import { Download, FileSpreadsheet, FileText, Settings2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';

interface Props {
    reportType: string;
    filters?: Record<string, unknown>;
    reportTitle?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    reportTitle: '',
    disabled: false,
});

const open = ref(false);

const exportTitle = ref(props.reportTitle || '');
const includeCoverPage = ref(true);
const includeFiltersSummary = ref(true);
const pdfOrientation = ref<'landscape' | 'portrait'>('landscape');

const buildExportUrl = (format: 'excel' | 'pdf'): string => {
    const params = new URLSearchParams();
    params.set('type', props.reportType);
    params.set('format', format);
    if (exportTitle.value) params.set('report_title', exportTitle.value);
    if (includeCoverPage.value) params.set('include_cover', '1');
    if (includeFiltersSummary.value) params.set('include_filters', '1');
    params.set('orientation', pdfOrientation.value);

    const appendFilters = (obj: Record<string, unknown>, prefix = '') => {
        for (const [key, value] of Object.entries(obj)) {
            const paramKey = prefix ? `${prefix}[${key}]` : key;
            if (Array.isArray(value)) {
                value.forEach((v, i) => params.set(`${paramKey}[${i}]`, String(v)));
            } else if (value !== null && value !== undefined && value !== '') {
                params.set(paramKey, String(value));
            }
        }
    };

    appendFilters(props.filters);

    return `/reports/export?${params.toString()}`;
};

const handleExport = (format: 'excel' | 'pdf') => {
    window.location.href = buildExportUrl(format);
    open.value = false;
};

const reportTypeLabel = computed(() => {
    const labels: Record<string, string> = {
        'records-inventory': 'Records Inventory',
        'summary': 'Summary Report',
        'storage-utilization': 'Storage Utilization',
        'document-access-frequency': 'Document Access Frequency',
        'retrieval-frequency': 'Document Access Frequency',
        'custom': 'Custom Report',
    };
    return labels[props.reportType] ?? props.reportType;
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                :disabled="props.disabled"
                class="gap-2"
                aria-label="Export report"
            >
                <Download class="h-4 w-4" />
                Export
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Settings2 class="h-4 w-4" />
                    </div>
                    <div>
                        <DialogTitle>Export Options</DialogTitle>
                        <DialogDescription>Configure your export for {{ reportTypeLabel }}</DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div class="flex flex-col gap-5 pt-1">

                <!-- Report title -->
                <div class="flex flex-col gap-1.5">
                    <Label class="text-sm font-medium">Report Title</Label>
                    <Input
                        v-model="exportTitle"
                        :placeholder="`${reportTypeLabel}…`"
                        class="text-sm"
                    />
                    <p class="text-[11px] text-muted-foreground">This title will appear on the cover page and in the Excel sheet name.</p>
                </div>

                <!-- PDF options -->
                <div class="flex flex-col gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">PDF Options</p>

                    <div class="flex items-center gap-2">
                        <Label class="text-sm font-medium shrink-0">Paper Orientation</Label>
                        <Select v-model="pdfOrientation">
                            <SelectTrigger class="h-8 w-36 text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="landscape">Landscape (wider)</SelectItem>
                                <SelectItem value="portrait">Portrait (taller)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Checkbox
                            id="include-cover"
                            :checked="includeCoverPage"
                            @update:checked="val => includeCoverPage = !!val"
                        />
                        <div>
                            <Label for="include-cover" class="text-sm font-medium cursor-pointer">Include Cover Page</Label>
                            <p class="text-[11px] text-muted-foreground">Adds a title page with agency name, date, and report details.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Checkbox
                            id="include-filters"
                            :checked="includeFiltersSummary"
                            @update:checked="val => includeFiltersSummary = !!val"
                        />
                        <div>
                            <Label for="include-filters" class="text-sm font-medium cursor-pointer">Include Filters Summary</Label>
                            <p class="text-[11px] text-muted-foreground">Lists the filters applied when this report was generated.</p>
                        </div>
                    </div>
                </div>

                <!-- Export buttons -->
                <div class="flex flex-col gap-2 border-t border-border/60 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1">Export As</p>
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            variant="outline"
                            class="gap-2 h-10"
                            @click="handleExport('excel')"
                        >
                            <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                            Excel (.xlsx)
                        </Button>
                        <Button
                            variant="outline"
                            class="gap-2 h-10"
                            @click="handleExport('pdf')"
                        >
                            <FileText class="h-4 w-4 text-rose-600" />
                            PDF (.pdf)
                        </Button>
                    </div>
                    <p class="text-[11px] text-muted-foreground/60">
                        Excel includes a "Report Info" summary sheet. PDF is formatted for printing.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
