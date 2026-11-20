<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useNotificationSse } from '@/composables/useNotificationSse';
import { apiClient } from '@/lib/axios';
import type { AppNotification } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();

const isOpen = ref(false);
const notifications = ref<AppNotification[]>([]);
const isLoading = ref(false);
const hasFetched = ref(false);
const unreadCountFromSse = ref<number | null>(null);
const isPageVisible = ref(
    typeof document === 'undefined' || document.visibilityState === 'visible',
);

const notificationPages = new Set(['Notifications', 'settings/Notifications']);
const shouldUseSse = computed(
    () => !notificationPages.has(page.component as string),
);

// Before the first fetch completes, fall back to the server-shared prop.
// After that, derive purely from the local notifications array so that
// mark-as-read mutations and background polls share one source of truth.
const unreadCount = computed(() =>
    unreadCountFromSse.value !== null
        ? unreadCountFromSse.value
        : hasFetched.value
        ? notifications.value.filter((n) => !n.read_at).length
        : ((page.props.unreadNotificationsCount as number) ?? 0),
);

const upsertNotification = (incoming: AppNotification): void => {
    const index = notifications.value.findIndex((item) => item.id === incoming.id);

    if (index === -1) {
        notifications.value = [incoming, ...notifications.value].slice(0, 20);
        return;
    }

    notifications.value[index] = {
        ...notifications.value[index],
        ...incoming,
    };
};

const applyRead = (notificationId: string, readAt: string): void => {
    notifications.value = notifications.value.map((item) =>
        item.id === notificationId ? { ...item, read_at: readAt } : item,
    );
};

const applyAllRead = (notificationIds: string[], readAt: string): void => {
    if (notificationIds.length === 0) {
        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: readAt,
        }));

        return;
    }

    const idSet = new Set(notificationIds);
    notifications.value = notifications.value.map((item) =>
        idSet.has(item.id) ? { ...item, read_at: readAt } : item,
    );
};

const fetchNotifications = async ({ showLoading = false } = {}) => {
    if (showLoading) {
        isLoading.value = true;
    }

    try {
        const { data } = await apiClient.get<{ data: AppNotification[] }>(
            '/api/notifications',
        );
        notifications.value = data.data ?? [];
        hasFetched.value = true;
        unreadCountFromSse.value = notifications.value.filter((n) => !n.read_at).length;
    } finally {
        if (showLoading) {
            isLoading.value = false;
        }
    }
};

const handleOpen = (open: boolean) => {
    isOpen.value = open;

    if (open) {
        fetchNotifications({ showLoading: notifications.value.length === 0 });

        if (isPageVisible.value && shouldUseSse.value) {
            connect();
        }

        return;
    }

    disconnect();
};

const markAsRead = async (notification: AppNotification, navigate = true) => {
    if (!notification.read_at) {
        await apiClient.post(`/api/notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
    }

    if (navigate && notification.data.link) {
        isOpen.value = false;
        router.visit(notification.data.link);
    }
};

const markAllAsRead = async () => {
    await apiClient.post('/api/notifications/read-all');
    notifications.value = notifications.value.map((n) => ({
        ...n,
        read_at: new Date().toISOString(),
    }));
    unreadCountFromSse.value = 0;
};

const formatTime = (dateString: string): string => {
    const date = new Date(dateString);
    const diffMins = Math.floor((Date.now() - date.getTime()) / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;

    return date.toLocaleDateString();
};

const { connect, disconnect } = useNotificationSse({
    onEvent: (event) => {
        if (event.event === 'notification_created') {
            upsertNotification(event.notification);
            unreadCountFromSse.value = event.unread_count;
            hasFetched.value = true;

            return;
        }

        if (event.event === 'notification_read') {
            applyRead(event.notification_id, event.read_at);
            unreadCountFromSse.value = event.unread_count;

            return;
        }

        applyAllRead(event.notification_ids, event.read_at);
        unreadCountFromSse.value = event.unread_count;
    },
});

onMounted(() => {
    fetchNotifications();

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }
});

const handleVisibilityChange = (): void => {
    if (typeof document === 'undefined') {
        return;
    }

    isPageVisible.value = document.visibilityState === 'visible';

    if (!isPageVisible.value) {
        disconnect();
        return;
    }

    if (isOpen.value && shouldUseSse.value) {
        fetchNotifications();
        connect();
    }
};

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }

    disconnect();
});
</script>

<template>
    <Popover :open="isOpen" @update:open="handleOpen">
        <PopoverTrigger as-child>
            <Button variant="ghost" size="icon" class="relative h-9 w-9">
                <Bell class="h-4 w-4" />
                <Badge
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </Badge>
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-80 p-0" align="end">
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm font-semibold">Notifications</span>
                <button
                    v-if="notifications.some((n) => !n.read_at)"
                    class="text-xs text-muted-foreground hover:text-foreground"
                    @click="markAllAsRead"
                >
                    Mark all as read
                </button>
            </div>

            <Separator />

            <div class="max-h-80 overflow-y-auto">
                <div v-if="isLoading" class="flex flex-col gap-2 p-4">
                    <div
                        v-for="i in 3"
                        :key="i"
                        class="h-12 animate-pulse rounded-md bg-muted"
                    />
                </div>

                <template v-else-if="notifications.length > 0">
                    <button
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50"
                        :class="{ 'bg-primary/5': !notification.read_at }"
                        @click="markAsRead(notification)"
                    >
                        <div
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                            :class="
                                notification.read_at
                                    ? 'bg-transparent'
                                    : 'bg-primary'
                            "
                        />

                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-sm">
                                {{ notification.data.message }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ formatTime(notification.created_at) }}
                            </p>
                        </div>
                    </button>
                </template>

                <div
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    No notifications yet.
                </div>
            </div>

            <Separator />

            <div class="p-2">
                <Link
                    href="/accountsettings/notifications"
                    class="block w-full rounded-md px-3 py-2 text-center text-sm font-medium transition-colors hover:bg-muted"
                    @click="isOpen = false"
                >
                    See all notifications
                </Link>
            </div>
        </PopoverContent>
    </Popover>
</template>
