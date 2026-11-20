<script setup lang="ts">
import { GripVertical, X } from 'lucide-vue-next';
import draggable from 'vuedraggable';

interface Field {
    key: string;
    label: string;
    type: string;
}

const selectedColumns = defineModel<Field[]>({ default: [] });

const handleRemove = (key: string) => {
    selectedColumns.value = selectedColumns.value.filter(f => f.key !== key);
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Selected Columns</p>
        <div class="min-h-24 rounded-lg border-2 border-dashed border-border/60 bg-muted/20 p-2">
            <draggable
                v-model="selectedColumns"
                item-key="key"
                handle=".drag-handle"
                class="flex flex-col gap-1"
                ghost-class="opacity-40"
            >
                <template #item="{ element }">
                    <div class="flex items-center justify-between rounded-md border border-border/60 bg-card px-3 py-2 text-sm shadow-sm">
                        <div class="flex items-center gap-2">
                            <GripVertical class="drag-handle h-3.5 w-3.5 cursor-grab text-muted-foreground active:cursor-grabbing" />
                            <span class="font-medium">{{ element.label }}</span>
                            <span class="text-[10px] text-muted-foreground/60 bg-muted rounded px-1">{{ element.type }}</span>
                        </div>
                        <button
                            class="text-muted-foreground hover:text-destructive transition-colors"
                            :aria-label="`Remove ${element.label}`"
                            @click="handleRemove(element.key)"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
            </draggable>
            <p v-if="selectedColumns.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                Add fields from the list on the left
            </p>
        </div>
    </div>
</template>
