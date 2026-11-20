<script setup lang="ts">
import { computed } from 'vue';
import { Download, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Props {
    reportType: string;
    filters?: Record<string, unknown>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    disabled: false,
});

const buildExportUrl = (format: 'excel' | 'pdf'): string => {
    const params = new URLSearchParams();
    params.set('type', props.reportType);
    params.set('format', format);

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

const handleExcel = () => {
    window.location.href = buildExportUrl('excel');
};

const handlePdf = () => {
    window.location.href = buildExportUrl('pdf');
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
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
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem class="gap-2 cursor-pointer" @click="handleExcel">
                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                Export as Excel (.xlsx)
            </DropdownMenuItem>
            <DropdownMenuItem class="gap-2 cursor-pointer" @click="handlePdf">
                <FileText class="h-4 w-4 text-rose-600" />
                Export as PDF
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
