<script setup lang="ts">
import AppContent from '@/components/layout/AppContent.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import AppShell from '@/components/layout/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    showLayoutToggle?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    showLayoutToggle: false,
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" :showLayoutToggle="showLayoutToggle" />

        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
