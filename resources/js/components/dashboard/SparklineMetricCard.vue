<script setup lang="ts">
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { CHART_COLORS, getSparklineChartConfig } from '@/utils/chartConfig';

interface Props {
    label: string;
    value: number | string;
    valueSuffix?: string;
    trendLabel?: string;
    series: number[];
    colorVariant?: 'success' | 'warning' | 'danger' | 'primary' | 'purple';
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    valueSuffix: '',
    trendLabel: '',
    colorVariant: 'primary',
    loading: false,
});

const chartColor = computed(() => {
    switch (props.colorVariant) {
        case 'success':
            return CHART_COLORS.success;
        case 'warning':
            return CHART_COLORS.warning;
        case 'danger':
            return CHART_COLORS.danger;
        case 'purple':
            return CHART_COLORS.purple;
        default:
            return CHART_COLORS.primary;
    }
});

const chartOptions = computed(() => getSparklineChartConfig(chartColor.value));
</script>

<template>
    <Card>
        <CardHeader class="space-y-0.5 p-3 sm:p-4 pb-1">
            <CardTitle class="text-sm font-medium text-muted-foreground">
                {{ label }}
            </CardTitle>
            <Skeleton v-if="loading" class="h-7 w-24" />
            <div v-else class="text-xl sm:text-2xl font-semibold tracking-tight">
                {{ value }}{{ valueSuffix }}
            </div>
        </CardHeader>
        <CardContent class="space-y-0.5 p-3 sm:p-4 pt-1">
            <div class="h-12 overflow-visible">
                <Skeleton v-if="loading" class="h-full w-full" />
                <VueApexCharts
                    v-else
                    class="overflow-visible"
                    type="line"
                    height="48"
                    :options="chartOptions"
                    :series="[{ name: label, data: series }]"
                />
            </div>
            <p v-if="trendLabel" class="text-xs text-muted-foreground">
                {{ trendLabel }}
            </p>
        </CardContent>
    </Card>
</template>

<style scoped>
:deep(.apexcharts-tooltip) {
    z-index: 60 !important;
}
</style>
