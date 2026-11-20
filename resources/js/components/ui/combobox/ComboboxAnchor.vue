<script setup lang="ts">
import { computed } from 'vue';
import type { ComboboxAnchorProps } from 'reka-ui';
import { ComboboxAnchor, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxAnchorProps & { class?: string }>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
    <ComboboxAnchor v-bind="forwardedProps" :class="cn(
        'flex w-full items-center justify-between rounded-md  bg-background text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class
    )
        ">
        <slot />
    </ComboboxAnchor>
</template>
