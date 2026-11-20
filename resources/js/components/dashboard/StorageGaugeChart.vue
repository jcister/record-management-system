<script setup lang="ts">
import { computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import { formatBytes, getStorageGaugeConfig, getStorageStatusColor } from '@/utils/chartConfig';

interface Props {
    totalStorage: number;
    storageCapacity: number;
    usedPercentage: number;
}

const props = defineProps<Props>();

const series = computed(() => [props.usedPercentage]);

const chartOptions = computed(() => getStorageGaugeConfig(props.usedPercentage));

const statusColor = computed(() => getStorageStatusColor(props.usedPercentage));

const statusText = computed(() => {
    if (props.usedPercentage < 70) return 'Healthy';
    if (props.usedPercentage < 90) return 'Warning';
    return 'Critical';
});

// Watch for dark mode changes and trigger chart re-render
watch(
    () => document.documentElement.classList.contains('dark'),
    () => {
        // Chart will automatically update via computed chartOptions
    }
);
</script>

<template>
    <div class="flex flex-col gap-4 ">
        <div class="flex items-center justify-center ">
            <VueApexCharts
                class=""
                type="radialBar"
                height="280"
                :options="chartOptions"
                :series="series"
            />
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div class="flex flex-col">
                <span class="text-muted-foreground">Used Storage</span>
                <span class="font-semibold">{{ formatBytes(totalStorage) }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-muted-foreground">Total Capacity</span>
                <span class="font-semibold">{{ formatBytes(storageCapacity) }}</span>
            </div>
            <div class="col-span-2 flex items-center gap-2">
                <div
                    class="h-2 w-2 rounded-full"
                    :class="{
                        'bg-green-500': statusColor === 'success',
                        'bg-orange-500': statusColor === 'warning',
                        'bg-red-500': statusColor === 'danger',
                    }"
                />
                <span class="text-xs text-muted-foreground">
                    Status: <span class="font-medium">{{ statusText }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
