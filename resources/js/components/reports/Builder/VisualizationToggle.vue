<script setup lang="ts">
import { BarChart2, Grid3x3, PieChart } from 'lucide-vue-next';

type VisualizationType = 'table' | 'bar' | 'donut';

const props = defineProps<{
    modelValue: VisualizationType;
    disableCharts?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: VisualizationType): void;
}>();

const options: { value: VisualizationType; label: string; icon: typeof BarChart2; tooltip: string }[] = [
    { value: 'table', label: 'Table', icon: Grid3x3, tooltip: 'View results as a data table' },
    { value: 'bar', label: 'Bar Chart', icon: BarChart2, tooltip: 'View grouped results as a bar chart (requires Group By)' },
    { value: 'donut', label: 'Donut Chart', icon: PieChart, tooltip: 'View grouped results as a donut chart (requires Group By)' },
];
</script>

<template>
    <div class="flex items-center gap-1 rounded-lg border border-zinc-500/50 bg-zinc-800/70 p-0.5">
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            class="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-300/70"
            :class="[
                props.modelValue === opt.value
                    ? 'bg-white text-zinc-900 shadow-sm'
                    : 'text-zinc-300 hover:bg-zinc-700 hover:text-white',
                props.disableCharts && opt.value !== 'table' ? 'cursor-not-allowed text-zinc-500 opacity-70' : 'cursor-pointer',
            ]"
            :title="opt.tooltip"
            :disabled="props.disableCharts && opt.value !== 'table'"
            @click="!props.disableCharts || opt.value === 'table' ? emit('update:modelValue', opt.value) : null"
        >
            <component :is="opt.icon" class="h-3.5 w-3.5 shrink-0" />
            <span class="hidden sm:inline">{{ opt.label }}</span>
        </button>
    </div>
</template>
