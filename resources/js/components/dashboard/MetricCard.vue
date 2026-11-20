<script setup lang="ts">
import { computed, type Component } from 'vue';
import { ArrowUp, ArrowDown, type LucideIcon } from 'lucide-vue-next';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface Props {
    title: string;
    value: string | number;
    icon: Component;
    subtitle?: string;
    loading?: boolean;
    trend?: {
        value: number;
        label: string;
    };
    colorVariant?: 'default' | 'success' | 'warning' | 'danger';
}

const props = withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    loading: false,
    trend: undefined,
    colorVariant: 'default',
});

const iconColorClass = computed(() => {
    switch (props.colorVariant) {
        case 'success':
            return 'text-green-500';
        case 'warning':
            return 'text-orange-500';
        case 'danger':
            return 'text-red-500';
        default:
            return 'text-muted-foreground';
    }
});

const trendColorClass = computed(() => {
    if (!props.trend) return '';
    return props.trend.value >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">
                {{ title }}
            </CardTitle>
            <component
                :is="icon"
                class="h-4 w-4"
                :class="iconColorClass"
            />
        </CardHeader>
        <CardContent>
            <Skeleton v-if="props.loading" class="h-8 w-20" />
            <div v-else class="text-2xl font-bold">{{ value }}</div>
            <div class="flex items-center gap-2 mt-1">
                <Skeleton v-if="props.loading && subtitle" class="h-4 w-32" />
                <p v-else-if="subtitle" class="text-xs text-muted-foreground">
                    {{ subtitle }}
                </p>
                <div v-if="trend" class="flex items-center gap-1 text-xs" :class="trendColorClass">
                    <component
                        :is="trend.value >= 0 ? ArrowUp : ArrowDown"
                        class="h-3 w-3"
                    />
                    <span>{{ Math.abs(trend.value) }}% {{ trend.label }}</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
