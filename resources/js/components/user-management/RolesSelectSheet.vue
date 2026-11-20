<template>
    <div class="w-full space-y-2">
        <label class="mb-1 block text-sm font-medium">Roles</label>

        <MultiSelectCombobox
            v-model="selectedRoleValues"
            :options="roleOptions"
            :placeholder="props.placeholder"
            empty-message="No roles found."
        />

        <template v-for="value in internalValue" :key="value">
            <input type="hidden" name="role_ids[]" :value="value" />
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import MultiSelectCombobox from '@/components/common/MultiSelectCombobox.vue';

interface RoleItem {
    id: number;
    name: string;
    deleted_at?: string | null;
}

const props = withDefaults(defineProps<{
    modelValue?: number[];
    items: RoleItem[];
    placeholder?: string;
}>(), {
    modelValue: () => [],
    placeholder: 'Select Roles',
});

const emits = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const internalValue = ref<number[]>(
    Array.isArray(props.modelValue) ? [...props.modelValue] : [],
);
const localItems = ref<RoleItem[]>([...props.items]);

watch(
    () => props.items,
    (value) => {
        localItems.value = [...value];
    },
);

watch(
    () => props.modelValue,
    (value) => {
        const next = Array.isArray(value) ? [...value] : [];
        if (!arraysEqual(internalValue.value, next)) {
            internalValue.value = next;
        }
    },
);

watch(internalValue, (value) => {
    emits('update:modelValue', value);
});

const roleOptions = computed(() => {
    return localItems.value
        .filter((item) => !item.deleted_at)
        .map((item) => ({ id: item.id, name: item.name }));
});

const selectedRoleValues = computed<(string | number)[]>({
    get: () => internalValue.value,
    set: (value) => {
        const normalized = value
            .map((entry) => Number(entry))
            .filter((entry) => Number.isInteger(entry));

        if (!arraysEqual(internalValue.value, normalized)) {
            internalValue.value = normalized;
        }
    },
});

function arraysEqual(left: number[], right: number[]): boolean {
    if (left.length !== right.length) {
        return false;
    }

    return left.every((value, index) => value === right[index]);
}
</script>
