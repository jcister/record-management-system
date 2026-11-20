<script setup lang="ts">
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import { getRequestsStatusChartConfig, STATUS_COLORS } from '@/utils/chartConfig';

interface RequestStatusData {
    status: string;
    count: number;
}

interface Props {
    requestsByStatus: RequestStatusData[];
}

const props = defineProps<Props>();

// Get series data and labels from requests
const series = computed(() => {
    return props.requestsByStatus.map((item) => item.count);
});

const labels = computed(() => {
    return props.requestsByStatus.map((item) => item.status);
});

const chartOptions = computed(() => {
    const config = getRequestsStatusChartConfig();
    return {
        ...config,
        labels: labels.value,
    };
});

const totalRequests = computed(() => {
    return props.requestsByStatus.reduce((sum, item) => sum + item.count, 0);
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div v-if="totalRequests > 0" class="flex items-center justify-center">
            <VueApexCharts
                type="donut"
                height="320"
                :options="chartOptions"
                :series="series"
            />
        </div>
        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
            <p class="text-sm text-muted-foreground">
                No requests data available yet.
            </p>
        </div>
    </div>
</template>
