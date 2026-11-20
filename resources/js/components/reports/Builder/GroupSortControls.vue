<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { ArrowUpDown } from 'lucide-vue-next';

interface Field {
    key: string;
    label: string;
    type: string;
}

interface Props {
    availableFields: Field[];
    groupBy?: string;
    sortBy?: string;
    sortDir?: 'asc' | 'desc';
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:groupBy', val: string): void;
    (e: 'update:sortBy', val: string): void;
    (e: 'update:sortDir', val: 'asc' | 'desc'): void;
}>();

const NONE_VALUE = '__none__';

const toModelValue = (value?: string): string => {
    return value && value.length > 0 ? value : NONE_VALUE;
};

const fromModelValue = (value: unknown): string => {
    if (value === NONE_VALUE || value === null || value === undefined) {
        return '';
    }

    return String(value);
};

const toggleSort = () => {
    emit('update:sortDir', props.sortDir === 'asc' ? 'desc' : 'asc');
};

const sortableFields = () => props.availableFields.filter(f => f.type !== 'tags');
</script>

<template>
    <div class="flex flex-wrap gap-4">
        <div class="flex flex-col gap-1.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Group By</p>
            <Select
                :model-value="toModelValue(props.groupBy)"
                @update:model-value="emit('update:groupBy', fromModelValue($event))"
            >
                <SelectTrigger class="h-8 w-40 text-xs">
                    <SelectValue placeholder="No grouping" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE_VALUE">No grouping</SelectItem>
                    <SelectItem v-for="f in sortableFields()" :key="f.key" :value="f.key">
                        {{ f.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="flex flex-col gap-1.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sort By</p>
            <div class="flex gap-1">
                <Select
                    :model-value="toModelValue(props.sortBy)"
                    @update:model-value="emit('update:sortBy', fromModelValue($event))"
                >
                    <SelectTrigger class="h-8 w-36 text-xs">
                        <SelectValue placeholder="Default" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE_VALUE">Default</SelectItem>
                        <SelectItem v-for="f in sortableFields()" :key="f.key" :value="f.key">
                            {{ f.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="outline" size="icon" class="h-8 w-8" :aria-label="`Sort direction: ${props.sortDir}`" @click="toggleSort">
                    <ArrowUpDown class="h-3.5 w-3.5" :class="props.sortDir === 'desc' ? 'rotate-180' : ''" />
                </Button>
            </div>
        </div>
    </div>
</template>
