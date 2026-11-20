<script setup lang="ts">
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

interface Props {
    type: 'bar' | 'pie' | 'donut' | 'line' | 'area' | 'treemap' | 'horizontalBar';
    series: ApexAxisChartSeries | ApexNonAxisChartSeries;
    labels?: string[];
    categories?: string[];
    height?: number;
    colors?: string[];
    title?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    height: 280,
    loading: false,
    colors: () => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'],
});

const chartType = computed(() => {
    if (props.type === 'horizontalBar') return 'bar';
    return props.type;
});

const chartOptions = computed((): ApexCharts.ApexOptions => {
    const base: ApexCharts.ApexOptions = {
        colors: props.colors,
        chart: {
            background: 'transparent',
            toolbar: { show: false },
            animations: { enabled: true, speed: 300 },
        },
        theme: { mode: 'light' },
        dataLabels: { enabled: false },
        tooltip: { theme: 'light' },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
        },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            fontFamily: 'inherit',
        },
    };

    const formatIntegerAxisLabel = (value: number): string => {
        if (!Number.isFinite(value)) return String(value);
        return value.toFixed(0);
    };

    if (props.type === 'bar') {
        return {
            ...base,
            xaxis: {
                categories: props.categories ?? props.labels ?? [],
                labels: { style: { fontSize: '11px', fontFamily: 'inherit' } },
            },
            yaxis: { labels: { style: { fontSize: '11px', fontFamily: 'inherit' } } },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
        };
    }

    if (props.type === 'horizontalBar') {
        return {
            ...base,
            chart: { ...base.chart, type: 'bar' },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
            // ApexCharts horizontal bars visually show categories on the Y-axis,
            // but the library expects them to be configured via xaxis.categories.
            xaxis: {
                categories: props.categories ?? props.labels ?? [],
                labels: { style: { fontSize: '11px', fontFamily: 'inherit' } },
            },
            yaxis: {
                labels: { style: { fontSize: '11px', fontFamily: 'inherit' } },
                // These charts are "count based", so we force integer tick labels.
                labels: {
                    style: { fontSize: '11px', fontFamily: 'inherit' },
                    formatter: formatIntegerAxisLabel,
                },
            },
        };
    }

    if (props.type === 'line' || props.type === 'area') {
        return {
            ...base,
            xaxis: {
                categories: props.categories ?? props.labels ?? [],
                labels: { style: { fontSize: '11px', fontFamily: 'inherit' } },
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: props.type === 'area' ? { type: 'gradient' } : undefined,
        };
    }

    if (props.type === 'pie' || props.type === 'donut') {
        return {
            ...base,
            labels: props.labels ?? [],
            dataLabels: { enabled: true, style: { fontSize: '11px', fontFamily: 'inherit' } },
        };
    }

    return base;
});
</script>

<template>
    <div class="relative">
        <div v-if="props.loading" class="flex items-center justify-center" :style="`height:${props.height}px`">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-muted border-t-primary" />
        </div>
        <div v-else-if="!series || (Array.isArray(series) && series.length === 0)" class="flex items-center justify-center text-sm text-muted-foreground" :style="`height:${props.height}px`">
            No data available
        </div>
        <VueApexCharts
            v-else
            :type="chartType"
            :height="props.height"
            :options="chartOptions"
            :series="series"
        />
    </div>
</template>
