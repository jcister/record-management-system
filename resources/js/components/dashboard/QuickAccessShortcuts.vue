<script setup lang="ts">
import { type LucideIcon } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export type ShortcutVariant = 'urgent' | 'warning' | 'normal';

export interface ShortcutItem {
    title: string;
    description: string;
    icon: LucideIcon;
    route: string;
    badgeLabel?: string;
    variant?: ShortcutVariant;
    colorClass: string;
}

interface Props {
    shortcuts: ShortcutItem[];
    title?: string;
    description?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Quick Actions',
    description: 'Common tasks you can do right away.',
});

const handleShortcutClick = (route: string) => {
    router.visit(route);
};

const borderVariantClass = (variant?: ShortcutVariant): string => {
    if (variant === 'urgent') return 'border-l-4 border-l-red-500';
    if (variant === 'warning') return 'border-l-4 border-l-amber-500';
    return '';
};

const badgeVariantClass = (variant?: ShortcutVariant): string => {
    if (variant === 'urgent') return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400';
    if (variant === 'warning') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400';
    return 'bg-muted text-muted-foreground';
};
</script>

<template>
    <Card class="w-full">
        <CardHeader class="pb-3">
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>
        <CardContent class="pt-0">
            <div
                class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3"
                :class="{
                    'xl:grid-cols-3': shortcuts.length === 3,
                    'xl:grid-cols-4': shortcuts.length !== 3,
                }"
            >
                <button
                    v-for="shortcut in shortcuts"
                    :key="shortcut.route"
                    @click="handleShortcutClick(shortcut.route)"
                    class="group flex min-h-20 w-full items-start gap-3 rounded-lg border bg-card p-4 text-left transition-all hover:bg-accent hover:shadow-sm"
                    :class="borderVariantClass(shortcut.variant)"
                >
                    <div
                        class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-muted transition-colors group-hover:bg-background"
                    >
                        <component :is="shortcut.icon" class="h-5 w-5" :class="shortcut.colorClass" />
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <p class="text-sm font-semibold leading-tight">{{ shortcut.title }}</p>
                        <p class="text-xs text-muted-foreground leading-snug">
                            {{ shortcut.description }}
                        </p>
                        <span
                            v-if="shortcut.badgeLabel"
                            class="mt-1 inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="badgeVariantClass(shortcut.variant)"
                        >
                            {{ shortcut.badgeLabel }}
                        </span>
                    </div>
                </button>
            </div>
        </CardContent>
    </Card>
</template>
