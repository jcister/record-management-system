<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Archive,
    BarChart2,
    ClipboardList,
    Cog,
    Database,
    Download,
    FileText,
    ShieldCheck,
    TrendingUp,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import ReportCard from '@/components/reports/ReportCard.vue';
import ReportChart from '@/components/reports/ReportChart.vue';
import StatCard from '@/components/reports/StatCard.vue';

interface ChartItem { label: string; count: number }
interface WeeklyTrend { date: string; count: number }

const props = defineProps<{
    stats: {
        total_documents: number;
        active_count: number;
        ready_for_disposal_count: number;
        pending_requests: number;
        disposed_this_month: number;
    };
    weekly_trend: WeeklyTrend[];
    compliance: {
        active: number;
        ready_for_disposal: number;
        disposed: number;
    };
    by_department: ChartItem[];
}>();

const reports = [
    {
        title: 'Records Inventory',
        description: 'See all documents in one place — filter by division, status, or date to find exactly what you need.',
        href: '/reports/records-inventory',
        icon: ClipboardList,
    },
    {
        title: 'Summary Report',
        description: 'A quick health check of your entire records system — totals, activity this period, and compliance at a glance.',
        href: '/reports/summary',
        icon: BarChart2,
    },
    {
        title: 'Storage Utilization',
        description: 'Find out which divisions or folders are using the most space and where you can free up storage.',
        href: '/reports/storage-utilization',
        icon: Database,
    },
    {
        title: 'Document Access Frequency',
        description: 'Discover which documents are opened most often — useful for understanding what staff rely on.',
        href: '/reports/document-access-frequency',
        icon: Download,
    },
    {
        title: 'Custom Report Builder',
        description: 'Build your own report from scratch — pick the fields you want, apply filters, and export to Excel or PDF.',
        href: '/reports/custom',
        icon: Cog,
    },
];

const hasAttentionItems = computed(() => props.stats.ready_for_disposal_count > 0);

const attentionMessage = computed(() => {
    const count = props.stats.ready_for_disposal_count;
    if (count <= 0) return '';
    return `${count.toLocaleString()} document${count !== 1 ? 's' : ''} are READY TO BE DISPOSED and need disposal action.`;
});

const trendDates = computed(() => props.weekly_trend.map(d => d.date));
const trendCounts = computed(() => props.weekly_trend.map(d => d.count));

const peakDay = computed(() => {
    if (!props.weekly_trend.length) return null;
    return [...props.weekly_trend].sort((a, b) => b.count - a.count)[0];
});

const trendInsight = computed(() => {
    if (!peakDay.value) return 'No document activity recorded in the last 30 days.';
    return `Most active day: ${peakDay.value.date} — ${peakDay.value.count.toLocaleString()} document${peakDay.value.count !== 1 ? 's' : ''} added.`;
});

const complianceRate = computed(() => {
    const total = props.compliance.active + props.compliance.ready_for_disposal + props.compliance.disposed;
    if (total === 0) return 100;
    return Math.round((props.compliance.active / total) * 100);
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }]">
        <Head title="Reports" />

        <div class="flex flex-col gap-6 p-4 sm:p-6">

            <!-- Page title -->
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <BarChart2 class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Reports Dashboard</h1>
                    <p class="text-sm text-muted-foreground">A live overview of your records system — updated in real time.</p>
                </div>
            </div>

            <!-- Attention Required Banner (shown when there are overdue/expiring items) -->
            <div
                v-if="hasAttentionItems"
                class="flex items-start gap-3 rounded-xl border border-rose-300/70 bg-rose-50 px-4 py-3.5 dark:border-rose-800/50 dark:bg-rose-950/40"
                role="alert"
            >
                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-rose-600 dark:text-rose-400" />
                <div class="flex-1">
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-300">Action Required</p>
                    <p class="mt-0.5 text-xs text-rose-700 dark:text-rose-400">
                        {{ attentionMessage }} Review the
                        <a href="/reports/records-inventory" class="underline underline-offset-2 hover:no-underline">Records Inventory</a>
                        or
                        <a href="/reports/summary" class="underline underline-offset-2 hover:no-underline">Summary Report</a>
                        for details.
                    </p>
                </div>
            </div>

            <!-- F-bar 1: KPI stat cards -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
                <StatCard
                    label="Total Documents"
                    :value="props.stats.total_documents"
                    :icon="FileText"
                    card-class="border-border/60 bg-card shadow-sm"
                />
                <StatCard
                    label="Ready to Be Disposed"
                    :value="props.stats.ready_for_disposal_count"
                    :icon="AlertTriangle"
                    :icon-class="props.stats.ready_for_disposal_count > 0 ? 'text-amber-500' : 'text-muted-foreground'"
                    :color-class="props.stats.ready_for_disposal_count > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-foreground'"
                    :card-class="props.stats.ready_for_disposal_count > 0 ? 'border-amber-300/60 bg-amber-50/80 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/40' : 'border-border/60 bg-card shadow-sm'"
                />
                <StatCard
                    label="Active Records"
                    :value="props.stats.active_count"
                    :icon="ShieldCheck"
                    icon-class="text-emerald-500"
                    color-class="text-emerald-600 dark:text-emerald-400"
                    card-class="border-border/60 bg-card shadow-sm"
                />
                <StatCard
                    label="Awaiting Retrieval"
                    :value="props.stats.pending_requests"
                    :icon="Archive"
                    icon-class="text-blue-500"
                    card-class="border-border/60 bg-card shadow-sm"
                />
                <StatCard
                    label="Documents Disposed This Month"
                    :value="props.stats.disposed_this_month"
                    :icon="TrendingUp"
                    icon-class="text-slate-400"
                    color-class="text-slate-600 dark:text-slate-300"
                    card-class="border-border/60 bg-card shadow-sm col-span-2 sm:col-span-1"
                />
            </div>

            <!-- F-bar 2: Main charts -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Activity trend (2/3) -->
                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-5 lg:col-span-2">
                    <div class="mb-1 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Document Activity — Last 30 Days</p>
                        <span class="text-[11px] text-muted-foreground/60">Daily uploads</span>
                    </div>
                    <ReportChart
                        type="area"
                        :series="[{ name: 'Documents Added', data: trendCounts }]"
                        :categories="trendDates"
                        :height="220"
                        :colors="['#3b82f6']"
                    />
                    <p class="mt-1 text-[11px] italic text-muted-foreground/70">{{ trendInsight }}</p>
                </div>

                <!-- Compliance donut (1/3) -->
                <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-5">
                    <div class="mb-1 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Records Health</p>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            {{ complianceRate }}% active
                        </span>
                    </div>
                    <ReportChart
                        type="donut"
                        :series="[props.compliance.active, props.compliance.ready_for_disposal, props.compliance.disposed]"
                        :labels="['ACTIVE', 'READY TO BE DISPOSED', 'DISPOSED']"
                        :colors="['#10b981', '#f59e0b', '#ef4444']"
                        :height="220"
                    />
                </div>
            </div>

            <!-- F-bar 3: Division breakdown chart -->
            <div class="rounded-xl border border-border/60 bg-card p-4 shadow-sm sm:p-5">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Documents by Division</p>
                <ReportChart
                    type="bar"
                    :series="[{ name: 'Documents', data: props.by_department.map(d => d.count) }]"
                    :categories="props.by_department.map(d => d.label)"
                    :height="200"
                />
            </div>

            <!-- F-stem: Report navigation + compliance summary -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <!-- Report cards (2/3) -->
                <div class="xl:col-span-2">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Go to a Report</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-2">
                        <ReportCard
                            v-for="report in reports"
                            :key="report.href"
                            :title="report.title"
                            :description="report.description"
                            :href="report.href"
                            :icon="report.icon"
                        />
                    </div>
                </div>

                <!-- Compliance detail (1/3) -->
                <div class="rounded-xl border border-border/60 bg-card p-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <ShieldCheck class="h-4 w-4 text-emerald-500" />
                        <p class="text-sm font-semibold text-foreground">Compliance Breakdown</p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <!-- Active -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <span class="text-sm text-muted-foreground">Active</span>
                            </div>
                            <span class="text-sm font-semibold text-foreground">{{ props.compliance.active.toLocaleString() }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all"
                                :style="`width: ${complianceRate}%`"
                            ></div>
                        </div>

                        <!-- Ready -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
                                <span class="text-sm text-muted-foreground">Ready to be Disposed</span>
                            </div>
                            <span class="text-sm font-semibold text-amber-600 dark:text-amber-400">{{ props.compliance.ready_for_disposal.toLocaleString() }}</span>
                        </div>

                        <!-- Disposed -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-500 shrink-0"></span>
                                <span class="text-sm text-muted-foreground">Disposed</span>
                            </div>
                            <span class="text-sm font-semibold text-rose-600 dark:text-rose-400">{{ props.compliance.disposed.toLocaleString() }}</span>
                        </div>

                        <div class="mt-2 border-t border-border/60 pt-3">
                            <p class="text-[11px] text-muted-foreground/70">
                                <template v-if="props.compliance.ready_for_disposal > 0">
                                    {{ props.compliance.ready_for_disposal }} document{{ props.compliance.ready_for_disposal !== 1 ? 's' : '' }} are READY TO BE DISPOSED and await disposal action.
                                </template>
                                <template v-else>
                                    No documents are ready for disposal. Great work!
                                </template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
