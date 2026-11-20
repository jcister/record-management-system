<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Download, Info, TrendingUp } from 'lucide-vue-next';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportFilterPanel from '@/components/reports/ReportFilterPanel.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import StatCard from '@/components/reports/StatCard.vue';
import ExportButton from '@/components/reports/ExportButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Tag { id: number; name: string; type: string }
interface TopFile { id: string; record_id: string; file_name: string; department: string | null; download_count: number; tags: Tag[] }
interface ChartItem { label: string; count: number }
interface Option { id: string | number; name: string }

const props = defineProps<{
    filters: { date_from?: string; date_to?: string; department_id?: string; top_n?: number; metadata_tags?: number[]; physical_location_tags?: number[] };
    top_files: TopFile[];
    by_department: ChartItem[];
    options: {
        departments: Option[];
        top_n: number[];
    };
}>();

const state = reactive({
    department_id: props.filters.department_id ?? '',
    top_n: String(props.filters.top_n ?? 20),
    metadata_tags: props.filters.metadata_tags ?? [],
    physical_location_tags: props.filters.physical_location_tags ?? [],
    per_page: 20,
});

const metadataTagSearch = ref('');
const physicalTagSearch = ref('');

const selectedDepartmentName = computed(() => {
    const match = props.options.departments.find(dept => String(dept.id) === state.department_id);
    return match?.name ?? '';
});

const navigate = useDebounceFn(() => {
    router.get('/reports/document-access-frequency', { ...state }, { preserveState: true, preserveScroll: true, replace: true });
}, 350);

watch(state, () => {
    currentPage.value = 1;
    navigate();
});

watch([metadataTagSearch, physicalTagSearch], () => {
    currentPage.value = 1;
});

const perPage = 20;
const currentPage = ref(1);

const filteredTopFiles = computed(() => {
    let files = [...props.top_files];

    if (state.department_id) {
        const selectedName = selectedDepartmentName.value.toLowerCase();
        files = files.filter(file => {
            return selectedName ? (file.department ? file.department.toLowerCase() === selectedName : false) : false;
        });
    }

    if (state.metadata_tags.length > 0) {
        files = files.filter(file => {
            const tagIds = file.tags.filter(t => t.type === 'metadata').map(t => t.id);
            return state.metadata_tags.every(id => tagIds.includes(id));
        });
    }

    if (state.physical_location_tags.length > 0) {
        files = files.filter(file => {
            const tagIds = file.tags.filter(t => t.type === 'physical_location').map(t => t.id);
            return state.physical_location_tags.every(id => tagIds.includes(id));
        });
    }

    if (metadataTagSearch.value.trim()) {
        const term = metadataTagSearch.value.toLowerCase();
        files = files.filter(file => file.tags.some(tag => tag.type === 'metadata' && tag.name.toLowerCase().includes(term)));
        files.sort((a, b) => {
            const aMatch = a.tags.find(tag => tag.type === 'metadata' && tag.name.toLowerCase().includes(term));
            const bMatch = b.tags.find(tag => tag.type === 'metadata' && tag.name.toLowerCase().includes(term));
            return (aMatch?.name || '').localeCompare(bMatch?.name || '');
        });
    }

    if (physicalTagSearch.value.trim()) {
        const term = physicalTagSearch.value.toLowerCase();
        files = files.filter(file => file.tags.some(tag => tag.type === 'physical_location' && tag.name.toLowerCase().includes(term)));
        files.sort((a, b) => {
            const aMatch = a.tags.find(tag => tag.type === 'physical_location' && tag.name.toLowerCase().includes(term));
            const bMatch = b.tags.find(tag => tag.type === 'physical_location' && tag.name.toLowerCase().includes(term));
            return (aMatch?.name || '').localeCompare(bMatch?.name || '');
        });
    }

    return files;
});

const topFilesWithRank = computed(() => filteredTopFiles.value.map((f, i) => ({ ...f, _rank: i + 1 })));

const totalPages = computed(() => Math.max(1, Math.ceil(topFilesWithRank.value.length / perPage)));

const paginatedTopFiles = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return topFilesWithRank.value.slice(start, start + perPage);
});

const maxDownloads = computed(() => {
    if (!topFilesWithRank.value.length) return 1;
    return Math.max(...topFilesWithRank.value.map(f => f.download_count), 1);
});

const totalDownloads = computed(() => props.top_files.reduce((sum, f) => sum + f.download_count, 0));

const topDocument = computed(() => props.top_files[0] ?? null);

const accessInsight = computed(() => {
    if (!topDocument.value) return 'No document access data available yet.';
    return `"${topDocument.value.file_name}" is the most-accessed document — opened ${topDocument.value.download_count.toLocaleString()} time${topDocument.value.download_count !== 1 ? 's' : ''}.`;
});

const pageChunks = computed(() => {
    const pages = Array.from({ length: totalPages.value }, (_, i) => i + 1);
    const chunks: number[][] = [];
    for (let i = 0; i < pages.length; i += 6) {
        chunks.push(pages.slice(i, i + 6));
    }
    return chunks;
});

const goToPage = (page: number) => {
    currentPage.value = Math.min(Math.max(page, 1), totalPages.value);
};

const handleReset = (): void => {
    metadataTagSearch.value = '';
    physicalTagSearch.value = '';
    Object.assign(state, { department_id: '', top_n: '20', metadata_tags: [], physical_location_tags: [], per_page: 20 });
};

const medalLabel = (rank: number): string => {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return '';
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }, { title: 'Document Access Frequency', href: '/reports/document-access-frequency' }]">
        <Head title="Document Access Frequency Report" />

        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Download class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Document Access Frequency</h1>
                        <p class="text-sm text-muted-foreground">Discover which documents are opened most often by your staff</p>
                    </div>
                </div>
                <ExportButton report-type="document-access-frequency" :filters="state" />
            </div>

            <!-- Filter (F-bar 1) -->
            <ReportFilterPanel
                :show-search="false"
                :departments="props.options.departments"
                :department-filter="state.department_id"
                v-model:metadata-tag-ids="state.metadata_tags"
                v-model:physical-location-tag-ids="state.physical_location_tags"
                @metadata-tag-search="metadataTagSearch = $event"
                @physical-location-tag-search="physicalTagSearch = $event"
                @update:department-filter="val => state.department_id = val"
                @reset="handleReset"
            />

            <!-- KPI cards (F-bar 2) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Documents Tracked" :value="filteredTopFiles.length" :icon="TrendingUp" />
                <StatCard label="Total Opens" :value="totalDownloads" :icon="Download" icon-class="text-blue-500" />
                <StatCard
                    label="Most Opened"
                    :value="topDocument ? topDocument.download_count : 0"
                    :icon="Download"
                    icon-class="text-amber-500"
                    color-class="text-amber-600"
                />
            </div>

            <!-- Insight callout -->
            <div class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-4 py-2.5">
                <Info class="h-4 w-4 shrink-0 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">{{ accessInsight }}</p>
            </div>

            <!-- Charts (F-bar 3) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Top 10 Most-Opened Documents</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Ranked by number of times opened</p>
                    <ReportChart
                        type="horizontalBar"
                        :series="[{ name: 'Opens', data: props.top_files.slice(0, 10).map(f => f.download_count) }]"
                        :categories="props.top_files.slice(0, 10).map(f => f.file_name.length > 25 ? f.file_name.slice(0, 25) + '…' : f.file_name)"
                        :height="280"
                    />
                </div>
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Opens by Division</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Which divisions open documents most frequently</p>
                    <ReportChart
                        type="bar"
                        :series="[{ name: 'Opens', data: props.by_department.map(d => d.count) }]"
                        :categories="props.by_department.map(d => d.label)"
                        :height="280"
                    />
                </div>
            </div>

            <!-- Table with medals and progress (F-stem) -->
            <div class="flex flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col">
                <div v-if="paginatedTopFiles.length > 0" class="flex flex-col h-full">
                    <div class="flex-1 max-h-[520px] overflow-auto">
                        <Table class="w-full min-w-[960px] table-fixed">
                            <TableHeader class="bg-background sticky top-0 z-10">
                                <TableRow class="border-b">
                                    <TableHead class="w-14 text-left py-2 md:py-3 px-4 text-xs md:text-sm">#</TableHead>
                                    <TableHead class="w-32 text-left py-2 md:py-3 px-4 text-xs md:text-sm">Record ID</TableHead>
                                    <TableHead class="w-[200px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">Document Name</TableHead>
                                    <TableHead class="w-[200px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">Division</TableHead>
                                    <TableHead class="w-[200px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">Opens</TableHead>
                                    <TableHead class="min-w-[180px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">Tags</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="file in paginatedTopFiles"
                                    :key="file.id"
                                    class="even:bg-muted/30 transition-colors hover:bg-muted/50"
                                    :class="file._rank <= 3 ? 'bg-amber-50/40 dark:bg-amber-950/10' : ''"
                                >
                                    <TableCell class="w-14 text-left py-2 md:py-3 px-4 text-xs md:text-sm font-semibold">
                                        <span v-if="file._rank <= 3" class="text-base">{{ medalLabel(file._rank) }}</span>
                                        <span v-else class="text-muted-foreground">{{ file._rank }}</span>
                                    </TableCell>
                                    <TableCell class="w-32 text-left py-2 md:py-3 px-4 text-xs md:text-sm">
                                        <div class="truncate" :title="file.record_id">{{ file.record_id }}</div>
                                    </TableCell>
                                    <TableCell class="w-[220px] text-left py-2 md:py-3 px-4 text-xs md:text-sm font-medium">
                                        <div class="truncate" :title="file.file_name">{{ file.file_name }}</div>
                                    </TableCell>
                                    <TableCell class="w-[200px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">
                                        <div class="truncate" :title="file.department || '—'">{{ file.department || '—' }}</div>
                                    </TableCell>
                                    <TableCell class="w-[200px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-primary w-12 shrink-0">{{ file.download_count.toLocaleString() }}</span>
                                            <div class="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                                                <div
                                                    class="h-full rounded-full bg-primary/60 transition-all"
                                                    :style="`width: ${Math.round((file.download_count / maxDownloads) * 100)}%`"
                                                ></div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-[180px] text-left py-2 md:py-3 px-4 text-xs md:text-sm">
                                        <div class="flex flex-wrap gap-1">
                                            <Badge v-for="tag in file.tags" :key="tag.id" variant="secondary" class="text-[10px]">
                                                {{ tag.name }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <Table class="w-full">
                        <TableBody>
                            <TableEmpty colspan="6">No document access data found.</TableEmpty>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="totalPages > 1" class="flex flex-col gap-2">
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="currentPage === 1"
                        @click="goToPage(currentPage - 1)"
                    >
                        Prev
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 px-3 text-xs"
                        :disabled="currentPage === totalPages"
                        @click="goToPage(currentPage + 1)"
                    >
                        Next
                    </Button>
                </div>
                <div v-for="(chunk, chunkIndex) in pageChunks" :key="chunkIndex" class="grid grid-cols-6 gap-1 w-fit">
                    <Button
                        v-for="page in chunk"
                        :key="page"
                        size="sm"
                        class="h-8 min-w-8 px-2 text-xs"
                        :variant="page === currentPage ? 'default' : 'outline'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

