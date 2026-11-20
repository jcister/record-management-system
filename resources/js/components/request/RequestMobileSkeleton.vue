<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

export interface FieldConfig {
    labelWidth?: string;
    valueWidth?: string;
}

withDefaults(defineProps<{
    cards?: number;
    fields?: FieldConfig[];
}>(), {
    cards: 4,
    fields: () => [
        { labelWidth: 'w-20', valueWidth: 'w-24' },
        { labelWidth: 'w-24', valueWidth: 'w-20' },
        { labelWidth: 'w-20', valueWidth: 'w-16' },
        { labelWidth: 'w-16', valueWidth: 'w-16' },
        { labelWidth: 'w-16', valueWidth: 'w-20' },
    ],
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-3 overflow-y-auto">
        <div
            v-for="index in cards"
            :key="`mobile-skeleton-${index}`"
            class="rounded-lg border bg-card p-4 shadow-sm"
        >
            <div class="mb-3 flex items-start justify-between gap-2">
                <div class="flex-1">
                    <Skeleton class="h-3 w-24 mb-2" />
                    <Skeleton class="h-4 w-full" />
                </div>
                <Skeleton class="h-6 w-16 rounded-full" />
            </div>
            <div class="space-y-2">
                <div
                    v-for="(field, fieldIndex) in fields"
                    :key="`field-${index}-${fieldIndex}`"
                    class="flex justify-between"
                >
                    <Skeleton :class="['h-3', field.labelWidth || 'w-20']" />
                    <Skeleton :class="['h-3', field.valueWidth || 'w-24']" />
                </div>
            </div>
        </div>
    </div>
</template>
