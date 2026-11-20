<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { provide } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: string;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

// Provide to children
provide('tabsModelValue', () => props.modelValue);
provide('tabsUpdate', (value: string) => emit('update:modelValue', value));
</script>

<template>
    <div :class="cn('w-full', props.class)">
        <slot />
    </div>
</template>
