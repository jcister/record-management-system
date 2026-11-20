<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

import { useAppearance } from '@/composables/useAppearance';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const { resolvedAppearance } = useAppearance();

const logoSrc = computed(() => {
    return resolvedAppearance.value === 'dark' ? '/logo-white.svg' : '/logo-dark.svg';
});
</script>

<template>
<img :src="logoSrc" :class="className" v-bind="$attrs" />
</template>
