<script setup lang="ts">
import Breadcrumbs from '@/components/layout/Breadcrumbs.vue';
import NotificationBell from '@/components/layout/NotificationBell.vue';
import TilesListButton from '@/components/records/TilesListButton.vue';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
        showLayoutToggle?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        showLayoutToggle: false,
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear md:px-4">
        <div class="flex items-center gap-2">
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-3">
            <slot name="actions" />
            <TilesListButton v-if="showLayoutToggle" />
            <NotificationBell />
        </div>
    </header>
</template>
