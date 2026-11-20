<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface Props {
    modelValue?: number;
    max?: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: 0,
    max: 100,
});

const percentage = computed(() => {
    return Math.min(100, Math.max(0, (props.modelValue / props.max) * 100));
});
</script>

<template>
    <div
        :class="cn('relative h-2 w-full overflow-hidden rounded-full bg-secondary', props.class)"
        role="progressbar"
        :aria-valuemax="max"
        :aria-valuemin="0"
        :aria-valuenow="modelValue"
    >
        <div
            class="h-full w-full flex-1 bg-primary transition-all duration-300 ease-in-out"
            :style="{ transform: `translateX(-${100 - percentage}%)` }"
        />
    </div>
</template>
