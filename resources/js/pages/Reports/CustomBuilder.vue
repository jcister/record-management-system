<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { preview as previewRoute } from '@/routes/reports/custom';
import { ChevronRight, FileText, Pencil, Play, Wand2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import ExportOptionsDialog from '@/components/reports/ExportOptionsDialog.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import ColumnList from '@/components/reports/Builder/ColumnList.vue';
import SelectedColumns from '@/components/reports/Builder/SelectedColumns.vue';
import ConditionRow from '@/components/reports/Builder/ConditionRow.vue';
import GroupSortControls from '@/components/reports/Builder/GroupSortControls.vue';
import VisualizationToggle from '@/components/reports/Builder/VisualizationToggle.vue';
import AggregationSelector from '@/components/reports/Builder/AggregationSelector.vue';
import SavedTemplates from '@/components/reports/Builder/SavedTemplates.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface FieldDef { label: string; type: string; options?: { value: string; label: string }[] }
interface Condition { field: string; operator: string; value: string }
interface Column { key: string; label: string; type: string }
type SourceKey = 'files' | 'requests' | 'users' | 'disposal_batches' | 'audit_logs';
type VisualizationType = 'table' | 'bar' | 'donut';
type AggregationType = 'count' | 'sum' | 'avg';

interface SavedTemplatePayload {
    source: string;
    columns: string[];
    conditions: unknown[];
    group_by: string;
    sort_by: string;
    sort_dir: string;
    name: string;
}

const props = defineProps<{
    available_fields: Record<SourceKey, Record<string, FieldDef>>;
}>();

const source = ref<SourceKey>('files');
const selectedColumns = ref<Column[]>([]);
const conditions = ref<Condition[]>([]);
const groupBy = ref('');
const sortBy = ref('');
const sortDir = ref<'asc' | 'desc'>('asc');
const reportTitle = ref('');
const visualizationType = ref<VisualizationType>('table');
const aggregationType = ref<AggregationType>('count');
const isTitleEditing = ref(false);

// Reset builder state when source changes
watch(source, () => {
    selectedColumns.value = [];
    conditions.value = [];
    groupBy.value = '';
    sortBy.value = '';
    sortDir.value = 'asc';
    previewResult.value = null;
    error.value = '';
    visualizationType.value = 'table';
});

// Reset chart view if group_by is cleared
watch(groupBy, (val) => {
    if (!val) visualizationType.value = 'table';
});

const previewResult = ref<{ columns: Column[]; rows: Record<string, unknown>[]; total: number } | null>(null);
const loading = ref(false);
const error = ref('');
const generatedAt = ref('');

const sourceLabels: Record<SourceKey, string> = {
    files: 'Files & Documents',
    requests: 'Retrieval Requests',
    users: 'Users',
    disposal_batches: 'Disposal Batches',
    audit_logs: 'Audit Logs',
};

const isSourceKey = (value: string): value is SourceKey => {
    return ['files', 'requests', 'users', 'disposal_batches', 'audit_logs'].includes(value);
};

const availableFields = computed(() => props.available_fields[source.value] ?? {});

const allFieldsForBuilder = computed(() =>
    Object.entries(availableFields.value).map(([key, def]) => ({
        key,
        label: def.label,
        type: def.type,
        ...(def.options ? { options: def.options } : {}),
    })),
);

const activeConditions = computed(() => conditions.value.filter(c => c.field !== '').length);
const isChartMode = computed(() => visualizationType.value !== 'table');
const canShowCharts = computed(() => {
    if (!previewResult.value) return false;
    return previewResult.value.rows.length > 0;
});

const handleAddColumn = (key: string) => {
    if (!key || selectedColumns.value.some(column => column.key === key)) return;
    const field = allFieldsForBuilder.value.find(item => item.key === key);
    if (!field) return;
    selectedColumns.value.push({ key: field.key, label: field.label, type: field.type });
};

const handleRunReport = async () => {
    if (selectedColumns.value.length === 0) {
        error.value = 'Please select at least one field to include in the report.';
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        const resp = await axios.post(previewRoute.url(), {
            source: source.value,
            columns: selectedColumns.value.map(c => c.key),
            conditions: conditions.value.filter(c => c.field !== ''),
            group_by: groupBy.value,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        });

        const formattedRows = resp.data.data.map((row: Record<string, unknown>) => {
            const formatted: Record<string, unknown> = {};
            for (const col of selectedColumns.value) {
                formatted[col.key] = formatValue(col.key, row[col.key], row);
            }
            return formatted;
        });

        previewResult.value = {
            columns: resp.data.columns,
            rows: formattedRows,
            total: resp.data.meta?.total ?? 0,
        };
        generatedAt.value = new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e: unknown) {
        const err = e as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } }; message?: string };
        const validationErrors = err?.response?.data?.errors
            ? Object.values(err.response.data.errors).flat().join(' ')
            : null;
        error.value = validationErrors
            ?? err?.response?.data?.message
            ?? (err?.response?.status ? `HTTP ${err.response.status}` : null)
            ?? err?.message
            ?? 'Failed to run report.';
    } finally {
        loading.value = false;
    }
};

const formatValue = (column: string, value: unknown, row: Record<string, unknown>): string => {
    if (value === null || value === undefined) return '—';

    if (column === 'retention_status' && row.retention_duration === 'perpetual') return '';

    if (column === 'retention_duration') {
        const duration = String(value);
        if (duration === 'one_year') return '1 Year';
        if (duration === 'ten_years') return '10 Years';
        if (duration === 'perpetual') return 'Perpetual';
        return String(value);
    }

    if (column === 'is_record') return value ? 'Record' : 'File';

    if (column === 'retention_status') {
        const s = String(value).toLowerCase();
        if (s === 'active' || s === 'archived') return 'ACTIVE';
        if (s === 'ready_for_disposal') return 'READY TO BE DISPOSED';
        if (s === 'disposed') return 'DISPOSED';
    }

    if (['status', 'retention_status', 'priority', 'mode_of_disposal'].includes(column)) {
        return String(value).split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    }

    return String(value);
};

// Build chart series from preview rows (when grouped)
const chartSeries = computed(() => {
    if (!previewResult.value?.rows.length) return [];

    if (groupBy.value && previewResult.value.columns.some(c => c.key === 'count')) {
        return previewResult.value.rows
            .map(row => Number(row['count'] ?? row[Object.keys(row).find(k => k !== groupBy.value) ?? ''] ?? 0))
            .filter(v => !isNaN(v));
    }

    const valueCol = previewResult.value.columns.find(c => c.key !== groupBy.value);
    if (!valueCol) return [];
    return previewResult.value.rows.map(row => Number(row[valueCol.key] ?? 0)).filter(v => !isNaN(v));
});

const chartLabels = computed(() => {
    if (!previewResult.value?.rows.length) return [];
    const labelField = groupBy.value || previewResult.value.columns[0]?.key;
    return previewResult.value.rows.map(row => String(row[labelField] ?? '—'));
});

const exportFilters = computed(() => ({
    source: source.value,
    columns: selectedColumns.value.map(c => c.key).join(','),
    conditions: JSON.stringify(conditions.value.filter(c => c.field !== '')),
    group_by: groupBy.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
}));

const currentTemplateConfig = computed(() => ({
    source: source.value,
    columns: selectedColumns.value.map(c => c.key),
    conditions: conditions.value.filter(c => c.field !== ''),
    group_by: groupBy.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
}));

const handleLoadTemplate = (template: SavedTemplatePayload) => {
    const normalizedSource: SourceKey = isSourceKey(template.source) ? template.source : 'files';
    const normalizedSortDir: 'asc' | 'desc' = template.sort_dir === 'desc' ? 'desc' : 'asc';

    source.value = normalizedSource;
    groupBy.value = template.group_by;
    sortBy.value = template.sort_by;
    sortDir.value = normalizedSortDir;
    reportTitle.value = template.name;
    previewResult.value = null;
    error.value = '';

    // Restore columns
    selectedColumns.value = template.columns
        .map(key => {
            const field = Object.entries(props.available_fields[normalizedSource] ?? {}).find(([k]) => k === key);
            if (!field) return null;
            return { key, label: field[1].label, type: field[1].type };
        })
        .filter(Boolean) as Column[];

    conditions.value = template.conditions
        .map((condition) => {
            if (!condition || typeof condition !== 'object') return null;

            const candidate = condition as Partial<Condition>;

            return {
                field: String(candidate.field ?? ''),
                operator: String(candidate.operator ?? ''),
                value: String(candidate.value ?? ''),
            };
        })
        .filter((condition): condition is Condition => condition !== null);
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }, { title: 'Custom Builder', href: '/reports/custom' }]">
        <Head title="Custom Report Builder" />

        <div class="flex flex-col gap-5 p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Wand2 class="h-5 w-5" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <h1 class="text-xl font-semibold text-foreground">Custom Report Builder</h1>
                        <p class="text-sm text-muted-foreground">Build any report from your records system — no technical knowledge required</p>
                    </div>
                </div>
                <ExportOptionsDialog
                    v-if="previewResult"
                    report-type="custom"
                    :filters="exportFilters"
                    :report-title="reportTitle"
                />
            </div>

            <!-- Report title bar -->
            <div class="flex items-center gap-2 rounded-xl border border-border/60 bg-card px-4 py-3">
                <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
                <div v-if="!isTitleEditing" class="flex flex-1 items-center gap-2">
                    <span class="text-sm font-medium text-foreground">
                        {{ reportTitle || 'Untitled Report' }}
                    </span>
                    <span v-if="!reportTitle" class="text-xs text-muted-foreground/60">(click to name your report)</span>
                </div>
                <Input
                    v-else
                    v-model="reportTitle"
                    placeholder="Enter a name for your report…"
                    class="h-7 flex-1 border-0 bg-transparent p-0 text-sm font-medium shadow-none focus-visible:ring-0"
                    autofocus
                    @blur="isTitleEditing = false"
                    @keydown.enter="isTitleEditing = false"
                    @keydown.escape="isTitleEditing = false"
                />
                <button
                    type="button"
                    class="rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
                    title="Edit report name"
                    @click="isTitleEditing = !isTitleEditing"
                >
                    <Pencil class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Controls toolbar -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border/60 bg-card px-4 py-3">
                <!-- Source selector -->
                <div class="flex items-center gap-2">
                    <Label class="shrink-0 text-xs font-medium text-muted-foreground">Data Source</Label>
                    <Select v-model="source">
                        <SelectTrigger class="h-8 w-52 text-xs">
                            <SelectValue placeholder="Select source" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="files">Files &amp; Documents</SelectItem>
                            <SelectItem value="requests">Retrieval Requests</SelectItem>
                            <SelectItem value="users">Users</SelectItem>
                            <SelectItem value="disposal_batches">Disposal Batches</SelectItem>
                            <SelectItem value="audit_logs">Audit Logs</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Aggregation (only when grouped) -->
                <AggregationSelector
                    v-if="groupBy"
                    v-model="aggregationType"
                />

                <!-- Live config badges -->
                <div class="flex flex-1 flex-wrap items-center gap-1.5">
                    <Badge v-if="selectedColumns.length > 0" variant="secondary" class="text-xs">
                        {{ selectedColumns.length }} field{{ selectedColumns.length !== 1 ? 's' : '' }}
                    </Badge>
                    <Badge v-if="activeConditions > 0" variant="secondary" class="text-xs">
                        {{ activeConditions }} filter{{ activeConditions !== 1 ? 's' : '' }}
                    </Badge>
                    <Badge v-if="groupBy" variant="secondary" class="text-xs">Grouped by {{ groupBy }}</Badge>
                    <Badge v-if="sortBy" variant="secondary" class="text-xs">Sorted</Badge>
                </div>

                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>

                <TooltipProvider :delay-duration="200">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <span>
                                <Button
                                    size="sm"
                                    :disabled="loading || selectedColumns.length === 0"
                                    class="gap-1.5"
                                    aria-label="Run report"
                                    @click="handleRunReport"
                                >
                                    <Play class="h-3.5 w-3.5" />
                                    {{ loading ? 'Running…' : 'Run Report' }}
                                </Button>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent v-if="selectedColumns.length === 0 && !loading" side="bottom">
                            Select at least one field first
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <!-- Step flow indicator -->
            <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">1</span>
                    <span :class="{ 'font-medium text-foreground': allFieldsForBuilder.length > 0 }">Pick Fields</span>
                </span>
                <ChevronRight class="h-3 w-3 shrink-0 text-muted-foreground/40" />
                <span class="flex items-center gap-1.5">
                    <span
                        class="flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold"
                        :class="selectedColumns.length > 0 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                    >2</span>
                    <span :class="{ 'font-medium text-foreground': selectedColumns.length > 0 }">Set Filters</span>
                </span>
                <ChevronRight class="h-3 w-3 shrink-0 text-muted-foreground/40" />
                <span class="flex items-center gap-1.5">
                    <span
                        class="flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold"
                        :class="previewResult ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                    >3</span>
                    <span :class="{ 'font-medium text-foreground': !!previewResult }">Preview &amp; Export</span>
                </span>
            </div>

            <!-- Builder panels -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1fr_1.5fr]">

                <!-- Col 1: Available Fields + Saved Templates -->
                <div class="flex flex-col gap-3">
                    <div class="rounded-xl border border-border/60 bg-card p-4">
                        <ColumnList
                            :fields="allFieldsForBuilder"
                            :selected-keys="selectedColumns.map(c => c.key)"
                            @add="handleAddColumn"
                        />
                    </div>
                    <div class="rounded-xl border border-border/60 bg-card p-4">
                        <SavedTemplates
                            :current-config="currentTemplateConfig"
                            @load="handleLoadTemplate"
                        />
                    </div>
                </div>

                <!-- Col 2: Configure (columns, conditions, group/sort) -->
                <div class="flex flex-col gap-3">
                    <div class="rounded-xl border border-border/60 bg-card p-4">
                        <SelectedColumns v-model="selectedColumns" />
                    </div>

                    <div class="rounded-xl border border-border/60 bg-card p-4">
                        <ConditionRow
                            v-model="conditions"
                            :available-fields="allFieldsForBuilder"
                        />
                    </div>

                    <div class="rounded-xl border border-border/60 bg-card">
                        <div class="border-b border-border/40 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Group &amp; Sort</p>
                        </div>
                        <div class="p-4">
                            <GroupSortControls
                                :available-fields="allFieldsForBuilder"
                                v-model:group-by="groupBy"
                                v-model:sort-by="sortBy"
                                v-model:sort-dir="sortDir"
                            />
                        </div>
                    </div>
                </div>

                <!-- Col 3: Preview panel -->
                <div class="flex flex-col overflow-hidden rounded-xl border border-border/60">
                    <!-- Viewer toolbar -->
                    <div class="flex items-center justify-between bg-zinc-700 px-4 py-2 dark:bg-zinc-900">
                        <div class="flex items-center gap-2">
                            <FileText class="h-3.5 w-3.5 text-zinc-300" />
                            <span class="text-xs font-medium text-zinc-300">
                                {{ reportTitle || 'Report Preview' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="previewResult"
                                class="rounded bg-zinc-600 px-2 py-0.5 text-xs text-zinc-200 dark:bg-zinc-800"
                            >
                                {{ previewResult.total.toLocaleString() }} rows
                            </span>
                            <!-- Visualization toggle -->
                            <VisualizationToggle
                                v-if="previewResult"
                                v-model="visualizationType"
                                :disable-charts="!canShowCharts"
                            />
                        </div>
                    </div>

                    <!-- Viewer body -->
                    <div
                        class="flex flex-1 flex-col items-center overflow-y-auto bg-zinc-600 p-4 dark:bg-zinc-800"
                        style="min-height: 460px; max-height: 720px;"
                    >
                        <!-- Empty state -->
                        <div
                            v-if="!previewResult && !loading && !error"
                            class="flex h-full w-full flex-col items-center justify-center gap-4 text-zinc-400"
                        >
                            <div class="flex h-24 w-20 flex-col items-center justify-center rounded-lg border-2 border-dashed border-zinc-500 bg-zinc-700 shadow-inner">
                                <FileText class="h-8 w-8 text-zinc-500" />
                            </div>
                            <div class="flex flex-col items-center gap-1 text-center">
                                <p class="text-xs font-medium text-zinc-300">Your report will appear here</p>
                                <p class="text-[11px] text-zinc-500">① Pick fields &nbsp;·&nbsp; ② Add filters &nbsp;·&nbsp; ③ Run Report</p>
                            </div>
                        </div>

                        <!-- Error state -->
                        <div v-else-if="error && !loading" class="w-full rounded bg-white shadow-2xl">
                            <div class="border-b border-red-100 bg-red-50 px-6 py-4">
                                <p class="text-sm font-semibold text-red-700">Something went wrong</p>
                                <p class="mt-0.5 text-xs text-red-500">{{ error }}</p>
                            </div>
                            <div class="px-6 py-8 text-center text-xs text-zinc-400">
                                Check your settings and try running the report again.
                            </div>
                        </div>

                        <!-- Loading skeleton -->
                        <div v-else-if="loading" class="w-full rounded bg-white shadow-2xl">
                            <div class="border-b border-zinc-200 px-6 py-5">
                                <div class="mb-3 h-4 w-44 animate-pulse rounded bg-zinc-200" />
                                <div class="h-3 w-28 animate-pulse rounded bg-zinc-100" />
                            </div>
                            <div class="px-6 py-4">
                                <div class="mb-3 h-5 w-full animate-pulse rounded bg-zinc-100" />
                                <div
                                    v-for="i in 9"
                                    :key="i"
                                    class="mb-2 h-4 w-full animate-pulse rounded"
                                    :class="i % 2 === 0 ? 'bg-zinc-100' : 'bg-zinc-50'"
                                />
                            </div>
                        </div>

                        <!-- Chart view (when grouped) -->
                        <div v-else-if="previewResult && isChartMode && canShowCharts" class="w-full rounded bg-white shadow-2xl">
                            <div class="border-b border-zinc-200 px-6 py-5">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-sm font-bold text-zinc-900">{{ reportTitle || 'Custom Report' }}</p>
                                        <p class="text-xs text-zinc-500">
                                            Source: {{ sourceLabels[source] }}
                                            <span v-if="groupBy"> · Grouped by {{ groupBy }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-semibold tracking-widest text-zinc-500">CONFIDENTIAL</p>
                                        <p class="text-xs text-zinc-400">Generated {{ generatedAt }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6">
                                <ReportChart
                                    :type="visualizationType === 'donut' ? 'donut' : 'bar'"
                                    :series="visualizationType === 'donut' ? chartSeries as number[] : [{ name: aggregationType === 'count' ? 'Count' : 'Value', data: chartSeries as number[] }]"
                                    :labels="visualizationType === 'donut' ? chartLabels : undefined"
                                    :categories="visualizationType !== 'donut' ? chartLabels : undefined"
                                    :height="320"
                                />
                            </div>
                            <div class="flex items-center justify-between border-t border-zinc-200 px-6 py-3">
                                <p class="text-xs text-zinc-400">
                                    Total: <span class="font-medium text-zinc-600">{{ previewResult.total.toLocaleString() }}</span> rows
                                </p>
                                <p class="text-xs text-zinc-400">{{ previewResult.rows.length }} groups</p>
                            </div>
                        </div>

                        <!-- Table / paper document -->
                        <div v-else-if="previewResult" class="w-full rounded bg-white shadow-2xl">
                            <!-- Document header -->
                            <div class="border-b border-zinc-200 px-6 py-5">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-900">
                                            <FileText class="h-5 w-5 text-white" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-zinc-900">{{ reportTitle || 'Custom Report' }}</p>
                                            <p class="text-xs text-zinc-500">Source: {{ sourceLabels[source] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-semibold tracking-widest text-zinc-500">CONFIDENTIAL</p>
                                        <p class="text-xs text-zinc-400">Generated {{ generatedAt }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Data table -->
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="bg-zinc-100">
                                            <th
                                                v-for="col in previewResult.columns"
                                                :key="col.key"
                                                class="border-b-2 border-zinc-300 px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 whitespace-nowrap"
                                            >
                                                {{ col.label }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="previewResult.rows.length === 0">
                                            <td
                                                :colspan="previewResult.columns.length"
                                                class="px-4 py-8 text-center text-xs text-zinc-400"
                                            >
                                                No results matched your filters. Try adjusting your conditions.
                                            </td>
                                        </tr>
                                        <tr
                                            v-for="(row, idx) in previewResult.rows"
                                            :key="idx"
                                            :class="idx % 2 === 0 ? 'bg-white' : 'bg-zinc-50'"
                                        >
                                            <td
                                                v-for="col in previewResult.columns"
                                                :key="col.key"
                                                class="border-b border-zinc-100 px-4 py-2 text-xs text-zinc-700 whitespace-nowrap"
                                            >
                                                {{ row[col.key] }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Document footer -->
                            <div class="flex items-center justify-between border-t border-zinc-200 px-6 py-3">
                                <p class="text-xs text-zinc-400">
                                    Total: <span class="font-medium text-zinc-600">{{ previewResult.total.toLocaleString() }}</span> rows
                                    <span v-if="previewResult.rows.length < previewResult.total" class="ml-1 text-zinc-400">(showing first {{ previewResult.rows.length }})</span>
                                </p>
                                <p class="text-xs text-zinc-400">Page 1 of 1</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
