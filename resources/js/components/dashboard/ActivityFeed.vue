<script setup lang="ts">
import {
    Activity as ActivityIcon,
    CheckCircle2,
    Clock,
    Download,
    FileText,
    FolderPlus,
    Upload,
    XCircle,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

import { formatDate } from '@/utils/format';

interface ActivityEntry {
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

interface ActivityGroup {
    label: string;
    items: ActivityEntry[];
}

interface Props {
    activities: ActivityEntry[];
}

const props = defineProps<Props>();

const getRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hr ago`;
    if (diffInSeconds < 604800)
        return `${Math.floor(diffInSeconds / 86400)} day${Math.floor(diffInSeconds / 86400) > 1 ? 's' : ''} ago`;

    return formatDate(dateString);
};

const getActivityIcon = (activity: ActivityEntry): Component => {
    const haystack = (activity.action + ' ' + activity.description).toLowerCase();

    if (str(haystack, ['upload', 'created', 'added'])) return Upload;
    if (str(haystack, ['folder'])) return FolderPlus;
    if (str(haystack, ['approved', 'completed', 'released', 'claimed'])) return CheckCircle2;
    if (str(haystack, ['rejected', 'failed', 'error', 'denied'])) return XCircle;
    if (str(haystack, ['pending', 'review', 'awaiting', 'submitted'])) return Clock;
    if (str(haystack, ['download', 'exported'])) return Download;
    if (str(haystack, ['file', 'document', 'record'])) return FileText;

    return ActivityIcon;
};

const str = (haystack: string, needles: string[]): boolean =>
    needles.some((n) => haystack.includes(n));

const getIconColorClass = (activity: ActivityEntry): string => {
    if (activity.severity === 'success') return 'text-green-600 dark:text-green-400';
    if (activity.severity === 'warning') return 'text-amber-600 dark:text-amber-400';
    if (activity.severity === 'danger') return 'text-red-600 dark:text-red-400';
    return 'text-muted-foreground';
};

const getIconBgClass = (activity: ActivityEntry): string => {
    if (activity.severity === 'success') return 'bg-green-100 dark:bg-green-900/30';
    if (activity.severity === 'warning') return 'bg-amber-100 dark:bg-amber-900/30';
    if (activity.severity === 'danger') return 'bg-red-100 dark:bg-red-900/30';
    return 'bg-muted';
};

const getAvatarInitials = (name: string): string => {
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const getAvatarColor = (name: string): string => {
    const colors = [
        'bg-blue-500',
        'bg-purple-500',
        'bg-green-500',
        'bg-amber-500',
        'bg-rose-500',
        'bg-teal-500',
        'bg-indigo-500',
        'bg-orange-500',
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
};

const groupedActivities = computed((): ActivityGroup[] => {
    const groups: Record<string, ActivityEntry[]> = {};

    for (const activity of props.activities) {
        const date = new Date(activity.created_at);
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const activityDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());

        let label: string;

        if (activityDay.getTime() === today.getTime()) {
            label = 'Today';
        } else if (activityDay.getTime() === yesterday.getTime()) {
            label = 'Yesterday';
        } else if (now.getTime() - activityDay.getTime() < 7 * 24 * 60 * 60 * 1000) {
            label = 'Earlier this week';
        } else {
            label = formatDate(activity.created_at);
        }

        if (!groups[label]) {
            groups[label] = [];
        }
        groups[label].push(activity);
    }

    return Object.entries(groups).map(([label, items]) => ({ label, items }));
});
</script>

<template>
    <div>
        <div v-if="activities.length > 0" class="flex max-h-[560px] flex-col gap-4 overflow-y-auto pr-1">
            <div v-for="group in groupedActivities" :key="group.label">
                <!-- Time group header -->
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ group.label }}
                    </span>
                    <div class="h-px flex-1 bg-border" />
                </div>

                <!-- Activity items -->
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="activity in group.items"
                        :key="activity.id"
                        class="flex items-start gap-3 rounded-lg border bg-card p-3 transition-colors hover:bg-accent"
                    >
                        <!-- User avatar initial -->
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                            :class="getAvatarColor(activity.user_name)"
                        >
                            {{ getAvatarInitials(activity.user_name) }}
                        </div>

                        <!-- Activity content -->
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <!-- Description + time -->
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium leading-snug">
                                    {{ activity.description }}
                                </p>
                                <span class="shrink-0 text-xs text-muted-foreground">
                                    {{ getRelativeTime(activity.created_at) }}
                                </span>
                            </div>

                            <!-- Meta: who, what resource type, resource name -->
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-muted-foreground">
                                <span class="font-medium text-foreground/80">{{ activity.user_name }}</span>
                                <span>·</span>
                                <span>{{ activity.resource_type || 'System' }}</span>
                                <span>·</span>
                                <span class="max-w-[180px] truncate font-medium text-foreground/70">
                                    {{ activity.resource_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Contextual icon -->
                        <div
                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md"
                            :class="getIconBgClass(activity)"
                        >
                            <component
                                :is="getActivityIcon(activity)"
                                class="h-3.5 w-3.5"
                                :class="getIconColorClass(activity)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                <ActivityIcon class="h-6 w-6 text-muted-foreground/50" />
            </div>
            <p class="text-sm font-medium text-muted-foreground">No recent activity</p>
            <p class="mt-1 text-xs text-muted-foreground/70">Actions you take will appear here.</p>
        </div>
    </div>
</template>
