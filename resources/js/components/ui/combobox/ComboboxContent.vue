<script setup lang="ts">
import { computed } from 'vue';
import type { ComboboxContentEmits, ComboboxContentProps } from 'reka-ui';
import { ComboboxContent, ComboboxViewport, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<ComboboxContentProps & { class?: string }>(),
    {
        position: 'popper',
        sideOffset: 4,
    }
);
const emits = defineEmits<ComboboxContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ComboboxContent v-bind="forwarded" :class="cn(
        'relative z-50 max-h-96 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
        position === 'popper' &&
        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
        props.class
    )
        ">
        <ComboboxViewport :class="cn(
            'p-1',
            position === 'popper' &&
            'h-[var(--reka-combobox-trigger-height)] w-full min-w-[var(--reka-combobox-trigger-width)]'
        )
            ">
            <slot />
        </ComboboxViewport>
    </ComboboxContent>
</template>
