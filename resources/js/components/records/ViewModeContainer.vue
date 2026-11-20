<script setup lang="ts">
import { computed } from 'vue';
import { useViewMode } from '@/composables/useViewMode';

const props = withDefaults(defineProps<{
    viewMode?: 'tiles' | 'list';
}>(), {
    viewMode: undefined,
});

const { viewMode: globalViewMode } = useViewMode();
const activeViewMode = computed(() => props.viewMode ?? globalViewMode.value);
</script>

<template>
    <div class>
        <template v-if="activeViewMode === 'tiles'">
            <slot name="tiles" />
        </template>

        <template v-else>
            <slot name="list" />
        </template>
    </div>
</template>
