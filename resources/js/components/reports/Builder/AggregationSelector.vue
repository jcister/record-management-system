<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type AggregationType = 'count' | 'sum' | 'avg';

const props = defineProps<{
    modelValue: AggregationType;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: AggregationType): void;
}>();

const options: { value: AggregationType; label: string; description: string }[] = [
    { value: 'count', label: 'Count', description: 'How many records are in each group' },
    { value: 'sum', label: 'Sum', description: 'Total of a numeric field per group' },
    { value: 'avg', label: 'Average', description: 'Average of a numeric field per group' },
];
</script>

<template>
    <div class="flex items-center gap-2">
        <Label class="shrink-0 text-xs font-medium text-muted-foreground">Summarize by</Label>
        <Select
            :model-value="props.modelValue"
            :disabled="props.disabled"
            @update:model-value="val => emit('update:modelValue', val as AggregationType)"
        >
            <SelectTrigger class="h-8 w-36 text-xs">
                <SelectValue placeholder="Choose…" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="opt in options" :key="opt.value" :value="opt.value">
                    <div class="flex flex-col">
                        <span class="font-medium">{{ opt.label }}</span>
                        <span class="text-[10px] text-muted-foreground">{{ opt.description }}</span>
                    </div>
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
