<script setup lang="ts">
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import { getFilesUploadTrendConfig } from '@/utils/chartConfig';

interface FilesTrendData {
    date: string;
    count: number;
}

interface Props {
    filesUploadTrend: FilesTrendData[];
}

const props = defineProps<Props>();

// Convert date strings to timestamps for ApexCharts
const series = computed(() => [
    {
        name: 'Files Uploaded',
        data: props.filesUploadTrend.map((item) => ({
            x: new Date(item.date).getTime(),
            y: item.count,
        })),
    },
]);

const chartOptions = computed(() => getFilesUploadTrendConfig());

const totalFiles = computed(() => {
    return props.filesUploadTrend.reduce((sum, item) => sum + item.count, 0);
});

const averagePerDay = computed(() => {
    if (props.filesUploadTrend.length === 0) return 0;
    return Math.round(totalFiles.value / props.filesUploadTrend.length);
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="filesUploadTrend.length > 0" class="flex flex-col gap-2">
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Total Files (30 days)</span>
                <span class="font-semibold">{{ totalFiles }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">Average Per Day</span>
                <span class="font-semibold">{{ averagePerDay }}</span>
            </div>
        </div>
        <div v-if="filesUploadTrend.length > 0">
            <VueApexCharts
                type="area"
                height="280"
                :options="chartOptions"
                :series="series"
            />
        </div>
        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
            <p class="text-sm text-muted-foreground">
                No Upload Data Available for the Selected Period.
            </p>
        </div>
    </div>
</template>
