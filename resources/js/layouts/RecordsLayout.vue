<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
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
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :showLayoutToggle="showLayoutToggle"
    >
        <template v-if="$slots['header-actions']" #header-actions>
            <slot name="header-actions" />
        </template>
        <slot />
    </AppLayout>
</template>
