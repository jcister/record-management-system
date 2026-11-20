<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ClipboardList,
    ChevronsRight,
    Database,
    Download,
    FileText,
    FilePlus,
    Folder,
    FolderOpen,
    ShieldAlert,
    Upload,
    Zap,
    BrainCircuit,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import ActivityFeed from '@/components/dashboard/ActivityFeed.vue';
import DashboardChartSkeleton from '@/components/dashboard/DashboardChartSkeleton.vue';
import FilesUploadTrendChart from '@/components/dashboard/FilesUploadTrendChart.vue';
import QuickAccessShortcuts, { type ShortcutItem } from '@/components/dashboard/QuickAccessShortcuts.vue';
import RequestsStatusChart from '@/components/dashboard/RequestsStatusChart.vue';
import SparklineMetricCard from '@/components/dashboard/SparklineMetricCard.vue';
import StorageGaugeChart from '@/components/dashboard/StorageGaugeChart.vue';
import FilePreview from '@/components/records/FilePreview.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { formatBytes } from '@/utils/chartConfig';

interface FolderItem {
    id: string;
    name: string;
    created_at: string;
    parent?: { id: string; name: string } | null;
}

interface FileItem {
    id: string;
    file_name: string;
    file_size: number;
    mime_type?: string | null;
    created_at: string;
}

interface Activity {
    id: string;
    type: 'audit' | 'request_log';
    user_name: string;
    action: string;
    description: string;
    resource_name: string;
    resource_type: string | null;
    created_at: string;
    ip_address?: string | null;
    severity?: 'success' | 'warning' | 'danger';
}

interface MonitoringCardData {
    key: string;
    label: string;
    value: number;
    value_suffix: string;
    trend_label: string;
    series: number[];
}

interface ModelMonitoringData {
    sparkline_days: number;
    cards: MonitoringCardData[];
}

interface RequestStatusData {
    status: string;
    count: number;
}

interface FilesTrendData {
    date: string;
    count: number;
}

interface AdminQuickAccessData {
    pending_approvals: number;
    recent_uploads: number;
    audit_logs_total: number;
}

interface UserQuickAccessData {
    my_pending_requests: number;
    my_files_count: number;
    my_requests_total: number;
}

interface DashboardData {
    model_monitoring: ModelMonitoringData;
    metrics: {
        total_files: number;
        total_folders: number;
        total_requests: number;
        total_users: number;
    };
    storage: {
        total_storage: number;
        storage_capacity: number;
        used_percentage: number;
        available_storage: number;
    };
    pending_requests: {
        pending_count: number;
        processing_count: number;
        for_release_count: number;
        total_active: number;
    };
    requests_by_status: RequestStatusData[];
    files_upload_trend: FilesTrendData[];
    top_active_users: unknown[];
    recent_activities: Activity[];
    quick_access: AdminQuickAccessData;
}

interface Props {
    isAdmin?: boolean;
    dashboardData?: DashboardData;
    folders: FolderItem[];
    files: FileItem[];
    recent_activities?: Activity[];
    quick_access?: UserQuickAccessData;
}

const props = withDefaults(defineProps<Props>(), {
    isAdmin: false,
    dashboardData: undefined,
    recent_activities: () => [],
    quick_access: () => ({ my_pending_requests: 0, my_files_count: 0, my_requests_total: 0 }),
});

// --- Chart loading state ---
const areChartsLoading = ref(true);
let minLoadingVisibleUntil = 0;
let hideLoadingTimeout: ReturnType<typeof setTimeout> | null = null;

const startLoading = () => {
    if (hideLoadingTimeout) {
        clearTimeout(hideLoadingTimeout);
        hideLoadingTimeout = null;
    }
    areChartsLoading.value = true;
    minLoadingVisibleUntil = Date.now() + 350;
};

const stopLoading = () => {
    const remaining = minLoadingVisibleUntil - Date.now();
    if (remaining > 0) {
        hideLoadingTimeout = setTimeout(() => {
            areChartsLoading.value = false;
            hideLoadingTimeout = null;
        }, remaining);
    } else {
        areChartsLoading.value = false;
    }
};

onMounted(() => {
    startLoading();
    stopLoading();
});

const removeStartListener = router.on('start', () => startLoading());
const removeFinishListener = router.on('finish', () => stopLoading());

onBeforeUnmount(() => {
    if (hideLoadingTimeout) clearTimeout(hideLoadingTimeout);
    removeStartListener();
    removeFinishListener();
});

// --- Breadcrumbs ---
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Overview',
        description: 'Your dashboard overview of records, requests, and system activity',
        href: dashboard().url,
    },
];

// --- Formatters ---
const formatFileSize = (bytes: number): string => formatBytes(bytes);

const formatDate = (date: string): string =>
    new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

// --- Admin metric card color — keyed by position, not by ML key name ---
const METRIC_COLORS = ['success', 'primary', 'warning', 'danger'] as const;

const getMonitoringColorVariant = (index: number) =>
    METRIC_COLORS[index % METRIC_COLORS.length];

// --- Admin quick action shortcuts ---
const adminShortcuts = computed((): ShortcutItem[] => {
    const qa = props.dashboardData?.quick_access;
    const pendingCount = qa?.pending_approvals ?? 0;

    return [
        {
            title: 'Process Pending Requests',
            description: 'Review and act on requests waiting for a response.',
            icon: Zap,
            route: '/requests?status=pending',
            badgeLabel: pendingCount > 0 ? `${pendingCount} waiting` : undefined,
            variant: pendingCount > 0 ? 'warning' : 'normal',
            colorClass: 'text-blue-500',
        },
        {
            title: 'Custom Reports',
            description: 'Build and export reports on files and requests.',
            icon: BrainCircuit,
            route: '/reports/custom',
            badgeLabel: qa?.recent_uploads ? `${qa.recent_uploads} new files` : undefined,
            variant: 'normal',
            colorClass: 'text-purple-500',
        },
        {
            title: 'Audit Trail Logs',
            description: 'See everything that has happened in the system.',
            icon: ShieldAlert,
            route: '/audit-logs',
            badgeLabel: qa?.audit_logs_total ? `${qa.audit_logs_total} entries` : undefined,
            variant: 'normal',
            colorClass: 'text-amber-500',
        },
    ];
});

// --- Non-admin quick action shortcuts ---
const nonAdminShortcuts = computed((): ShortcutItem[] => {
    const qa = props.quick_access;
    const pendingCount = qa?.my_pending_requests ?? 0;

    return [
        {
            title: 'Browse Records',
            description: 'Search and view all files and folders.',
            icon: FolderOpen,
            route: '/records',
            variant: 'normal',
            colorClass: 'text-blue-500',
        },
        {
            title: 'Submit a Request',
            description: 'Ask for a copy or access to a document.',
            icon: FilePlus,
            route: '/requests/create',
            variant: 'normal',
            colorClass: 'text-purple-500',
        },
        {
            title: 'My Requests',
            description: 'Track the status of your document requests.',
            icon: ClipboardList,
            route: '/requests?mine=1',
            badgeLabel: pendingCount > 0 ? `${pendingCount} awaiting response` : undefined,
            variant: pendingCount > 0 ? 'warning' : 'normal',
            colorClass: 'text-amber-500',
        },
        {
            title: 'Recently Uploaded',
            description: 'Files added to the system in the last 30 days.',
            icon: Upload,
            route: '/records?sort=recent',
            badgeLabel: qa?.my_files_count ? `${qa.my_files_count} by you` : undefined,
            variant: 'normal',
            colorClass: 'text-green-500',
        },
    ];
});

// --- Total file size for non-admin stat card ---
const totalFileSize = computed(() =>
    formatFileSize(props.files.reduce((sum, f) => sum + f.file_size, 0)),
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <!-- ═══════════════════════════════════════════════════════ -->
        <!--  ADMIN DASHBOARD  (F-Pattern)                          -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div v-if="isAdmin && dashboardData" class="flex h-full flex-1 flex-col gap-4 sm:gap-6 p-3 sm:p-4 md:p-6">

            <!-- ── Row 1 · Top horizontal scan: Key Metric Cards ── -->
            <div class="grid gap-3 sm:gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SparklineMetricCard
                    v-for="(metric, index) in dashboardData.model_monitoring.cards"
                    :key="metric.key"
                    :label="metric.label"
                    :value="metric.value"
                    :value-suffix="metric.value_suffix"
                    :trend-label="metric.trend_label"
                    :series="metric.series"
                    :loading="areChartsLoading"
                    :color-variant="getMonitoringColorVariant(index)"
                />
            </div>

            <!-- ── Row 2 · Second horizontal scan: Quick Actions ── -->
            <QuickAccessShortcuts
                :shortcuts="adminShortcuts"
                title="Quick Actions"
                description="Jump straight to the tasks that need your attention."
            />

            <!-- ── Row 3 · Left vertical stroke + right sidebar ── -->
            <div class="grid items-start gap-3 sm:gap-4 xl:grid-cols-12">

                <!-- Left: Recent Activities (wide) -->
                <Card class="xl:col-span-8 2xl:col-span-9">
                    <CardHeader class="p-4 sm:p-6 pb-2 sm:pb-3">
                        <CardTitle class="text-base sm:text-lg">Recent Activities</CardTitle>
                        <CardDescription class="text-xs sm:text-sm">
                            A timeline of everything that has happened — newest first.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-4 sm:p-6 pt-0 pb-4">
                        <ActivityFeed :activities="dashboardData.recent_activities" />
                    </CardContent>
                </Card>

                <!-- Right: Charts sidebar -->
                <div class="flex flex-col gap-3 sm:gap-4 xl:col-span-4 2xl:col-span-3">
                    <Card>
                        <CardHeader class="p-4 sm:p-6">
                            <CardTitle class="text-base sm:text-lg">Requests by Status</CardTitle>
                            <CardDescription class="text-xs sm:text-sm">
                                Where each request currently stands.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 sm:p-6 pt-0">
                            <DashboardChartSkeleton v-if="areChartsLoading" variant="donut" />
                            <RequestsStatusChart
                                v-else
                                :requests-by-status="dashboardData.requests_by_status"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="p-4 sm:p-6">
                            <CardTitle class="text-base sm:text-lg">Storage Capacity</CardTitle>
                            <CardDescription class="text-xs sm:text-sm">
                                How much disk space has been used so far.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 sm:p-6 pt-0">
                            <DashboardChartSkeleton v-if="areChartsLoading" variant="gauge" />
                            <StorageGaugeChart
                                v-else
                                :total-storage="Number(dashboardData.storage.total_storage)"
                                :storage-capacity="Number(dashboardData.storage.storage_capacity)"
                                :used-percentage="Number(dashboardData.storage.used_percentage)"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ── Row 4 · Files Upload Trend ── -->
            <Card>
                <CardHeader class="p-4 sm:p-6">
                    <CardTitle class="text-base sm:text-lg">Files Upload Trend</CardTitle>
                    <CardDescription class="text-xs sm:text-sm">
                        How many files were uploaded each day over the last 30 days.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4 sm:p-6 pt-0">
                    <DashboardChartSkeleton v-if="areChartsLoading" variant="trend" />
                    <FilesUploadTrendChart
                        v-else
                        :files-upload-trend="dashboardData.files_upload_trend"
                    />
                </CardContent>
            </Card>

            <!-- ── Row 5 · Helpful Links (inline, no extra card weight) ── -->
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-muted-foreground text-xs font-medium uppercase tracking-wide mr-2">Quick links</span>
                <Link
                    href="/reports/summary"
                    class="rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent"
                >
                    Summary Reports
                </Link>
                <Link
                    href="/reports/storage-utilization"
                    class="rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent"
                >
                    Storage Utilization
                </Link>
                <Link
                    href="/reports/document-access-frequency"
                    class="rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent"
                >
                    Document Access Frequency
                </Link>
                <Link
                    href="/audit-logs"
                    class="rounded-md border px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent"
                >
                    Audit Trail Logs
                </Link>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!--  NON-ADMIN DASHBOARD  (F-Pattern)                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div v-else class="flex h-full flex-1 flex-col gap-4 sm:gap-6 p-3 sm:p-4 md:p-6">

            <!-- ── Row 1 · Top horizontal scan: Summary Stats ── -->
            <div class="grid auto-rows-min gap-3 sm:gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2 p-4 sm:p-5">
                        <CardTitle class="text-sm font-medium">My Folders</CardTitle>
                        <Folder class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent class="p-4 sm:p-5 pt-0">
                        <div class="text-2xl font-bold">{{ folders.length }}</div>
                        <p class="text-xs text-muted-foreground mt-0.5">Recently created</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2 p-4 sm:p-5">
                        <CardTitle class="text-sm font-medium">My Files</CardTitle>
                        <FileText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent class="p-4 sm:p-5 pt-0">
                        <div class="text-2xl font-bold">{{ files.length }}</div>
                        <p class="text-xs text-muted-foreground mt-0.5">Recently uploaded</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2 p-4 sm:p-5">
                        <CardTitle class="text-sm font-medium">My Requests</CardTitle>
                        <ClipboardList class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent class="p-4 sm:p-5 pt-0">
                        <div class="text-2xl font-bold">{{ quick_access?.my_requests_total ?? 0 }}</div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            <span v-if="(quick_access?.my_pending_requests ?? 0) > 0" class="text-amber-600 dark:text-amber-400 font-medium">
                                {{ quick_access.my_pending_requests }} awaiting response
                            </span>
                            <span v-else>All requests up to date</span>
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Row 2 · Second horizontal scan: Quick Actions ── -->
            <QuickAccessShortcuts
                :shortcuts="nonAdminShortcuts"
                title="Quick Actions"
                description="Get to the most common tasks in one click."
            />

            <!-- ── Row 3 · Left: Recent Activity · Right: Recent Files ── -->
            <div class="grid items-start gap-3 sm:gap-4 xl:grid-cols-12">

                <!-- Left: Activity Feed -->
                <Card class="xl:col-span-7">
                    <CardHeader class="p-4 sm:p-6 pb-2 sm:pb-3">
                        <CardTitle class="text-base sm:text-lg">My Recent Activity</CardTitle>
                        <CardDescription class="text-xs sm:text-sm">
                            A log of your most recent actions in the system.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-4 sm:p-6 pt-0 pb-4">
                        <ActivityFeed :activities="recent_activities ?? []" />
                    </CardContent>
                </Card>

                <!-- Right: Recent Files + Folders -->
                <div class="flex flex-col gap-3 sm:gap-4 xl:col-span-5">
                    <!-- Recent Folders -->
                    <Card>
                        <CardHeader class="p-4 sm:p-5">
                            <CardTitle class="text-base">Recent Folders</CardTitle>
                            <CardDescription class="text-xs sm:text-sm">
                                Folders you recently created or accessed.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 sm:p-5 pt-0">
                            <div v-if="folders.length > 0" class="space-y-1.5">
                                <div
                                    v-for="folder in folders.slice(0, 5)"
                                    :key="folder.id"
                                    @click="router.visit(`/records/${folder.id}`)"
                                    class="flex items-center gap-2.5 rounded-lg border p-2.5 hover:bg-accent transition-colors cursor-pointer"
                                >
                                    <Folder class="h-4 w-4 text-blue-500 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-sm truncate">{{ folder.name }}</p>
                                        <p v-if="folder.parent" class="text-xs text-muted-foreground flex items-center gap-0.5 truncate">
                                            <ChevronsRight class="h-3 w-3 shrink-0" />
                                            <span>in {{ folder.parent.name }}</span>
                                        </p>
                                    </div>
                                    <span class="shrink-0 text-xs text-muted-foreground">
                                        {{ formatDate(folder.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                <Folder class="h-10 w-10 text-muted-foreground/40 mb-2" />
                                <p class="text-xs text-muted-foreground">No folders yet. Create one to get started!</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Files -->
                    <Card>
                        <CardHeader class="p-4 sm:p-5">
                            <CardTitle class="text-base">Recent Files</CardTitle>
                            <CardDescription class="text-xs sm:text-sm">
                                Files you recently uploaded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-4 sm:p-5 pt-0">
                            <div v-if="files.length > 0" class="space-y-1.5">
                                <div
                                    v-for="file in files.slice(0, 5)"
                                    :key="file.id"
                                    class="flex items-center gap-2.5 rounded-lg border p-2.5 hover:bg-accent transition-colors"
                                >
                                    <div class="w-14 shrink-0">
                                        <FilePreview :file="file" dense />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium truncate text-sm">{{ file.file_name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatFileSize(file.file_size) }} · {{ formatDate(file.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                <FileText class="h-10 w-10 text-muted-foreground/40 mb-2" />
                                <p class="text-xs text-muted-foreground">No files yet. Upload one to get started!</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
