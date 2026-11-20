<script setup lang="ts">
import { computed } from 'vue';
import { LayoutGrid, AlignJustify, List } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useViewMode } from '@/composables/useViewMode';

const { viewMode, toggle } = useViewMode();

const isTiles = computed(() => viewMode.value === 'tiles');

const toggleLayout = () => {
    toggle();
};
</script>

<template>
    <Button
        @click="toggleLayout"
        variant="ghost"
        size="icon"
        class="h-9 w-9"
        :title="isTiles ? 'Switch to List View' : 'Switch to Tiles View'"
    >
        <List v-if="isTiles" class="h-4 w-4" />
        <LayoutGrid v-else class="h-4 w-4" />
    </Button>
</template>
