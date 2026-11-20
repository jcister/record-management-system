<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, inject } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    value: string;
    class?: HTMLAttributes['class'];
}>();

const getModelValue = inject<() => string | undefined>('tabsModelValue', () => undefined);

const isActive = computed(() => getModelValue() === props.value);
</script>

<template>
    <div
        v-if="isActive"
        :class="
            cn(
                'mt-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
