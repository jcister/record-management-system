<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, ChevronsUpDown, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
} from '@/components/ui/combobox';
import { ComboboxAnchor } from 'reka-ui';
import { cn } from '@/lib/utils';

interface Option {
    id: string | number;
    name: string;
}

interface Props {
    options: Option[];
    modelValue: (string | number)[];
    placeholder?: string;
    emptyMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select...',
    emptyMessage: 'No options found.',
});

const emit = defineEmits<{
    'update:modelValue': [value: (string | number)[]];
}>();

const open = ref(false);
const searchTerm = ref('');

// Reset search when dropdown closes
watch(open, (isOpen) => {
    if (!isOpen) {
        searchTerm.value = '';
    }
});

const selectedValues = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const filteredOptions = computed(() => {
    if (!searchTerm.value) return props.options;

    const search = searchTerm.value.toLowerCase();
    return props.options.filter(option =>
        option.name.toLowerCase().includes(search)
    );
});

const selectedOptions = computed(() => {
    return props.options.filter(option =>
        selectedValues.value.includes(option.id)
    );
});

const toggleOption = (optionId: string | number) => {
    const index = selectedValues.value.indexOf(optionId);
    const newValue = [...selectedValues.value];

    if (index > -1) {
        newValue.splice(index, 1);
    } else {
        newValue.push(optionId);
    }

    selectedValues.value = newValue;
};

const removeOption = (optionId: string | number, event?: Event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    selectedValues.value = selectedValues.value.filter(id => id !== optionId);
};

const clearAll = (event?: Event) => {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    selectedValues.value = [];
};
</script>

<template>
    <Combobox v-model:open="open" v-model:searchTerm="searchTerm" multiple>
        <ComboboxAnchor as-child>
            <button
                type="button"
                class="flex w-full min-h-10 items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                @click="open = !open"
            >
                <div class="flex flex-wrap items-center gap-1 flex-1 overflow-hidden">
                    <template v-if="selectedOptions.length > 0">
                        <Badge
                            v-for="option in selectedOptions"
                            :key="option.id"
                            variant="secondary"
                            class="gap-1 pr-1"
                        >
                            <span class="max-w-30 truncate">{{ option.name }}</span>
                            <button
                                type="button"
                                class="h-4 w-4 rounded-sm hover:bg-muted inline-flex items-center justify-center"
                                @click="(e) => removeOption(option.id, e)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </template>
                    <span v-else class="text-muted-foreground">{{ placeholder }}</span>
                </div>
                <div class="flex items-center gap-1 ml-2">
                    <button
                        v-if="selectedOptions.length > 0"
                        type="button"
                        class="h-4 w-4 rounded-sm hover:bg-muted inline-flex items-center justify-center opacity-50 hover:opacity-100"
                        @click="(e) => clearAll(e)"
                    >
                        <X class="h-3 w-3" />
                    </button>
                    <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
                </div>
            </button>
        </ComboboxAnchor>

        <ComboboxContent class="w-[--reka-combobox-trigger-width] p-0">
            <div class="p-2 border-b bg-background sticky top-0 z-10">
                <ComboboxInput
                    placeholder="Search..."
                    class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
            </div>

            <div class="max-h-64 overflow-y-auto p-1">
                <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                    {{ emptyMessage }}
                </ComboboxEmpty>

                <ComboboxItem
                    v-for="option in filteredOptions"
                    :key="option.id"
                    :value="String(option.id)"
                    class="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                    @click="toggleOption(option.id)"
                >
                    <div class="flex items-center gap-2 w-full">
                        <div
                            :class="cn(
                                'flex h-4 w-4 items-center justify-center rounded-sm border border-primary',
                                selectedValues.includes(option.id)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'opacity-50'
                            )"
                        >
                            <Check
                                v-if="selectedValues.includes(option.id)"
                                class="h-3 w-3"
                            />
                        </div>
                        <span class="flex-1 truncate">{{ option.name }}</span>
                    </div>
                </ComboboxItem>
            </div>
        </ComboboxContent>
    </Combobox>
</template>
