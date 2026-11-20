<script setup lang="ts">
import { reactive, ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ClipboardList, FileX, Info } from 'lucide-vue-next';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportFilterPanel from '@/components/reports/ReportFilterPanel.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import ExportButton from '@/components/reports/ExportButton.vue';
import RecordDetailsSheet from '@/components/reports/RecordDetailsSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Tag { id: number; name: string; type: string }
interface RecordItem {
    id: string;
    record_id: string;
    file_name: string;
    author: string | null;
    department: string | null;
    department_id?: string | number;
    folder: string | null;
    tags: Tag[];
    status: string;
    retention_status: string;
    retention_duration: string | null;
    date_created: string | null;
    retention_expires_at: string | null;
    is_record: boolean;
}
interface ChartItem { label: string; count: number }
interface PaginationLink { url: string | null; label: string; active: boolean }
interface Paginator {
    data: RecordItem[];
    links: PaginationLink[];
    total?: number;
    current_page?: number;
    last_page?: number;
}
interface Options {
    departments: { id: string | number; name: string }[];
    root_folders: { id: string; name: string }[];
    per_page: number[];
    statuses: string[];
    retention_statuses: string[];
}

const props = defineProps<{
    records: Paginator;
    filters: { [key: string]: unknown };
    charts: {
        by_department: ChartItem[];
        by_retention_status: ChartItem[];
    };
    options: Options;
}>();

const searchQuery = ref((props.filters.search as string) ?? '');

const state = reactive({
    department_id: (props.filters.department_id as string) ?? '',
    status: (props.filters.status as string) ?? '',
    retention_status: (props.filters.retention_status as string) ?? '',
    date_from: (props.filters.date_from as string) ?? '',
    date_to: (props.filters.date_to as string) ?? '',
    metadata_tags: (props.filters.metadata_tags as number[]) ?? [],
    physical_location_tags: (props.filters.physical_location_tags as number[]) ?? [],
    metadata_tag_search: (props.filters.metadata_tag_search as string) ?? '',
    physical_location_tag_search: (props.filters.physical_location_tag_search as string) ?? '',
    per_page: (props.filters.per_page as number) ?? 20,
});

const navigate = useDebounceFn(() => {
    router.get(
        '/reports/records-inventory',
        {
            ...state,
            search: searchQuery.value,
            page: 1,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 350);

watch(
    () => [
        searchQuery.value,
        state.department_id,
        state.status,
        state.retention_status,
        state.date_from,
        state.date_to,
        state.metadata_tag_search,
        state.physical_location_tag_search,
        state.per_page,
        state.metadata_tags.join(','),
        state.physical_location_tags.join(','),
    ],
    navigate,
);

const selectedDepartmentName = computed(() => {
    const match = props.options.departments.find(dept => String(dept.id) === state.department_id);
    return match?.name ?? '';
});

const filteredRecords = computed(() => {
    let filtered = [...props.records.data];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(record =>
            record.record_id.toLowerCase().includes(query) ||
            record.file_name.toLowerCase().includes(query) ||
            (record.author && record.author.toLowerCase().includes(query)) ||
            (record.department && record.department.toLowerCase().includes(query)) ||
            record.tags.some(tag =>
                (tag.type === 'metadata' || tag.type === 'physical_location') &&
                tag.name.toLowerCase().includes(query),
            ),
        );
    }

    if (state.department_id) {
        const selectedName = selectedDepartmentName.value.toLowerCase();
        filtered = filtered.filter(record => {
            const idMatch = String(record.department_id ?? '') === state.department_id;
            const nameMatch = selectedName ? (record.department ? record.department.toLowerCase() === selectedName : false) : false;
            return idMatch || nameMatch;
        });
    }

    if (state.status) {
        filtered = filtered.filter(record => record.status === state.status);
    }

    if (state.retention_status) {
        filtered = filtered.filter(record => record.retention_status === state.retention_status);
    }

    if (state.date_from) {
        const from = new Date(state.date_from);
        filtered = filtered.filter(record => {
            if (!record.date_created) return false;
            return new Date(record.date_created) >= from;
        });
    }

    if (state.date_to) {
        const to = new Date(state.date_to);
        filtered = filtered.filter(record => {
            if (!record.date_created) return false;
            return new Date(record.date_created) <= to;
        });
    }

    if (state.metadata_tags.length > 0) {
        filtered = filtered.filter(record => {
            const tagIds = record.tags.filter(t => t.type === 'metadata').map(t => t.id);
            return state.metadata_tags.every(id => tagIds.includes(id));
        });
    }

    if (state.physical_location_tags.length > 0) {
        filtered = filtered.filter(record => {
            const tagIds = record.tags.filter(t => t.type === 'physical_location').map(t => t.id);
            return state.physical_location_tags.every(id => tagIds.includes(id));
        });
    }

    return filtered;
});

// Insight: count overdue/problem records
const needsActionCount = computed(() => {
    return filteredRecords.value.filter(r =>
        r.retention_status?.toLowerCase() === 'ready_for_disposal' ||
        r.retention_status?.toLowerCase() === 'overdue',
    ).length;
});

const insightMessage = computed(() => {
    const total = filteredRecords.value.length;
    const overall = props.records.total || props.records.data.length;
    const action = needsActionCount.value;
    let msg = `Showing ${total.toLocaleString()} of ${overall.toLocaleString()} document${overall !== 1 ? 's' : ''}.`;
    if (action > 0) {
        msg += ` ${action.toLocaleString()} document${action !== 1 ? 's' : ''} need attention.`;
    }
    return msg;
});

const handleReset = () => {
    searchQuery.value = '';
    Object.assign(state, {
        department_id: '', status: '', retention_status: '',
        date_from: '', date_to: '', metadata_tags: [], physical_location_tags: [],
        metadata_tag_search: '', physical_location_tag_search: '',
        per_page: 20,
    });
};

const infoSheetOpen = ref(false);
const selectedRecord = ref<RecordItem | null>(null);

const openInfoSheet = (recordId: string) => {
    const record = filteredRecords.value.find(r => r.id === recordId);
    if (record) {
        selectedRecord.value = record;
        infoSheetOpen.value = true;
    }
};

const columns = [
    { key: 'record_id', label: 'Record ID' },
    { key: 'file_name', label: 'File Name' },
    { key: 'author', label: 'Author' },
    { key: 'department', label: 'Division' },
    { key: 'status', label: 'Status' },
    { key: 'retention_status', label: 'Lifecycle Stage', class: 'px-1' },
    { key: 'date_created', label: 'Created' },
    { key: 'retention_expires_at', label: 'Expires At' },
];

// Human-readable lifecycle status labels
const formatRetentionStatus = (status: string): string => {
    const map: Record<string, string> = {
        active: 'ACTIVE',
        archived: 'ACTIVE',
        ready_for_disposal: 'READY TO BE DISPOSED',
        disposed: 'DISPOSED',
    };
    return map[status?.toLowerCase()] ?? status?.replace(/_/g, ' ') ?? '—';
};

const retentionStatusColors = computed(() =>
    props.charts.by_retention_status.map(item => {
        const status = item.label.toLowerCase();
        if (status === 'active' || status === 'archived') return '#22c55e';
        if (status === 'ready_for_disposal') return '#f59e0b';
        if (status === 'disposed') return '#ef4444';
        return '#6b7280';
    }),
);

const retentionStatusLabels = computed(() =>
    props.charts.by_retention_status.map(item => formatRetentionStatus(item.label)),
);

const pageNumberLinks = computed(() => (props.records.links ?? []).filter(link => /^\d+$/.test(link.label)));
const edgeLinks = computed(() => (props.records.links ?? []).filter(link => !/^\d+$/.test(link.label)));
const prevLink = computed(() => edgeLinks.value.find(link => /prev/i.test(link.label) || link.label.includes('«')));
const nextLink = computed(() => edgeLinks.value.find(link => /next/i.test(link.label) || link.label.includes('»')));

const mobileLinks = computed(() => {
    const numeric = pageNumberLinks.value;
    if (numeric.length === 0) return [];

    const windowSize = 5;
    if (numeric.length <= windowSize) {
        return [
            ...(prevLink.value ? [prevLink.value] : []),
            ...numeric,
            ...(nextLink.value ? [nextLink.value] : []),
        ];
    }

    const activeIndex = numeric.findIndex(link => link.active);
    const start = Math.max(0, Math.min(activeIndex - 2, numeric.length - windowSize));
    const windowLinks = numeric.slice(start, start + windowSize);

    return [
        ...(prevLink.value ? [prevLink.value] : []),
        ...windowLinks,
        ...(nextLink.value ? [nextLink.value] : []),
    ];
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }, { title: 'Records Inventory', href: '/reports/records-inventory' }]">
        <Head title="Records Inventory Report" />

        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ClipboardList class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Records Inventory</h1>
                        <p class="text-sm text-muted-foreground">A complete list of all documents — filter to find what you need</p>
                    </div>
                </div>
                <ExportButton report-type="records-inventory" :filters="{ ...state, search: searchQuery.value }" />
            </div>

            <!-- Filter panel (F-bar 1) -->
            <ReportFilterPanel
                v-model:search-query="searchQuery"
                v-model:department-filter="state.department_id"
                v-model:status-filter="state.status"
                v-model:retention-status-filter="state.retention_status"
                v-model:date-from-filter="state.date_from"
                v-model:date-to-filter="state.date_to"
                v-model:metadata-tag-ids="state.metadata_tags"
                v-model:physical-location-tag-ids="state.physical_location_tags"
                @metadata-tag-search="state.metadata_tag_search = $event"
                @physical-location-tag-search="state.physical_location_tag_search = $event"
                :departments="props.options.departments"
                :statuses="props.options.statuses"
                :retention-statuses="props.options.retention_statuses"
                show-status
                show-retention-status
                show-date-range
                @reset="handleReset"
            />

            <!-- Insight callout -->
            <div class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-4 py-2.5">
                <component
                    :is="needsActionCount > 0 ? AlertTriangle : Info"
                    class="h-4 w-4 shrink-0"
                    :class="needsActionCount > 0 ? 'text-amber-500' : 'text-muted-foreground'"
                />
                <p class="text-sm text-muted-foreground">
                    {{ insightMessage }}
                    <template v-if="needsActionCount > 0">
                        <span class="ml-1 font-medium text-amber-700 dark:text-amber-400">These are ready to be reviewed for disposal.</span>
                    </template>
                </p>
            </div>

            <!-- Charts (F-bar 2) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">By Division</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Documents per department</p>
                    <ReportChart
                        type="bar"
                        :series="[{ name: 'Documents', data: props.charts.by_department.map(d => d.count) }]"
                        :categories="props.charts.by_department.map(d => d.label)"
                        :height="220"
                    />
                </div>
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Document Lifecycle Status</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Where each document is in its retention cycle</p>
                    <ReportChart
                        type="donut"
                        :series="props.charts.by_retention_status.map(d => d.count)"
                        :labels="retentionStatusLabels"
                        :colors="retentionStatusColors"
                        :height="220"
                    />
                </div>
            </div>

            <!-- Results count (F-stem header) -->
            <div class="flex items-center justify-between text-sm text-muted-foreground">
                <div>
                    Showing <span class="font-medium text-foreground">{{ filteredRecords.length }}</span>
                    of <span class="font-medium text-foreground">{{ props.records.total || props.records.data.length }}</span> documents
                </div>
                <div v-if="props.records.last_page && props.records.last_page > 1">
                    Page {{ props.records.current_page }} / {{ props.records.last_page }}
                </div>
            </div>

            <!-- Table (F-stem) -->
            <div class="flex flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col">
                <div v-if="filteredRecords.length > 0" class="flex flex-col h-full">
                    <div class="flex-1 max-h-[520px] overflow-auto">
                        <Table class="w-full min-w-[960px] table-fixed">
                            <TableHeader class="bg-background sticky top-0 z-10">
                                <TableRow class="border-b">
                                    <TableHead
                                        v-for="col in columns"
                                        :key="col.key"
                                        :class="['text-left py-2 md:py-3 text-xs md:text-sm', col.class]"
                                    >
                                        {{ col.label }}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="record in filteredRecords"
                                    :key="record.id"
                                    class="cursor-pointer even:bg-muted/30 transition-colors hover:bg-muted/50"
                                    @click="openInfoSheet(record.id)"
                                >
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 whitespace-nowrap">
                                        <div class="truncate max-w-[180px] md:max-w-[220px]" :title="record.record_id">{{ record.record_id }}</div>
                                    </TableCell>
                                    <TableCell class="font-medium text-xs md:text-sm text-left py-2 md:py-3 whitespace-nowrap">
                                        <div class="truncate max-w-[220px] md:max-w-[300px]" :title="record.file_name">{{ record.file_name }}</div>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 whitespace-nowrap">
                                        <div class="truncate max-w-[180px] md:max-w-[220px]" :title="record.author || '—'">{{ record.author || '—' }}</div>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 whitespace-nowrap">
                                        <div class="truncate max-w-[180px] md:max-w-[220px]" :title="record.department || '—'">{{ record.department || '—' }}</div>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3">
                                        <Badge
                                            :class="[
                                                'capitalize text-xs border whitespace-nowrap',
                                                record.status.toLowerCase() === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800' :
                                                record.status.toLowerCase() === 'pending' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300 dark:border-amber-800' :
                                                record.status.toLowerCase() === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800' :
                                                'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800'
                                            ]"
                                        >
                                            {{ record.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 px-1 whitespace-nowrap min-w-0">
                                        <Badge
                                            :class="[
                                                'capitalize text-xs border whitespace-nowrap inline-flex items-center max-w-[160px] md:max-w-[190px] overflow-hidden',
                                                record.retention_status.toLowerCase() === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800' :
                                                record.retention_status.toLowerCase() === 'archived' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800' :
                                                record.retention_status.toLowerCase() === 'ready_for_disposal' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300 dark:border-amber-800' :
                                                record.retention_status.toLowerCase() === 'disposed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800' :
                                                'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800'
                                            ]"
                                        >
                                            <span class="truncate" :title="formatRetentionStatus(record.retention_status)">{{ formatRetentionStatus(record.retention_status) }}</span>
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3">
                                        <div class="truncate" :title="record.date_created || '—'">{{ record.date_created || '—' }}</div>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3">
                                        <div
                                            class="truncate"
                                            :title="record.retention_expires_at || '—'"
                                            :class="record.retention_expires_at && new Date(record.retention_expires_at) < new Date() ? 'font-semibold text-rose-600 dark:text-rose-400' : ''"
                                        >
                                            {{ record.retention_expires_at || '—' }}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <FileX class="mb-4 h-24 w-24 text-muted-foreground/50" />
                    <h2 class="mb-2 text-xl font-semibold">No Documents Found</h2>
                    <p class="max-w-md text-muted-foreground">
                        Try adjusting your filters to see more results.
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.records.links?.length" class="flex flex-col gap-2">
                <div class="hidden sm:flex flex-wrap items-center gap-1.5">
                    <Button
                        v-for="link in props.records.links"
                        :key="link.label"
                        :disabled="!link.url"
                        variant="outline"
                        size="sm"
                        class="h-8 min-w-8 px-2 text-xs"
                        :class="{ 'bg-primary text-primary-foreground dark:bg-white dark:text-black hover:bg-primary hover:text-primary-foreground dark:hover:bg-white dark:hover:text-black': link.active }"
                        @click="link.url && router.visit(link.url, { preserveState: false, preserveScroll: true, replace: true })"
                    >
                        <span v-html="link.label" />
                    </Button>
                </div>

                <div class="flex sm:hidden items-center gap-1.5">
                    <Button
                        v-for="link in mobileLinks"
                        :key="(link.label ?? '') + (link.url ?? '')"
                        :disabled="!link.url"
                        variant="outline"
                        size="sm"
                        class="h-8 min-w-8 px-2 text-xs"
                        :class="{
                            'bg-black text-white hover:bg-black hover:text-white dark:bg-black dark:text-white dark:hover:bg-black dark:hover:text-white': link.active,
                            'min-w-14 px-3': !/^\d+$/.test(String(link.label)),
                        }"
                        @click="link.url && router.visit(link.url, { preserveState: false, preserveScroll: true, replace: true })"
                    >
                        <span v-html="link.label" />
                    </Button>
                </div>
            </div>
        </div>

        <RecordDetailsSheet v-model:open="infoSheetOpen" :record="selectedRecord" />
    </AppLayout>
</template>
