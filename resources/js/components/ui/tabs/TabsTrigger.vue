<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, inject } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    value: string;
    class?: HTMLAttributes['class'];
}>();

const getModelValue = inject<() => string | undefined>('tabsModelValue', () => undefined);
const updateModelValue = inject<(value: string) => void>('tabsUpdate', () => {});

const isActive = computed(() => getModelValue() === props.value);

const handleClick = () => {
    updateModelValue(props.value);
};
</script>

<template>
    <button
        type="button"
        :class="
            cn(
                'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
                isActive
                    ? 'bg-background text-foreground shadow'
                    : 'text-muted-foreground hover:bg-background/50',
                props.class,
            )
        "
        @click="handleClick"
    >
        <slot />
    </button>
</template>
