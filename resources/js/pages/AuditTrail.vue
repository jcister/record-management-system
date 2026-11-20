<script setup lang="ts">
import DatePicker from '@/components/common/DatePicker.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { parseDate } from '@internationalized/date';
import { Activity, Download, Filter, RefreshCw, Search, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import AuditLogDetailSheet from '@/components/audit/AuditLogDetailSheet.vue';
import AuditLogTableSkeleton from '@/components/audit/AuditLogTableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types';
import type { AuditLog } from '@/types/schema';

type ActionGroup = {
    label: string;
    actions: string[];
};

const props = defineProps<{
    logs: {
        data: AuditLog[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        action?: string;
        user_id?: string;
        auditable_type?: string;
        search?: string;
        from?: string | null;
        to?: string | null;
        my_activity?: boolean;
    };
    actionGroups: ActionGroup[];
    users: { id: string; name: string | null }[];
    subjects: string[];
}>();

const page = usePage();
const auth = computed(() => page.props.auth as Auth);
const currentUserId = computed(() => auth.value?.user?.id ?? null);

const showFilters = ref(
    Boolean(
        props.filters.action ||
        props.filters.user_id ||
        props.filters.auditable_type ||
        props.filters.from ||
        props.filters.to,
    ),
);

const filterState = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    user_id: props.filters.user_id ?? '',
    auditable_type: props.filters.auditable_type ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    my_activity: props.filters.my_activity ?? false,
});

const hasActiveFilters = computed(() =>
    Boolean(
        filterState.search ||
        filterState.action ||
        filterState.user_id ||
        filterState.auditable_type ||
        filterState.from ||
        filterState.to ||
        filterState.my_activity,
    ),
);

const minDateForTo = computed(() => {
    if (!filterState.from || filterState.from.length !== 10) return undefined;
    const parts = filterState.from.split('/');
    if (parts.length !== 3) return undefined;
    try {
        const [month, day, year] = parts;
        return parseDate(
            `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`,
        );
    } catch {
        return undefined;
    }
});

const actionLabels: Record<string, string> = {
    file_created: 'Created file',
    file_updated: 'Updated file',
    file_approved: 'Approved file',
    file_rejected: 'Rejected file',
    file_locked: 'Locked file',
    file_unlocked: 'Unlocked file',
    file_visibility_changed: 'Visibility changed',
    file_deleted: 'Moved to trash',
    file_restored: 'Restored file',
    file_force_deleted: 'Deleted permanently',
    file_downloaded: 'Downloaded file',
    file_viewed: 'Viewed file',
    file_version_restored: 'Restored version',
    file_version_created: 'New version',
    file_ocr_completed: 'OCR completed',
    file_ocr_failed: 'OCR failed',
    chunked_upload_assembled: 'Chunked upload assembled',
    chunked_upload_failed: 'Chunked upload failed',
    folder_created: 'Created folder',
    folder_updated: 'Updated folder',
    folder_moved: 'Moved folder',
    folder_deleted: 'Archived folder',
    folder_restored: 'Restored folder',
    folder_force_deleted: 'Deleted folder permanently',
    request_created: 'Created request',
    request_status_changed: 'Changed request status',
    request_file_attached: 'Attached request file',
    request_file_detached: 'Detached request file',
    request_scan_uploaded: 'Uploaded request scan',
    role_created: 'Created role',
    role_updated: 'Updated role',
    role_deleted: 'Deleted role',
    permission_assigned: 'Assigned permission',
    permission_revoked: 'Revoked permission',
    user_created: 'Created user',
    user_deleted: 'Deleted user',
    user_updated: 'Updated user',
    profile_updated: 'Updated profile',
    password_updated: 'Updated password',
    login: 'Signed in',
    login_success: 'Signed in',
    logout: 'Signed out',
    login_failed: 'Failed sign-in',
    password_reset: 'Password reset',
    email_verified: 'Email verified',
    disposal_scheduled: 'Scheduled disposal',
    disposal_completed: 'Completed disposal',
    disposal_inspected: 'Inspected disposal',
    disposal_approved: 'Approved disposal',
    disposal_rejected: 'Rejected disposal',
    disposal_unarchive: 'Unarchived from disposal',
};

const subjectLabels: Record<string, string> = {
    'App\\Models\\File': 'File',
    'App\\Models\\Folder': 'Folder',
    'App\\Models\\Requests': 'Request',
    'App\\Models\\User': 'User',
    'App\\Models\\Role': 'Role',
    'App\\Models\\Permission': 'Permission',
    'App\\Models\\Institution': 'Institution',
    'App\\Models\\Department': 'Department',
    'App\\Models\\DisposalBatch': 'Disposal Batch',
    'App\\Models\\DisposalBatchFile': 'Disposal File',
};

const formatAction = (action: string) =>
    actionLabels[action] ??
    action.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

const formatSubject = (auditableType?: string | null) => {
    if (!auditableType) return '—';
    return (
        subjectLabels[auditableType] ??
        auditableType.split('\\').pop() ??
        auditableType
    );
};

const formatTimestamp = (value?: string | null) => {
    if (!value) return '—';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '—';
    return parsed.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const truncateText = (text: string, max: number): string =>
    text.length > max ? `${text.slice(0, max)}…` : text;

const summarizeProperties = (log: AuditLog): string => {
    if (!log.properties) return '—';

    const p = log.properties as Record<string, unknown>;

    if (p.old_status && p.new_status) {
        return `${p.old_status} → ${p.new_status}`;
    }

    if (p.old_visibility && p.new_visibility) {
        return `${p.old_visibility} → ${p.new_visibility}`;
    }

    if (p.changes && typeof p.changes === 'object') {
        const changeEntries = Object.entries(
            p.changes as Record<string, { from?: unknown; to?: unknown }>,
        );
        if (changeEntries.length === 0) return '—';
        return changeEntries
            .slice(0, 3)
            .map(
                ([field, change]) =>
                    `${field}: ${change.from ?? '—'} → ${change.to ?? '—'}`,
            )
            .join('\n');
    }

    if (p.file_name && typeof p.file_name === 'string') return p.file_name;
    if (p.request_title && typeof p.request_title === 'string')
        return p.request_title;

    if (
        log.action === 'file_ocr_completed' &&
        typeof p.text_length === 'number'
    ) {
        return `Extracted ${p.text_length.toLocaleString()} characters`;
    }

    if (log.action === 'file_ocr_failed') {
        return typeof p.error === 'string' ? p.error : 'Processing failed';
    }

    return '—';
};

const selectedLog = ref<AuditLog | null>(null);
const detailOpen = ref(false);
const isLoading = ref(true);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});

const handleRowClick = (log: AuditLog): void => {
    selectedLog.value = log;
    detailOpen.value = true;
};

const applyFilters = () => {
    router.get(
        '/audit-logs',
        { ...filterState },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    filterState.search = '';
    filterState.action = '';
    filterState.user_id = '';
    filterState.auditable_type = '';
    filterState.from = '';
    filterState.to = '';
    filterState.my_activity = false;
    applyFilters();
};

const toggleMyActivity = () => {
    filterState.my_activity = !filterState.my_activity;
    if (filterState.my_activity) {
        // Clear user filter when switching to My Activity
        filterState.user_id = '';
    }
    applyFilters();
};

const buildQueryString = () => {
    const params = new URLSearchParams();

    Object.entries(filterState).forEach(([key, value]) => {
        if (value) {
            params.set(key, String(value));
        }
    });

    return params.toString();
};

const exportCsv = () => {
    const query = buildQueryString();
    const url = query ? `/audit-logs/export?${query}` : '/audit-logs/export';
    window.open(url, '_blank');
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        {
            title: 'Audit Trail',
            description: 'View and manage audit logs',
            href: '/audit-logs',
        },
    ]">

        <Head title="Audit Trail" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Search and Filters -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative w-full flex-1">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="filterState.search" placeholder="Search by Action, Subject, User..."
                            class="pl-9 placeholder-black" @keydown.enter.prevent="applyFilters" />
                    </div>

                    <!-- My Activity toggle -->
                    <Button
                        variant="outline"
                        @click="toggleMyActivity"
                        :class="[
                            'flex w-full items-center justify-center gap-2 whitespace-nowrap sm:w-auto',
                            {
                                'border-primary bg-primary/10 text-primary hover:bg-primary/20': filterState.my_activity,
                                'cursor-pointer': true,
                            },
                        ]"
                        :title="filterState.my_activity ? 'Showing your activity only — click to clear' : 'Show only your activity'"
                    >
                        <Activity class="h-4 w-4 shrink-0" />
                        <span class="xs:inline hidden sm:inline">My Activity</span>
                    </Button>

                    <Button
                        variant="outline"
                        @click="showFilters = !showFilters"
                        :class="[
                            'flex w-full items-center justify-center gap-2 whitespace-nowrap sm:w-auto',
                            {
                                'bg-accent': hasActiveFilters && !filterState.my_activity,
                                'cursor-pointer': true,
                            },
                        ]"
                    >
                        <Filter class="h-4 w-4 shrink-0" />
                        <span class="xs:inline hidden sm:inline">Filters</span>
                        <span v-if="hasActiveFilters"
                            class="ml-1 inline-flex items-center justify-center rounded-full bg-primary/10 px-2 py-1 text-xs text-primary hover:bg-primary/20"
                            @click.stop="clearFilters" title="Clear Filters">
                            <X class="h-3 w-3" />
                            <span class="sr-only">Clear Filters</span>
                        </span>
                    </Button>
                    <Button variant="outline" @click="applyFilters" class="cursor-pointer">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                    <Button @click="exportCsv" class="cursor-pointer">
                        <Download class="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                </div>

                <!-- My Activity banner -->
                <div
                    v-if="filterState.my_activity"
                    class="flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-4 py-2 text-sm text-primary"
                >
                    <Activity class="h-4 w-4 shrink-0" />
                    <span>Showing your activity only</span>
                </div>

                <!-- Filter Panel -->
                <div
                    v-if="showFilters && !filterState.my_activity"
                    class="grid grid-cols-1 gap-4 rounded-lg border bg-muted/30 p-4 md:grid-cols-5"
                >
                    <div class="flex flex-col gap-2">
                        <Label>Action</Label>
                        <Select v-model="filterState.action">
                            <SelectTrigger
                                class="w-full cursor-pointer hover:bg-secondary/80"
                            >
                                <SelectValue placeholder="All Actions" />
                            </SelectTrigger>
                            <SelectContent>
                                <template
                                    v-for="group in actionGroups"
                                    :key="group.label"
                                >
                                    <SelectGroup>
                                        <SelectLabel>{{ group.label }}</SelectLabel>
                                        <SelectItem
                                            v-for="action in group.actions"
                                            :key="action"
                                            :value="action"
                                        >
                                            {{ formatAction(action) }}
                                        </SelectItem>
                                    </SelectGroup>
                                </template>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>Subject</Label>
                        <Select v-model="filterState.auditable_type">
                            <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                                <SelectValue class="placeholder-black" placeholder="All Subjects" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="subject in subjects" :key="subject" :value="subject">
                                    {{ formatSubject(subject) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>User</Label>
                        <Select v-model="filterState.user_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All Users" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name ?? 'Unknown user' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2 md:col-span-2">
                        <Label>Date Range</Label>
                        <div class="flex items-center gap-2">
                            <DatePicker class="w-full flex-1" :model-value="filterState.from" @update:model-value="
                                (val) => (filterState.from = val || '')
                            " placeholder="MM/DD/YYYY" :allow-manual-entry="true" />
                            <span class="text-muted-foreground">→</span>
                            <DatePicker class="flex-1" :model-value="filterState.to" @update:model-value="
                                (val) => (filterState.to = val || '')
                            " placeholder="MM/DD/YYYY" :allow-manual-entry="true" :disabled="!filterState.from"
                                :min-value="minDateForTo" />
                        </div>
                    </div>
                </div>

                <!-- Results count -->
                <div v-if="hasActiveFilters" class="text-sm text-muted-foreground">
                    Showing {{ logs.data.length }} of {{ logs.total }} Entries
                </div>
            </div>

            <div v-if="!isLoading" class="flex items-center justify-between text-xs text-muted-foreground">
                <div>
                    Showing {{ logs.data.length }} of {{ logs.total }} Entries
                </div>
                <div>Page {{ logs.current_page }} / {{ logs.last_page }}</div>
            </div>

            <!-- Desktop View -->
            <div class="hidden flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm md:flex">
                <AuditLogTableSkeleton v-if="isLoading" :rows="8" />
                <div v-else-if="logs.data.length" class="flex h-full flex-col">
                    <div class="w-full">
                        <Table class="w-full table-fixed">
                            <TableHeader class="sticky top-0 z-10 bg-background">
                                <TableRow class="border-b">
                                    <TableHead
                                        class="max-w-xs overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm">
                                        Timestamp</TableHead>
                                    <TableHead
                                        class="max-w-xs overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm">
                                        User</TableHead>
                                    <TableHead
                                        class="max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-2xl md:py-3 md:text-sm">
                                        Action</TableHead>
                                    <TableHead
                                        class="hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell">
                                        Subject</TableHead>
                                    <TableHead
                                        class="max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-md md:py-3 md:text-sm">
                                        Details</TableHead>
                                    <TableHead
                                        class="hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell">
                                        IP Address</TableHead>
                                </TableRow>
                            </TableHeader>
                        </Table>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <Table class="w-full table-fixed">
                            <TableBody>
                                <TableRow v-for="log in logs.data" :key="log.id"
                                    class="cursor-pointer transition-colors even:bg-muted/30 hover:bg-muted/50"
                                    tabindex="0" role="button"
                                    :aria-label="`View details for ${formatAction(log.action)}`"
                                    @click="handleRowClick(log)" @keydown.enter.prevent="handleRowClick(log)"
                                    @keydown.space.prevent="handleRowClick(log)">
                                    <TableCell
                                        class="max-w-xs truncate overflow-hidden py-2 text-left text-xs whitespace-nowrap md:max-w-sm md:py-3 md:text-sm">
                                        {{ formatTimestamp(log.created_at) }}
                                    </TableCell>
                                    <TableCell
                                        class="max-w-xs truncate overflow-hidden py-2 text-left text-xs md:max-w-sm md:py-3 md:text-sm">
                                        {{
                                            log.user_name ||
                                            log.user?.name ||
                                            '—'
                                        }}
                                    </TableCell>
                                    <TableCell
                                        class="max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-2xl md:py-3 md:text-sm">
                                        <Badge variant="secondary" class="text-xs whitespace-nowrap md:text-sm"
                                            :title="formatAction(log.action)">
                                            {{
                                                truncateText(
                                                    formatAction(log.action),
                                                    40,
                                                )
                                            }}
                                        </Badge>

                                        <div
                                            class="mt-1 line-clamp-2 overflow-hidden text-xs wrap-break-word whitespace-normal text-muted-foreground md:text-sm"
                                        >
                                            {{ log.description }}
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="hidden overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell">
                                        <div class="truncate font-medium" :title="log.auditable_name ?? undefined
                                            ">
                                            {{
                                                truncateText(
                                                    log.auditable_name ?? '—',
                                                    40,
                                                )
                                            }}
                                        </div>
                                        <div class="truncate text-muted-foreground">
                                            {{
                                                formatSubject(
                                                    log.auditable_type,
                                                )
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="max-w-sm overflow-hidden py-2 text-left text-xs md:max-w-md md:py-3 md:text-sm"
                                        :title="summarizeProperties(log)">
                                        <div class="truncate">
                                            {{
                                                truncateText(
                                                    summarizeProperties(log),
                                                    80,
                                                )
                                            }}
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="hidden truncate overflow-hidden py-2 text-left text-xs md:py-3 md:text-sm lg:table-cell">
                                        {{ log.ip_address || '—' }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <Filter class="mb-4 h-24 w-24 text-muted-foreground/50" />
                    <h2 class="mb-2 text-xl font-semibold">
                        {{
                            hasActiveFilters
                                ? 'No Matching Entries'
                                : 'No Audit Entries'
                        }}
                    </h2>
                    <p class="max-w-md text-muted-foreground">
                        {{
                            hasActiveFilters
                                ? 'Adjust Filters or Try a Broader Search.'
                                : 'No Audit Entries Available.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Mobile View -->
            <div v-if="logs.data.length > 0" class="flex flex-1 flex-col gap-3 overflow-y-auto md:hidden">
                <div v-for="log in logs.data" :key="log.id"
                    class="cursor-pointer rounded-lg border bg-card p-4 shadow-sm transition-colors hover:bg-muted/50"
                    tabindex="0" role="button" :aria-label="`View details for ${formatAction(log.action)}`"
                    @click="handleRowClick(log)" @keydown.enter.prevent="handleRowClick(log)">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <p class="text-xs text-muted-foreground">
                                {{ formatTimestamp(log.created_at) }}
                            </p>
                            <Badge variant="secondary" class="mt-1.5 text-xs">{{
                                formatAction(log.action)
                                }}</Badge>
                        </div>
                    </div>
                    <div class="space-y-1.5 text-xs text-muted-foreground">
                        <div class="flex justify-between gap-2">
                            <span>User:</span>
                            <span class="text-right font-medium text-foreground">{{
                                log.user_name || log.user?.name || '—'
                            }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span>Subject:</span>
                            <div class="text-right">
                                <div class="font-medium text-foreground">
                                    {{ log.auditable_name || '—' }}
                                </div>
                                <div class="text-muted-foreground">
                                    {{ formatSubject(log.auditable_type) }}
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span>Description:</span>
                            <span class="text-sm font-medium text-foreground">{{
                                log.description
                                }}</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span>Details:</span>
                            <span
                                class="text-sm font-medium wrap-break-word text-foreground"
                                >{{ summarizeProperties(log) }}</span
                            >
                        </div>
                        <div class="flex justify-between gap-2">
                            <span>IP Address:</span>
                            <span class="font-medium text-foreground">{{
                                log.ip_address || '—'
                                }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-20 text-center md:hidden">
                <Filter class="mb-4 h-24 w-24 text-muted-foreground/50" />
                <h2 class="mb-2 text-xl font-semibold">
                    {{
                        hasActiveFilters
                            ? 'No Matching Entries'
                            : 'No Audit Entries'
                    }}
                </h2>
                <p class="max-w-md text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'Adjust Filters or Try a Broader Search.'
                            : 'No Audit Entries Available.'
                    }}
                </p>
            </div>

            <div v-if="logs.links?.length && !isLoading" class="flex flex-wrap items-center gap-1.5">
                <Button v-for="link in logs.links" :key="link.label" :disabled="!link.url" variant="outline" size="sm"
                    class="h-8 min-w-8 px-2 text-xs" :class="{
                        'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground dark:bg-white dark:text-black dark:hover:bg-white dark:hover:text-black':
                            link.active,
                    }" @click="
                        link.url &&
                        router.visit(link.url, {
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                        })
                        ">
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>

        <!-- Audit Entry Detail Panel -->
        <AuditLogDetailSheet :log="selectedLog" :open="detailOpen" @update:open="detailOpen = $event" />
    </AppLayout>
</template>
