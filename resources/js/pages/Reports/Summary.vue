<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, BarChart2, CalendarClock, Clock, FileText, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportFilterPanel from '@/components/reports/ReportFilterPanel.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import StatCard from '@/components/reports/StatCard.vue';
import ExportButton from '@/components/reports/ExportButton.vue';

interface ChartItem { label: string; count: number }
interface WeeklyTrend { date: string; count: number }

const props = defineProps<{
    generated_at: string;
    filters: { period_from: string; period_to: string };
    counts: {
        total_files: number;
        total_records: number;
        by_department: ChartItem[];
        by_retention_status: ChartItem[];
        by_status: ChartItem[];
    };
    period: {
        added: number;
        disposed: number;
        requests: number;
        requests_fulfilled: number;
        weekly_trend: WeeklyTrend[];
    };
    compliance: {
        active: number;
        ready_for_disposal: number;
        disposed: number;
    };
}>();

const state = reactive({
    period_from: props.filters.period_from ?? '',
    period_to: props.filters.period_to ?? '',
});

const hasInitialized = ref(false);

onMounted(() => {
    const hadInitialFilters = Boolean(props.filters.period_from || props.filters.period_to);

    if (!hadInitialFilters) {
        return;
    }

    hasInitialized.value = true;
    state.period_from = '';
    state.period_to = '';
});

const navigate = useDebounceFn(() => {
    router.get('/reports/summary', { ...state }, { preserveState: true, preserveScroll: true, replace: true });
}, 350);

watch(
    state,
    () => {
        if (!hasInitialized.value) {
            hasInitialized.value = true;
            return;
        }

        navigate();
    },
    { deep: true },
);

// --- Human-readable retention status labels ---
const formatRetentionLabel = (label: string): string => {
    const map: Record<string, string> = {
        active: 'ACTIVE',
        archived: 'ACTIVE',
        ready_for_disposal: 'READY TO BE DISPOSED',
        disposed: 'DISPOSED',
    };
    return map[label?.toLowerCase()] ?? label;
};

const retentionStatusData = computed(() =>
    props.counts.by_retention_status.map(d => ({
        ...d,
        label: formatRetentionLabel(d.label),
    })),
);

const retentionStatusColors = computed(() =>
    props.counts.by_retention_status.map(item => {
        const s = item.label?.toLowerCase();
        if (s === 'active' || s === 'archived') return '#22c55e';
        if (s === 'ready_for_disposal') return '#f59e0b';
        if (s === 'disposed') return '#ef4444';
        return '#6b7280';
    }),
);

// --- Chart insight strings ---
const departmentInsight = computed(() => {
    if (!props.counts.by_department.length) return 'No division data available yet.';
    const top = props.counts.by_department[0];
    return `${top.label} has the most documents — ${top.count.toLocaleString()} in total.`;
});

const retentionStatusInsight = computed(() => {
    if (!retentionStatusData.value.length) return 'No lifecycle data available yet.';
    const sorted = [...retentionStatusData.value].sort((a, b) => b.count - a.count);
    const top = sorted[0];
    return `Most documents are currently "${top.label}" (${top.count.toLocaleString()}).`;
});

const weeklyTrendInsight = computed(() => {
    if (!props.period.weekly_trend.length) return 'No documents were added in this period.';
    const peak = [...props.period.weekly_trend].sort((a, b) => b.count - a.count)[0];
    return `Busiest day: ${peak.date} — ${peak.count.toLocaleString()} document${peak.count !== 1 ? 's' : ''} added.`;
});

const complianceInsight = computed(() => {
    const { active, ready_for_disposal, disposed } = props.compliance;

    if (ready_for_disposal > 0) {
        return `${ready_for_disposal.toLocaleString()} document${ready_for_disposal === 1 ? '' : 's'} are READY TO BE DISPOSED and awaiting disposal action.`;
    }

    if (active > 0) {
        return `${active.toLocaleString()} document${active === 1 ? '' : 's'} are ACTIVE — none are ready for disposal.`;
    }

    return `${disposed.toLocaleString()} document${disposed === 1 ? '' : 's'} are DISPOSED.`;
});

const lastUpdated = computed(() =>
    new Date(props.generated_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }),
);

const overviewRef = ref<HTMLElement | null>(null);
const periodRef = ref<HTMLElement | null>(null);
const complianceRef = ref<HTMLElement | null>(null);

const sections = [
    { id: 'overview', label: 'At a Glance', targetRef: overviewRef },
    { id: 'period', label: 'What Happened', targetRef: periodRef },
    { id: 'compliance', label: 'Records Health', targetRef: complianceRef },
];

const activeSectionId = ref(sections[0].id);
const intersectionObserver = ref<IntersectionObserver | null>(null);

const scrollToSection = (sectionId: string): void => {
    const target = sections.find(section => section.id === sectionId)?.targetRef?.value;

    if (!target) {
        return;
    }

    activeSectionId.value = sectionId;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const handleIntersections = (entries: IntersectionObserverEntry[]): void => {
    const visibleEntries = entries.filter(entry => entry.isIntersecting);

    if (!visibleEntries.length) {
        return;
    }

    visibleEntries.sort((a, b) => b.intersectionRatio - a.intersectionRatio);
    activeSectionId.value = visibleEntries[0].target.id;
};

onMounted(() => {
    intersectionObserver.value = new IntersectionObserver(handleIntersections, {
        rootMargin: '-140px 0px -40% 0px',
        threshold: [0.2, 0.4, 0.6, 0.8, 1],
    });

    sections.forEach(section => {
        const target = section.targetRef.value;

        if (target) {
            intersectionObserver.value?.observe(target);
        }
    });
});

onBeforeUnmount(() => {
    intersectionObserver.value?.disconnect();
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }, { title: 'Summary', href: '/reports/summary' }]">
        <Head title="Summary Report" />

        <div class="flex flex-col gap-6 p-4 sm:gap-8 sm:p-6">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                        <BarChart2 class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-foreground">Summary Report</h1>
                        <p class="text-sm text-muted-foreground">A complete health check of your records system</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden text-[11px] text-muted-foreground/60 sm:inline">Updated {{ lastUpdated }}</span>
                    <ExportButton report-type="summary" :filters="state" />
                </div>
            </div>

            <!-- Action required banner -->
            <div
                v-if="props.compliance.ready_for_disposal > 0"
                class="flex items-start gap-3 rounded-xl border border-rose-300/70 bg-rose-50 px-4 py-3.5 dark:border-rose-800/50 dark:bg-rose-950/40"
                role="alert"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
                <div>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">Action Required</p>
                    <p class="mt-0.5 text-xs text-rose-700 dark:text-rose-400">
                        {{ props.compliance.ready_for_disposal.toLocaleString() }} document{{ props.compliance.ready_for_disposal !== 1 ? 's' : '' }} are READY TO BE DISPOSED and awaiting disposal action.
                    </p>
                </div>
            </div>

            <!-- Sticky period picker + quick nav -->
            <div class="sticky top-0 z-10 -mx-4 bg-background/95 px-4 py-2 backdrop-blur supports-backdrop-filter:bg-background/80 sm:-mx-6 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <ReportFilterPanel
                            :show-search="false"
                            :show-period-range="true"
                            :show-metadata-tags="false"
                            :show-physical-location-tags="false"
                            :period-from-filter="state.period_from"
                            :period-to-filter="state.period_to"
                            @update:period-from-filter="val => state.period_from = val"
                            @update:period-to-filter="val => state.period_to = val"
                            @reset="state.period_from = ''; state.period_to = ''"
                        />
                    </div>
                    <div class="flex w-full shrink-0 flex-col items-center gap-1 sm:w-auto sm:flex-none sm:pl-2">
                        <p class="text-center text-[11px] font-semibold uppercase tracking-wide text-muted-foreground/80 dark:text-white pr-4">Jump to Section</p>
                        <div class="flex w-full items-center justify-center gap-2 overflow-x-auto pb-1 sm:w-auto sm:pb-0">
                            <template v-for="section in sections" :key="section.id">
                                <button
                                    type="button"
                                    class="inline-flex flex-none items-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 py-1.5 text-xs font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 sm:px-3 sm:py-2 sm:text-sm"
                                    :class="activeSectionId === section.id
                                        ? 'border-primary/40 bg-primary/10 text-primary shadow-sm'
                                        : 'border-border/60 bg-card text-muted-foreground hover:bg-muted/60'
                                    "
                                    :title="`Jump to ${section.label}`"
                                    @click="scrollToSection(section.id)"
                                >
                                    {{ section.label }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Section: At a Glance ──────────────────────────────────── -->
            <section ref="overviewRef" id="overview" class="flex flex-col gap-4 scroll-mt-32 sm:scroll-mt-36">
                <div class="flex items-center gap-3">
                    <div class="h-px min-w-4 flex-1 bg-linear-to-r from-transparent via-border/80 to-border/90"></div>
                    <div class="flex shrink-0 items-center gap-1.5 rounded-full border border-border/60 bg-card px-3.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/70 shadow-sm">
                        <span class="text-sm dark:text-white">At a Glance</span>
                    </div>
                    <div class="h-px min-w-4 flex-1 bg-linear-to-l from-transparent via-border/60 to-border/60"></div>
                </div>

                <!-- F-bar 1: High-level KPIs -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard
                        label="Total Documents"
                        :value="props.counts.total_files"
                        :icon="FileText"
                        card-class="border-border/60 bg-card shadow-sm"
                    />
                    <StatCard
                        label="Official Records"
                        :value="props.counts.total_records"
                        :icon="FileText"
                        icon-class="text-emerald-500"
                        card-class="border-border/60 bg-card shadow-sm"
                    />
                    <StatCard label="ACTIVE" :value="props.compliance.active" :icon="ShieldCheck" icon-class="text-emerald-500" color-class="text-emerald-600" />
                    <StatCard label="DISPOSED" :value="props.compliance.disposed" :icon="Trash2" icon-class="text-rose-400" color-class="text-rose-500" />
                </div>

                <!-- F-bar 2: Overview charts -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-6">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Documents by Division</p>
                        <p class="mb-3 text-[11px] text-muted-foreground/60">How documents are distributed across departments</p>
                        <ReportChart
                            type="bar"
                            :series="[{ name: 'Documents', data: props.counts.by_department.map(d => d.count) }]"
                            :categories="props.counts.by_department.map(d => d.label)"
                            :height="240"
                        />
                        <p class="mt-2 text-[11px] italic text-muted-foreground/70">{{ departmentInsight }}</p>
                    </div>
                    <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-6">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Document Lifecycle Status</p>
                        <p class="mb-3 text-[11px] text-muted-foreground/60">Where each document is in its lifecycle</p>
                        <ReportChart
                            type="donut"
                            :series="retentionStatusData.map(d => d.count)"
                            :labels="retentionStatusData.map(d => d.label)"
                            :colors="retentionStatusColors"
                            :height="240"
                        />
                        <p class="mt-2 text-[11px] italic text-muted-foreground/70">{{ retentionStatusInsight }}</p>
                    </div>
                </div>
            </section>

            <!-- ─── Section: What Happened This Period ────────────────────── -->
            <section ref="periodRef" id="period" class="flex flex-col gap-4 scroll-mt-32 sm:scroll-mt-36">
                <div class="flex items-center gap-3">
                    <div class="h-px min-w-4 flex-1 bg-linear-to-r from-transparent via-border/60 to-border/60"></div>
                    <div class="flex shrink-0 items-center gap-1.5 rounded-full border border-border/60 bg-card px-3.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/70 shadow-sm">
                        <span class="text-sm dark:text-white">What Happened This Period</span>
                    </div>
                    <div class="h-px min-w-4 flex-1 bg-linear-to-l from-transparent via-border/60 to-border/60"></div>
                </div>

                <div class="flex flex-col gap-4">
                    <p class="text-xs text-muted-foreground/60">
                        {{ (state.period_from && state.period_to) ? `${state.period_from} → ${state.period_to}` : 'All Time' }}
                    </p>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <StatCard label="Documents Added" :value="props.period.added" :icon="FileText" icon-class="text-blue-500" />
                        <StatCard label="Documents Removed" :value="props.period.disposed" :icon="Trash2" icon-class="text-rose-500" />
                        <StatCard label="Retrieval Requests" :value="props.period.requests" :icon="Clock" icon-class="text-amber-500" />
                        <StatCard label="Requests Fulfilled" :value="props.period.requests_fulfilled" :icon="FileText" icon-class="text-emerald-500" />
                    </div>
                </div>

                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-6">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Daily Document Uploads (Selected Period)</p>
                    <p class="mb-3 text-[11px] text-muted-foreground/60">Shows how many new documents were added each day</p>
                    <ReportChart
                        type="area"
                        :series="[{ name: 'Documents Added', data: props.period.weekly_trend.map(d => d.count) }]"
                        :categories="props.period.weekly_trend.map(d => d.date)"
                        :height="220"
                    />
                    <p class="mt-2 text-[11px] italic text-muted-foreground/70">{{ weeklyTrendInsight }}</p>
                </div>
            </section>

            <!-- ─── Section: Records Health ───────────────────────────────── -->
            <section ref="complianceRef" id="compliance" class="flex flex-col gap-4 scroll-mt-32 sm:scroll-mt-36">
                <div class="flex items-center gap-3">
                    <div class="h-px min-w-4 flex-1 bg-linear-to-r from-transparent via-border/60 to-border/60"></div>
                    <div class="flex shrink-0 items-center gap-1.5 rounded-full border border-border/60 bg-card px-3.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground/70 shadow-sm">
                        <span class="text-sm dark:text-white">Records Health</span>
                    </div>
                    <div class="h-px min-w-4 flex-1 bg-linear-to-l from-transparent via-border/60 to-border/60"></div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        label="ACTIVE"
                        :value="props.compliance.active"
                        :icon="ShieldCheck"
                        icon-class="text-emerald-500 h-5 w-5"
                        color-class="text-emerald-600"
                    />
                    <StatCard
                        label="READY TO BE DISPOSED"
                        :value="props.compliance.ready_for_disposal"
                        :icon="AlertTriangle"
                        icon-class="text-amber-600 h-5 w-5"
                        color-class="text-amber-600 dark:text-amber-400"
                        card-class="border-amber-300/60 bg-amber-50/80 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/40"
                    />
                    <StatCard
                        label="DISPOSED"
                        :value="props.compliance.disposed"
                        :icon="Trash2"
                        icon-class="text-rose-600 h-5 w-5"
                        color-class="text-rose-700 dark:text-rose-400"
                        card-class="border-rose-300/60 bg-rose-50/80 shadow-sm dark:border-rose-800/50 dark:bg-rose-950/40"
                    />
                </div>

                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-6">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Records Health Breakdown</p>
                    <p class="mb-3 text-[11px] text-muted-foreground/60">Shows the proportion of documents in each health state</p>
                    <ReportChart
                        type="donut"
                        :series="[props.compliance.active, props.compliance.ready_for_disposal, props.compliance.disposed]"
                        :labels="['ACTIVE', 'READY TO BE DISPOSED', 'DISPOSED']"
                        :colors="['#10b981', '#f59e0b', '#ef4444']"
                        :height="260"
                    />
                    <p class="mt-2 text-[11px] italic text-muted-foreground/70">{{ complianceInsight }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
