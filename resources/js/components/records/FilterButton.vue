<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import { Filter, RefreshCw, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import DatePicker from '@/components/common/DatePicker.vue';
import MultiSelectCombobox from '@/components/common/MultiSelectCombobox.vue';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSearchFilters } from '@/composables/useSearchFilters';
import type { SearchFilters, RetentionDuration } from '@/types/filters';

interface Props {
    modelValue?: SearchFilters;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [filters: SearchFilters];
    'change': [filters: SearchFilters];
    'search': [];
}>();

const {
    filters,
    filterOptions,
    isLoadingOptions,
    activeFilterCount,
    hasActiveFilters,
    clearAllFilters,
    fetchFilterOptions,
} = useSearchFilters();

const isOpen = ref(false);

// Sync with parent if using v-model
watch(
    () => props.modelValue,
    (newValue) => {
        if (newValue) {
            Object.assign(filters, newValue);
        }
    },
    { deep: true, immediate: true },
);

watch(
    filters,
    (newFilters) => {
        emit('update:modelValue', { ...newFilters });
        emit('change', { ...newFilters });
    },
    { deep: true },
);

// Refresh filter options when popover opens - always fetch fresh data
watch(isOpen, (newValue) => {
    if (newValue) {
        fetchFilterOptions();
    }
});

onMounted(() => {
    fetchFilterOptions();
});

const handleClearFilters = () => {
    clearAllFilters();
};

const handleSearch = () => {
    emit('search');
    isOpen.value = false;
};

const handleRefreshOptions = async () => {
    await fetchFilterOptions();
};

/** reka-ui forbids value="" on SelectItem — use this sentinel instead */
const ALL = '__all__';
const toModel = (v: string | string[] | number[] | null | undefined) => {
    if (Array.isArray(v)) return v.length > 0 ? v : [ALL];
    return v || ALL;
};
const fromModelSingle = (v: string) => (v === ALL ? '' : v);
const fromModelArray = (v: string[]) => (v.includes(ALL) ? [] : v);

// For record type filter
const recordTypeOptions = [
    { value: '__all__', label: 'All Files' },
    { value: 'true', label: 'Records Only' },
    { value: 'false', label: 'Non-Records Only' },
];

const recordTypeModelValue = computed(() => {
    if (filters.isRecord === null) return '__all__';
    return String(filters.isRecord);
});

const updateRecordType = (value: any) => {
    const strValue = String(value);
    if (!strValue || strValue === '__all__') {
        filters.isRecord = null;
    } else {
        filters.isRecord = strValue === 'true';
    }
};

// Multi-select handling for retention duration checkboxes
const toggleRetentionDuration = (value: RetentionDuration) => {
    const index = filters.retentionDurations.indexOf(value);
    if (index > -1) {
        filters.retentionDurations.splice(index, 1);
    } else {
        filters.retentionDurations.push(value);
    }
};

// Helper to convert MM/DD/YYYY to DateValue
const stringToDateValue = (dateStr: string) => {
    if (!dateStr || dateStr.length !== 10) return undefined;

    const parts = dateStr.split('/');
    if (parts.length !== 3) return undefined;

    const month = parts[0].padStart(2, '0');
    const day = parts[1].padStart(2, '0');
    const year = parts[2];

    try {
        return parseDate(`${year}-${month}-${day}`);
    } catch {
        return undefined;
    }
};

// Date validation - Date Created
const dateCreatedFromMin = computed(() => undefined);
const dateCreatedFromMax = computed(() => stringToDateValue(filters.dateCreatedTo));
const dateCreatedToMin = computed(() => stringToDateValue(filters.dateCreatedFrom));
const dateCreatedToMax = computed(() => undefined);

// Date validation - Date Released
const dateReleasedFromMin = computed(() => undefined);
const dateReleasedFromMax = computed(() => stringToDateValue(filters.dateReleasedTo));
const dateReleasedToMin = computed(() => stringToDateValue(filters.dateReleasedFrom));
const dateReleasedToMax = computed(() => undefined);
</script>

<template>
    <Popover v-model:open="isOpen">
        <PopoverTrigger as-child>
            <button
                type="button"
                aria-label="Open search filters"
                class="relative flex h-14 w-14 shrink-0 items-center border justify-center rounded-2xl  transition-colors"
                :class="hasActiveFilters
                    ? ' bg-black text-white dark:border-white dark:bg-white dark:text-black'
                    : ' dark:border-white bg-background text-foreground hover:bg-accent/40'"
            >
                <Filter class="h-5 w-5 text-muted-foreground" />
                <span
                    v-if="hasActiveFilters"
                    class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-500 px-1.5 text-[10px] font-bold text-primary-foreground"
                >
                    {{ activeFilterCount }}
                </span>
            </button>
        </PopoverTrigger>
        <PopoverContent class="w-[90vw] max-w-2xl rounded-2xl border-2 border-black/10 dark:border-white/10 p-0 shadow-xl" align="end">
            <div class="flex flex-col max-h-[80vh]">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border/60 px-5 py-4">
                    <div class="flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <h3 class="font-semibold">Filters</h3>
                        <span v-if="hasActiveFilters" class="text-xs text-muted-foreground">({{ activeFilterCount }} active)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent transition-colors"
                            :class="isLoadingOptions ? 'opacity-50 cursor-not-allowed' : ''"
                            :disabled="isLoadingOptions"
                            title="Refresh filter options"
                            @click="handleRefreshOptions"
                        >
                            <RefreshCw :class="['h-3.5 w-3.5', isLoadingOptions ? 'animate-spin' : '']" />
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="flex h-7 items-center gap-1 rounded-lg px-2 text-xs text-muted-foreground hover:bg-accent transition-colors"
                            @click="handleClearFilters"
                        >
                            <X class="h-3 w-3" />
                            Clear all
                        </button>
                    </div>
                </div>

                <!-- Filters Content -->
                <div class="overflow-y-auto px-5 py-4">
                    <div v-if="isLoadingOptions" class="flex items-center justify-center py-8 text-sm text-muted-foreground">
                        Loading filter options…
                    </div>
                    <div v-else class="grid gap-5 sm:grid-cols-2">
                        <!-- Date Created Range -->
                        <div class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date Created</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <DatePicker
                                    v-model="filters.dateCreatedFrom"
                                    placeholder="From"
                                    :min-value="dateCreatedFromMin"
                                    :max-value="dateCreatedFromMax"
                                />
                                <DatePicker
                                    v-model="filters.dateCreatedTo"
                                    placeholder="To"
                                    :min-value="dateCreatedToMin"
                                    :max-value="dateCreatedToMax"
                                />
                            </div>
                        </div>

                        <!-- Date Released Range -->
                        <div class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Date Released</Label>
                            <div class="grid grid-cols-2 gap-2">
                                <DatePicker
                                    v-model="filters.dateReleasedFrom"
                                    placeholder="From"
                                    :min-value="dateReleasedFromMin"
                                    :max-value="dateReleasedFromMax"
                                />
                                <DatePicker
                                    v-model="filters.dateReleasedTo"
                                    placeholder="To"
                                    :min-value="dateReleasedToMin"
                                    :max-value="dateReleasedToMax"
                                />
                            </div>
                        </div>

                        <!-- Document Type (Root Folder) -->
                        <div v-if="filterOptions?.rootFolders && filterOptions.rootFolders.length > 0" class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Document Type</Label>
                            <MultiSelectCombobox
                                v-model="filters.rootFolderIds"
                                :options="filterOptions.rootFolders"
                                placeholder="Select document types..."
                                empty-message="No document types found."
                               />
                        </div>

                        <!-- Authors -->
                        <div v-if="filterOptions?.authors && filterOptions.authors.length > 0" class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Author</Label>
                            <MultiSelectCombobox
                                v-model="filters.authors"
                                :options="filterOptions.authors"
                                placeholder="Select authors..."
                                empty-message="No authors found."
                            />
                        </div>

                        <!-- Keywords (Metadata Tags) -->
                        <div v-if="filterOptions?.metadataTags && filterOptions.metadataTags.length > 0" class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Keywords</Label>
                            <MultiSelectCombobox
                                v-model="filters.metadataTagIds"
                                :options="filterOptions.metadataTags"
                                placeholder="Select keywords..."
                                empty-message="No keywords found."
                            />
                        </div>

                        <!-- Physical Location Tags -->
                        <div v-if="filterOptions?.physicalLocationTags && filterOptions.physicalLocationTags.length > 0" class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Physical Location</Label>
                            <MultiSelectCombobox
                                v-model="filters.physicalLocationTagIds"
                                :options="filterOptions.physicalLocationTags"
                                placeholder="Select locations..."
                                empty-message="No locations found."
                            />
                        </div>

                        <!-- Retention Duration -->
                        <div v-if="filterOptions?.retentionDurations && filterOptions.retentionDurations.length > 0" class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Retention Duration</Label>
                            <div class="border rounded-md p-2 space-y-1">
                                <label
                                    v-for="duration in filterOptions.retentionDurations"
                                    :key="duration.value"
                                    class="flex items-center gap-2 cursor-pointer hover:bg-accent rounded px-2 py-1 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="filters.retentionDurations.includes(duration.value)"
                                        class="rounded border-input"
                                        @change="toggleRetentionDuration(duration.value)"
                                    />
                                    <span>{{ duration.label }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Record Type -->
                        <div class="flex flex-col gap-2">
                            <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">File Type</Label>
                            <Select
                                :model-value="recordTypeModelValue"
                                @update:model-value="updateRecordType"
                            >
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="All Files" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in recordTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-border/60 px-5 py-3 flex justify-between items-center">
                    <p class="text-xs text-muted-foreground">
                        {{ hasActiveFilters ? `${activeFilterCount} filter${activeFilterCount > 1 ? 's' : ''} active` : 'No filters applied' }}
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="h-9 rounded-xl border border-border px-4 text-sm hover:bg-accent transition-colors"
                            @click="isOpen = false"
                        >
                            Done
                        </button>
                        <button
                            type="button"
                            class="h-9 rounded-xl bg-black dark:bg-white text-white dark:text-black px-4 text-sm font-medium hover:opacity-80 transition-opacity"
                            @click="handleSearch"
                        >
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </PopoverContent>
    </Popover>
</template>
