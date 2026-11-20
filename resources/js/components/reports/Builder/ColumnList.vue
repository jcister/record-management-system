<script setup lang="ts">
import { GripVertical, Plus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface Field {
    key: string;
    label: string;
    type: string;
}

interface Props {
    fields: Field[];
    selectedKeys: string[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'add', key: string): void;
}>();

const availableFields = () => props.fields.filter(f => !props.selectedKeys.includes(f.key));
</script>

<template>
    <div class="flex flex-col gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Available Fields</p>
        <div class="flex max-h-72 flex-col gap-1 overflow-y-auto rounded-lg border border-border/60 bg-muted/30 p-2">
            <div
                v-for="field in availableFields()"
                :key="field.key"
                class="flex cursor-pointer items-center justify-between rounded-md px-3 py-2 text-sm hover:bg-accent transition-colors"
                tabindex="0"
                :aria-label="`Add field ${field.label}`"
                @click="emit('add', field.key)"
                @keydown.enter="emit('add', field.key)"
                @keydown.space.prevent="emit('add', field.key)"
            >
                <div class="flex items-center gap-2">
                    <GripVertical class="h-3 w-3 text-muted-foreground/40" />
                    <span>{{ field.label }}</span>
                    <span class="text-[10px] text-muted-foreground/60 bg-muted rounded px-1">{{ field.type }}</span>
                </div>
                <Plus class="h-3.5 w-3.5 text-primary opacity-0 group-hover:opacity-100" />
            </div>
            <p v-if="availableFields().length === 0" class="py-4 text-center text-xs text-muted-foreground">
                All fields selected
            </p>
        </div>
    </div>
</template>
