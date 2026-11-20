<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { parseDate } from '@internationalized/date';
import {
    Bell,
    BellRing,
    CheckCircle2,
    FileText,
    Filter,
    FolderClock,
    RefreshCw,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import DatePicker from '@/components/common/DatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { apiClient } from '@/lib/axios';
import type { AppNotification } from '@/types';

const props = defineProps<{
    notifications: {
        data: AppNotification[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        type?: string;
        status?: string;
        from?: string;
        to?: string;
        search?: string;
    };
    typeCounts: Record<string, number>;
    unreadCount: number;
}>();

const showFilters = ref(
    Boolean(
        props.filters.type ||
        props.filters.status ||
        props.filters.from ||
        props.filters.to,
    ),
);

const filterState = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const hasActiveFilters = computed(() =>
    Boolean(
        filterState.search ||
        filterState.type ||
        filterState.status ||
        filterState.from ||
        filterState.to,
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

const notificationTypes = [
    { value: 'new_request', label: 'New Request' },
    { value: 'request_status_updated', label: 'Request Status Updated' },
    { value: 'request_will_return_copy_changed', label: 'Return Copy Changed' },
    { value: 'scan_submitted_for_review', label: 'Scan Submitted' },
    { value: 'scan_approved', label: 'Scan Approved' },
    { value: 'scan_rejected', label: 'Scan Rejected' },
    { value: 'record_updated', label: 'Record Updated' },
    { value: 'record_disposal_approaching', label: 'Disposal Approaching' },
    { value: 'retention_duration_reached', label: 'Retention Expired' },
    { value: 'disposal_batch_schedule_reminder', label: 'Disposal Scheduled' },
];

const typeCategories = {
    all: { label: 'All', icon: Bell, types: [] as string[] },
    requests: {
        label: 'Requests',
        icon: FileText,
        types: [
            'new_request',
            'request_status_updated',
            'request_will_return_copy_changed',
        ],
    },
    scans: {
        label: 'Scans',
        icon: CheckCircle2,
        types: ['scan_submitted_for_review', 'scan_approved', 'scan_rejected'],
    },
    retention: {
        label: 'Retention',
        icon: FolderClock,
        types: [
            'record_disposal_approaching',
            'retention_duration_reached',
            'disposal_batch_schedule_reminder',
        ],
    },
};

const activeTab = ref('all');

const getCategoryCount = (category: keyof typeof typeCategories): number => {
    if (category === 'all') {
        return Object.values(props.typeCounts).reduce(
            (sum, count) => sum + count,
            0,
        );
    }
    return typeCategories[category].types.reduce(
        (sum, type) => sum + (props.typeCounts[type] ?? 0),
        0,
    );
};

const getTypeLabel = (type: string): string => {
    const found = notificationTypes.find((t) => t.value === type);
    return (
        found?.label ??
        type.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
    );
};

const getTypeBadgeVariant = (
    type: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (type.includes('rejected') || type.includes('disposal'))
        return 'destructive';
    if (type.includes('approved') || type === 'new_request') return 'default';
    return 'secondary';
};

const getUrgencyClass = (urgency?: string): string => {
    if (urgency === 'critical' || urgency === 'today') {
        return 'border-l-4 border-l-red-500';
    }
    if (urgency === 'warning' || urgency === 'upcoming') {
        return 'border-l-4 border-l-yellow-500';
    }
    return '';
};

const formatTimestamp = (value: string): string => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const diffMins = Math.floor((Date.now() - date.getTime()) / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
    if (diffMins < 10080) return `${Math.floor(diffMins / 1440)}d ago`;

    return date.toLocaleDateString();
};

const groupNotificationsByDate = (notifications: AppNotification[]) => {
    const groups: Record<string, AppNotification[]> = {};
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const thisWeekStart = new Date(today);
    thisWeekStart.setDate(thisWeekStart.getDate() - 7);

    notifications.forEach((notification) => {
        const date = new Date(notification.created_at);
        date.setHours(0, 0, 0, 0);

        let groupKey: string;
        if (date.getTime() === today.getTime()) {
            groupKey = 'Today';
        } else if (date.getTime() === yesterday.getTime()) {
            groupKey = 'Yesterday';
        } else if (date >= thisWeekStart) {
            groupKey = 'This Week';
        } else {
            groupKey = 'Earlier';
        }

        if (!groups[groupKey]) {
            groups[groupKey] = [];
        }
        groups[groupKey].push(notification);
    });

    return groups;
};

const groupedNotifications = computed(() =>
    groupNotificationsByDate(props.notifications.data),
);

const applyFilters = () => {
    router.get(
        '/notifications',
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
    filterState.type = '';
    filterState.status = '';
    filterState.from = '';
    filterState.to = '';
    activeTab.value = 'all';
    applyFilters();
};

const handleTabChange = (tab: string) => {
    activeTab.value = tab;
    if (tab === 'all') {
        filterState.type = '';
    } else {
        const category = typeCategories[tab as keyof typeof typeCategories];
        if (category && category.types.length > 0) {
            filterState.type = category.types.join(',');
        }
    }
    applyFilters();
};

const markAsRead = async (notification: AppNotification) => {
    if (!notification.read_at) {
        await apiClient.post(`/api/notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
    }

    if (notification.data.link) {
        router.visit(notification.data.link);
    }
};

const markAllAsRead = async () => {
    await apiClient.post('/api/notifications/read-all');
    router.reload({ only: ['notifications', 'unreadCount'] });
};

const getActorName = (notification: AppNotification): string | null => {
    const data = notification.data;
    return (
        data.actor ??
        data.approver ??
        data.rejector ??
        data.uploader ??
        data.requested_by ??
        null
    );
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Notifications',
                description: 'View and manage your notifications',
                href: '/notifications',
            },
        ]"
    >
        <Head title="Notifications" />

        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header with Tabs -->
            <div class="flex flex-col gap-4">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <Tabs
                        :model-value="activeTab"
                        @update:model-value="handleTabChange"
                        class="w-full sm:w-auto"
                    >
                        <TabsList>
                            <TabsTrigger
                                v-for="(category, key) in typeCategories"
                                :key="key"
                                :value="key"
                                class="flex items-center gap-2"
                            >
                                <component
                                    :is="category.icon"
                                    class="h-4 w-4"
                                />
                                <span class="hidden sm:inline">{{
                                    category.label
                                }}</span>
                                <Badge
                                    v-if="getCategoryCount(key) > 0"
                                    variant="secondary"
                                    class="ml-1 h-5 min-w-5 px-1.5 text-xs"
                                >
                                    {{
                                        getCategoryCount(key) > 99
                                            ? '99+'
                                            : getCategoryCount(key)
                                    }}
                                </Badge>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="unreadCount > 0"
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            @click="markAllAsRead"
                        >
                            <CheckCircle2 class="mr-2 h-4 w-4" />
                            Mark All Read
                        </Button>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="relative w-full flex-1">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filterState.search"
                            placeholder="Search notifications..."
                            class="pl-9"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <Button
                        variant="outline"
                        @click="showFilters = !showFilters"
                        :class="[
                            'flex w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap sm:w-auto',
                            { 'bg-accent': hasActiveFilters },
                        ]"
                    >
                        <Filter class="h-4 w-4 shrink-0" />
                        <span class="xs:inline hidden sm:inline">Filters</span>
                        <span
                            v-if="hasActiveFilters"
                            class="ml-1 inline-flex items-center justify-center rounded-full bg-primary/10 px-2 py-1 text-xs text-primary hover:bg-primary/20"
                            @click.stop="clearFilters"
                            title="Clear Filters"
                        >
                            <X class="h-3 w-3" />
                        </span>
                    </Button>
                    <Button
                        variant="outline"
                        @click="applyFilters"
                        class="cursor-pointer"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                <!-- Filter Panel -->
                <div
                    v-if="showFilters"
                    class="grid grid-cols-1 gap-4 rounded-lg border bg-muted/30 p-4 md:grid-cols-4"
                >
                    <div class="flex flex-col gap-2">
                        <Label>Type</Label>
                        <Select
                            v-model="filterState.type"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger
                                class="w-full cursor-pointer hover:bg-secondary/80"
                            >
                                <SelectValue placeholder="All Types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="type in notificationTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label>Status</Label>
                        <Select
                            v-model="filterState.status"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger
                                class="w-full cursor-pointer hover:bg-secondary/80"
                            >
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="unread">Unread</SelectItem>
                                <SelectItem value="read">Read</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex flex-col gap-2 md:col-span-2">
                        <Label>Date Range</Label>
                        <div class="flex items-center gap-2">
                            <DatePicker
                                class="w-full flex-1"
                                :model-value="filterState.from"
                                @update:model-value="
                                    (val) => {
                                        filterState.from = val || '';
                                        applyFilters();
                                    }
                                "
                                placeholder="MM/DD/YYYY"
                                :allow-manual-entry="true"
                            />
                            <span class="text-muted-foreground">→</span>
                            <DatePicker
                                class="flex-1"
                                :model-value="filterState.to"
                                @update:model-value="
                                    (val) => {
                                        filterState.to = val || '';
                                        applyFilters();
                                    }
                                "
                                placeholder="MM/DD/YYYY"
                                :allow-manual-entry="true"
                                :disabled="!filterState.from"
                                :min-value="minDateForTo"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex items-center justify-between text-xs text-muted-foreground"
            >
                <div>
                    Showing {{ notifications.data.length }} of
                    {{ notifications.total }} Notifications
                    <span v-if="unreadCount > 0" class="ml-2">
                        (<span class="font-medium text-primary"
                            >{{ unreadCount }} unread</span
                        >)
                    </span>
                </div>
                <div>
                    Page {{ notifications.current_page }} /
                    {{ notifications.last_page }}
                </div>
            </div>

            <!-- Desktop View -->
            <div
                class="hidden flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm md:flex"
            >
                <div
                    v-if="notifications.data.length"
                    class="flex h-full flex-col"
                >
                    <div class="flex-1 overflow-y-auto">
                        <template
                            v-for="(group, groupName) in groupedNotifications"
                            :key="groupName"
                        >
                            <div
                                class="sticky top-0 z-10 border-b bg-muted/50 px-4 py-2"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ groupName }}
                                </span>
                            </div>
                            <button
                                v-for="notification in group"
                                :key="notification.id"
                                class="flex w-full items-start gap-4 border-b px-4 py-4 text-left transition-colors hover:bg-muted/50"
                                :class="[
                                    !notification.read_at ? 'bg-primary/5' : '',
                                    getUrgencyClass(notification.data.urgency),
                                ]"
                                @click="markAsRead(notification)"
                            >
                                <div
                                    class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                    :class="
                                        notification.read_at
                                            ? 'bg-transparent'
                                            : 'bg-primary'
                                    "
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="mb-1 flex items-center gap-2">
                                        <Badge
                                            :variant="
                                                getTypeBadgeVariant(
                                                    notification.data.type,
                                                )
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                getTypeLabel(
                                                    notification.data.type,
                                                )
                                            }}
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatRelativeTime(
                                                    notification.created_at,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <p class="line-clamp-2 text-sm">
                                        {{ notification.data.message }}
                                    </p>

                                    <div class="mt-1 flex items-center gap-4">
                                        <span
                                            v-if="getActorName(notification)"
                                            class="text-xs text-muted-foreground"
                                        >
                                            by {{ getActorName(notification) }}
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatTimestamp(
                                                    notification.created_at,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-20 text-center"
                >
                    <BellRing class="mb-4 h-24 w-24 text-muted-foreground/50" />
                    <h2 class="mb-2 text-xl font-semibold">
                        {{
                            hasActiveFilters
                                ? 'No Matching Notifications'
                                : 'No Notifications'
                        }}
                    </h2>
                    <p class="max-w-md text-muted-foreground">
                        {{
                            hasActiveFilters
                                ? 'Adjust Filters or Try a Broader Search.'
                                : "You're all caught up! No notifications at the moment."
                        }}
                    </p>
                </div>
            </div>

            <!-- Mobile View -->
            <div
                v-if="notifications.data.length > 0"
                class="flex flex-1 flex-col gap-3 overflow-y-auto md:hidden"
            >
                <template
                    v-for="(group, groupName) in groupedNotifications"
                    :key="groupName"
                >
                    <div
                        class="mt-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ groupName }}
                    </div>
                    <button
                        v-for="notification in group"
                        :key="notification.id"
                        class="cursor-pointer rounded-lg border bg-card p-4 text-left shadow-sm transition-colors hover:bg-muted/50"
                        :class="[
                            !notification.read_at
                                ? 'border-primary/30 bg-primary/5'
                                : '',
                            getUrgencyClass(notification.data.urgency),
                        ]"
                        @click="markAsRead(notification)"
                    >
                        <div
                            class="mb-2 flex items-start justify-between gap-2"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="
                                        notification.read_at
                                            ? 'bg-transparent'
                                            : 'bg-primary'
                                    "
                                />
                                <Badge
                                    :variant="
                                        getTypeBadgeVariant(
                                            notification.data.type,
                                        )
                                    "
                                    class="text-xs"
                                >
                                    {{ getTypeLabel(notification.data.type) }}
                                </Badge>
                            </div>
                            <span
                                class="text-xs whitespace-nowrap text-muted-foreground"
                            >
                                {{
                                    formatRelativeTime(notification.created_at)
                                }}
                            </span>
                        </div>

                        <p class="line-clamp-3 text-sm">
                            {{ notification.data.message }}
                        </p>

                        <div
                            class="mt-2 flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span v-if="getActorName(notification)">
                                by {{ getActorName(notification) }}
                            </span>
                            <span>{{
                                formatTimestamp(notification.created_at)
                            }}</span>
                        </div>
                    </button>
                </template>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center py-20 text-center md:hidden"
            >
                <BellRing class="mb-4 h-24 w-24 text-muted-foreground/50" />
                <h2 class="mb-2 text-xl font-semibold">
                    {{
                        hasActiveFilters
                            ? 'No Matching Notifications'
                            : 'No Notifications'
                    }}
                </h2>
                <p class="max-w-md text-muted-foreground">
                    {{
                        hasActiveFilters
                            ? 'Adjust Filters or Try a Broader Search.'
                            : "You're all caught up!"
                    }}
                </p>
            </div>

            <!-- Pagination -->
            <div
                v-if="notifications.links?.length"
                class="flex flex-wrap items-center gap-1.5"
            >
                <Button
                    v-for="link in notifications.links"
                    :key="link.label"
                    :disabled="!link.url"
                    variant="outline"
                    size="sm"
                    class="h-8 min-w-8 px-2 text-xs"
                    :class="{
                        'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground dark:bg-white dark:text-black dark:hover:bg-white dark:hover:text-black':
                            link.active,
                    }"
                    @click="
                        link.url &&
                        router.visit(link.url, {
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                        })
                    "
                >
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
