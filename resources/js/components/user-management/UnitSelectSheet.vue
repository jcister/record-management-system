<template>
    <div class="w-full">
        <label class="mb-1 block text-sm font-medium">Unit</label>

        <Combobox v-model:open="open" v-model:searchTerm="searchTerm">
            <ComboboxAnchor class="w-full">
                <ComboboxTrigger class="w-full cursor-pointer">
                    <span
                        :class="
                            internalValue !== null
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ displayText }}
                    </span>
                    <ChevronsUpDownIcon
                        class="ml-2 h-4 w-4 shrink-0 opacity-50"
                    />
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxContent class="w-[--reka-combobox-trigger-width] p-0">
                <div class="flex items-center border-b px-3">
                    <Search class="h-4 w-4 text-gray-500" />
                    <ComboboxInput
                        placeholder="Search unit..."
                        class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                    />
                </div>

                <div class="max-h-64 overflow-y-auto p-1">
                    <ComboboxItem
                        v-for="item in visibleItems"
                        :key="item.id"
                        :value="String(item.id)"
                        class="cursor-pointer"
                        @select.prevent="selectItem(item.id)"
                    >
                        <Check
                            v-if="internalValue === item.id"
                            class="mr-2 h-4 w-4 text-primary"
                        />
                        <span class="truncate">{{ item.name }}</span>
                    </ComboboxItem>

                    <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                        No units found
                    </ComboboxEmpty>
                </div>
            </ComboboxContent>
        </Combobox>

        <input type="hidden" name="unit_id" :value="internalValue ?? ''" />
    </div>
</template>

<script setup lang="ts">
import {
    Combobox,
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import {
    Check,
    ChevronsUpDownIcon,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Item {
    id: number;
    name: string;
    deleted_at?: string | null;
}

const props = withDefaults(
    defineProps<{
        modelValue?: number | null;
        items: Item[];
        placeholder?: string;
    }>(),
    {
        modelValue: null,
        placeholder: 'Select Unit',
    },
);

const emits = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const open = ref(false);
const searchTerm = ref('');

const internalValue = ref<number | null>(props.modelValue ?? null);
const localItems = ref<Item[]>([...props.items]);

watch(
    () => props.items,
    (value) => {
        localItems.value = [...value];
    },
);

watch(
    () => props.modelValue,
    (value) => {
        if (internalValue.value !== (value ?? null)) {
            internalValue.value = value ?? null;
        }
    },
);

watch(internalValue, (value) => {
    emits('update:modelValue', value);
});

watch(open, (isOpen) => {
    if (!isOpen) {
        searchTerm.value = '';
    }
});

const visibleItems = computed(() => {
    const base = localItems.value.filter((item) => !item.deleted_at);
    const query = searchTerm.value.trim().toLowerCase();

    if (!query) {
        return base;
    }

    return base.filter((item) => item.name.toLowerCase().includes(query));
});

const displayText = computed(() => {
    if (internalValue.value === null) {
        return props.placeholder;
    }

    const selected = localItems.value.find(
        (item) => item.id === internalValue.value && !item.deleted_at,
    );
    return selected?.name ?? props.placeholder;
});

function selectItem(id: number): void {
    const item = localItems.value.find((entry) => entry.id === id);
    if (item?.deleted_at) {
        return;
    }

    internalValue.value = id;
    open.value = false;
}
</script>
