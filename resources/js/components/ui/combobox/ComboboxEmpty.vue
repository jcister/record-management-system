<script setup lang="ts">
import { computed } from 'vue';
import type { ComboboxEmptyProps } from 'reka-ui';
import { ComboboxEmpty, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxEmptyProps & { class?: string }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <ComboboxEmpty v-bind="forwardedProps" :class="cn('py-6 text-center text-sm text-muted-foreground', props.class)">
        <slot />
    </ComboboxEmpty>
</template>
