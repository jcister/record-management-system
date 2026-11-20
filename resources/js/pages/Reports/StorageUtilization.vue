<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Database, HardDrive, Info } from 'lucide-vue-next';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportFilterPanel from '@/components/reports/ReportFilterPanel.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import StatCard from '@/components/reports/StatCard.vue';
import ExportButton from '@/components/reports/ExportButton.vue';

interface StorageRow { label: string; total_size: number; file_count: number; percentage: number }
interface Option { id: string | number; name: string }

const props = defineProps<{
    filters: { department_id?: string; folder_id?: string; group_by?: string };
    total_size: number;
    by_department: StorageRow[];
    by_folder: StorageRow[];
    options: {
        departments: Option[];
        root_folders: Option[];
        group_by: string[];
    };
}>();

const state = reactive({
    department_id: props.filters.department_id ?? '',
    folder_id: props.filters.folder_id ?? '',
    group_by: props.filters.group_by ?? 'department',
});

const navigate = useDebounceFn(() => {
    router.get('/reports/storage-utilization', { ...state }, { preserveState: true, preserveScroll: true, replace: true });
}, 350);

watch(state, navigate);

const activeData = computed(() => state.group_by === 'folder' ? props.by_folder : props.by_department);

const formatBytes = (bytes: number): string => {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    return (bytes / 1024).toFixed(2) + ' KB';
};

const topEntry = computed(() => {
    if (!activeData.value.length) return null;
    return [...activeData.value].sort((a, b) => b.total_size - a.total_size)[0];
});

const storageInsight = computed(() => {
    if (!topEntry.value) return 'No storage data available.';
    const label = state.group_by === 'folder' ? 'folder' : 'division';
    return `${topEntry.value.label} uses the most storage — ${formatBytes(topEntry.value.total_size)} (${topEntry.value.percentage}% of total).`;
});

const maxSize = computed(() => {
    if (!activeData.value.length) return 1;
    return Math.max(...activeData.value.map(r => r.total_size), 1);
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }, { title: 'Storage Utilization', href: '/reports/storage-utilization' }]">
        <Head title="Storage Utilization Report" />

        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Database class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Storage Utilization</h1>
                        <p class="text-sm text-muted-foreground">See how much space each division or folder is using</p>
                    </div>
                </div>
                <ExportButton report-type="storage-utilization" :filters="state" />
            </div>

            <!-- Filter (F-bar 1) -->
            <ReportFilterPanel
                :show-search="false"
                :show-metadata-tags="false"
                :show-physical-location-tags="false"
                :departments="props.options.departments"
                :department-filter="state.department_id"
                @update:department-filter="val => state.department_id = val"
                @reset="state.department_id = ''; state.folder_id = ''; state.group_by = 'department'"
            />

            <!-- KPI cards (F-bar 2) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Total Storage Used" :value="formatBytes(props.total_size)" :icon="HardDrive" />
                <StatCard label="Divisions Tracked" :value="props.by_department.length" :icon="Database" />
                <StatCard label="Folders Tracked" :value="props.by_folder.length" :icon="Database" />
            </div>

            <!-- Insight callout -->
            <div class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/40 px-4 py-2.5">
                <Info class="h-4 w-4 shrink-0 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">{{ storageInsight }}</p>
            </div>

            <!-- Charts (F-bar 3) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Storage Share by Division</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Percentage of total storage used by each division</p>
                    <ReportChart
                        type="donut"
                        :series="props.by_department.map(d => d.percentage)"
                        :labels="props.by_department.map(d => d.label)"
                        :height="260"
                    />
                </div>
                <div class="rounded-xl border border-border/60 bg-card p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Which folders contain the most documents</p>
                    <p class="mb-2 text-[11px] text-muted-foreground/60">Top folders ranked by document count</p>
                    <ReportChart
                        type="horizontalBar"
                        :series="[{ name: 'Files', data: props.by_folder.slice(0, 10).map(d => d.file_count) }]"
                        :categories="props.by_folder.slice(0, 10).map(d => d.label)"
                        :height="260"
                    />
                </div>
            </div>

            <!-- Group-by toggle + table (F-stem) -->
            <div class="flex items-center gap-3">
                <span class="text-xs font-medium text-muted-foreground">Group by:</span>
                <div class="flex rounded-lg border border-border/60 overflow-hidden">
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="state.group_by === 'department' ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-muted'"
                        @click="state.group_by = 'department'"
                    >
                        Division
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="state.group_by === 'folder' ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:bg-muted'"
                        @click="state.group_by = 'folder'"
                    >
                        Folder
                    </button>
                </div>
            </div>

            <!-- Storage table with progress bars -->
            <div class="overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm">
                <table class="w-full">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ state.group_by === 'folder' ? 'Folder' : 'Division' }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total Size</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">Files</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground w-48">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, idx) in activeData"
                            :key="row.label"
                            class="border-t border-border/40 transition-colors"
                            :class="idx % 2 === 0 ? 'bg-card' : 'bg-muted/20'"
                        >
                            <td class="px-4 py-3 text-sm font-medium text-foreground">{{ row.label }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ formatBytes(row.total_size) }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ row.file_count.toLocaleString() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full bg-muted overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="`width: ${row.percentage}%`"
                                        ></div>
                                    </div>
                                    <span class="w-10 shrink-0 text-right text-xs font-medium text-muted-foreground">{{ row.percentage }}%</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!activeData.length">
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-muted-foreground">No data available.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
