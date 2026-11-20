<script setup lang="ts">
import { type LucideIcon } from 'lucide-vue-next';

interface Props {
    label: string;
    value: number | string;
    icon?: LucideIcon;
    iconClass?: string;
    trend?: number;
    suffix?: string;
    colorClass?: string;
    cardClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    iconClass: 'text-primary',
    suffix: '',
    colorClass: 'text-foreground',
    cardClass: '',
});

const trendPositive = props.trend !== undefined && props.trend > 0;
const trendNegative = props.trend !== undefined && props.trend < 0;
</script>

<template>
    <div class="flex flex-col gap-2 rounded-xl border p-5" :class="props.cardClass || 'border-border/60 bg-card'">
        <div class="flex items-center justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ props.label }}</p>
            <component v-if="props.icon" :is="props.icon" class="h-4 w-4" :class="props.iconClass" />
        </div>
        <p class="text-3xl font-bold" :class="props.colorClass">
            {{ typeof props.value === 'number' ? props.value.toLocaleString() : props.value }}{{ props.suffix }}
        </p>
        <p v-if="props.trend !== undefined" class="text-xs" :class="trendPositive ? 'text-emerald-600' : trendNegative ? 'text-rose-600' : 'text-muted-foreground'">
            {{ trendPositive ? '▲' : trendNegative ? '▼' : '–' }}
            {{ Math.abs(props.trend) }}% from last period
        </p>
    </div>
</template>
