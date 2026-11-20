<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Condition {
    field: string;
    operator: string;
    value: string;
}

interface FieldOption {
    value: string;
    label: string;
}

interface Field {
    key: string;
    label: string;
    type: string;
    options?: FieldOption[];
}

interface Props {
    availableFields: Field[];
}

const props = defineProps<Props>();
const conditions = defineModel<Condition[]>({ default: [] });

const OPERATORS = [
    { value: 'equals', label: '= Equals' },
    { value: 'not_equals', label: '≠ Not Equals' },
    { value: 'contains', label: '∋ Contains' },
    { value: 'not_contains', label: '∌ Not Contains' },
    { value: 'greater_than', label: '> Greater Than' },
    { value: 'less_than', label: '< Less Than' },
    { value: 'is_null', label: 'Is Empty' },
    { value: 'is_not_null', label: 'Is Not Empty' },
];

const TAG_OPERATORS = [
    { value: 'contains', label: '∋ Contains' },
    { value: 'not_contains', label: '∌ Not Contains' },
    { value: 'equals', label: '= Equals' },
    { value: 'not_equals', label: '≠ Not Equals' },
    { value: 'is_null', label: 'Has None' },
    { value: 'is_not_null', label: 'Has Any' },
];

const CLASSIFICATION_OPERATORS = [
    { value: 'equals', label: '= Equals' },
    { value: 'not_equals', label: '≠ Not Equals' },
];

const getOperatorsForField = (fieldKey: string) => {
    const field = props.availableFields.find(f => f.key === fieldKey);
    if (field?.type === 'tags') return TAG_OPERATORS;
    if (field?.type === 'classification') return CLASSIFICATION_OPERATORS;
    return OPERATORS;
};

const getFieldOptions = (fieldKey: string): FieldOption[] | undefined => {
    return props.availableFields.find(f => f.key === fieldKey)?.options;
};

const handleAdd = () => {
    conditions.value = [...conditions.value, { field: props.availableFields[0]?.key ?? '', operator: 'equals', value: '' }];
};

const handleRemove = (index: number) => {
    conditions.value = conditions.value.filter((_, i) => i !== index);
};

const updateCondition = (index: number, partial: Partial<Condition>) => {
    const updated = [...conditions.value];
    updated[index] = { ...updated[index], ...partial };
    conditions.value = updated;
};

const needsValue = (operator: string) => !['is_null', 'is_not_null'].includes(operator);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Conditions</p>
            <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" aria-label="Add condition" @click="handleAdd">
                <Plus class="h-3 w-3" />
                Add
            </Button>
        </div>

        <div class="flex flex-col gap-2">
            <div
                v-for="(condition, index) in conditions"
                :key="index"
                class="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-card p-2"
            >
                <span v-if="index > 0" class="text-[10px] font-semibold uppercase text-muted-foreground w-6">AND</span>

                <Select :model-value="condition.field" @update:model-value="val => updateCondition(index, { field: val })">
                    <SelectTrigger class="h-7 w-36 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="f in availableFields" :key="f.key" :value="f.key">{{ f.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="condition.operator" @update:model-value="val => updateCondition(index, { operator: val })">
                    <SelectTrigger class="h-7 w-36 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="op in getOperatorsForField(condition.field)" :key="op.value" :value="op.value">{{ op.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    v-if="needsValue(condition.operator) && getFieldOptions(condition.field)"
                    :model-value="condition.value"
                    @update:model-value="val => updateCondition(index, { value: String(val) })"
                >
                    <SelectTrigger class="h-7 w-36 text-xs">
                        <SelectValue placeholder="Select..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in getFieldOptions(condition.field)" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    v-else-if="needsValue(condition.operator)"
                    :model-value="condition.value"
                    class="h-7 w-36 text-xs"
                    placeholder="Value..."
                    @update:model-value="val => updateCondition(index, { value: String(val) })"
                />

                <button
                    class="ml-auto text-muted-foreground hover:text-destructive transition-colors"
                    :aria-label="`Remove condition ${index + 1}`"
                    @click="handleRemove(index)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <p v-if="conditions.length === 0" class="text-xs text-muted-foreground">
                No conditions — all records will be returned.
            </p>
        </div>
    </div>
</template>
