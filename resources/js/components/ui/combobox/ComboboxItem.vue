<script setup lang="ts">
import { computed } from 'vue';
import type { ComboboxItemEmits, ComboboxItemProps } from 'reka-ui';
import { ComboboxItem, ComboboxItemIndicator, useForwardPropsEmits } from 'reka-ui';
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps<ComboboxItemProps & { class?: string }>();
const emits = defineEmits<ComboboxItemEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <ComboboxItem v-bind="forwarded" :class="cn(
        'relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none data-[disabled]:pointer-events-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:opacity-50',
        props.class
    )
        ">
        <slot />

        <ComboboxItemIndicator class="ml-auto">
            <Check class="h-4 w-4" />
        </ComboboxItemIndicator>
    </ComboboxItem>
</template>
