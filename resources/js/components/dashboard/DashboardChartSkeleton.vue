<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

type ChartSkeletonVariant = 'gauge' | 'donut' | 'trend';

withDefaults(defineProps<{
    variant?: ChartSkeletonVariant;
}>(), {
    variant: 'trend',
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="variant === 'gauge'" class="flex items-center justify-center">
            <Skeleton class="h-[280px] w-[280px] rounded-full" />
        </div>

        <div v-else-if="variant === 'donut'" class="flex items-center justify-center">
            <Skeleton class="h-[320px] w-[320px] rounded-full" />
        </div>

        <div v-else class="flex flex-col gap-3">
            <div class="flex items-center justify-between text-sm">
                <Skeleton class="h-4 w-32" />
                <Skeleton class="h-4 w-16" />
            </div>
            <div class="flex items-center justify-between text-sm">
                <Skeleton class="h-4 w-28" />
                <Skeleton class="h-4 w-14" />
            </div>
            <Skeleton class="h-[280px] w-full rounded-lg" />
        </div>
    </div>
</template>
