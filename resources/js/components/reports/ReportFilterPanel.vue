<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Filter, X } from 'lucide-vue-next';
import SearchInput from '@/components/common/SearchInput.vue';
import DatePicker from '@/components/common/DatePicker.vue';
import TagInput from '@/components/common/TagInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Department {
    id: string | number;
    name: string;
}

interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    searchQuery?: string;
    searchPlaceholder?: string;
    showSearch?: boolean;
    showFilterLabel?: boolean;
    departmentFilter?: string;
    statusFilter?: string;
    retentionStatusFilter?: string;
    retentionDurationFilter?: string;
    visibilityFilter?: string;
    dateFromFilter?: string;
    dateToFilter?: string;
    periodFromFilter?: string;
    periodToFilter?: string;
    metadataTagIds?: number[];
    physicalLocationTagIds?: number[];
    departments?: Department[];
    statuses?: SelectOption[] | string[];
    retentionStatuses?: string[];
    retentionDurations?: SelectOption[];
    visibilities?: string[];
    showMetadataTags?: boolean;
    showPhysicalLocationTags?: boolean;
    showDateRange?: boolean;
    showPeriodRange?: boolean;
    showStatus?: boolean;
    showRetentionStatus?: boolean;
    showRetentionDuration?: boolean;
    showVisibility?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    searchQuery: '',
    searchPlaceholder: 'Search Record ID, File Name, Author, Division...',
    showSearch: true,
    showFilterLabel: true,
    metadataTagIds: () => [],
    physicalLocationTagIds: () => [],
    departments: () => [],
    statuses: () => [],
    retentionStatuses: () => [],
    retentionDurations: () => [],
    visibilities: () => [],
    showMetadataTags: true,
    showPhysicalLocationTags: true,
    showDateRange: false,
    showPeriodRange: false,
    showStatus: false,
    showRetentionStatus: false,
    showRetentionDuration: false,
    showVisibility: false,
});

const emit = defineEmits<{
    (e: 'update:searchQuery', val: string): void;
    (e: 'update:departmentFilter', val: string): void;
    (e: 'update:statusFilter', val: string): void;
    (e: 'update:retentionStatusFilter', val: string): void;
    (e: 'update:retentionDurationFilter', val: string): void;
    (e: 'update:visibilityFilter', val: string): void;
    (e: 'update:dateFromFilter', val: string): void;
    (e: 'update:dateToFilter', val: string): void;
    (e: 'update:periodFromFilter', val: string): void;
    (e: 'update:periodToFilter', val: string): void;
    (e: 'update:metadataTagIds', val: number[]): void;
    (e: 'update:physicalLocationTagIds', val: number[]): void;
    (e: 'metadataTagSearch', val: string): void;
    (e: 'physicalLocationTagSearch', val: string): void;
    (e: 'reset'): void;
}>();

const showFilters = ref(false);

const hasActiveFilters = computed(() =>
    !!props.departmentFilter ||
    !!props.statusFilter ||
    !!props.retentionStatusFilter ||
    !!props.retentionDurationFilter ||
    !!props.visibilityFilter ||
    !!props.dateFromFilter ||
    !!props.dateToFilter ||
    !!props.periodFromFilter ||
    !!props.periodToFilter ||
    props.metadataTagIds.length > 0 ||
    props.physicalLocationTagIds.length > 0,
);

const normalizeOptions = (options: SelectOption[] | string[]): SelectOption[] =>
    options.map(o => typeof o === 'string' ? { value: o, label: o.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) } : o);

/** reka-ui forbids value="" on SelectItem — use this sentinel instead */
const ALL = '__all__';
const toModel = (v: string | undefined) => v || ALL;
const fromModel = (v: string | null) => (v === ALL || !v) ? '' : v;

const searchInput = ref(props.searchQuery ?? '');
let searchDebounce: number | undefined;

watch(
    () => props.searchQuery,
    (val) => {
        if (val !== searchInput.value) {
            searchInput.value = val ?? '';
        }
    },
);

const emitSearchQuery = (val: string | number | null): void => {
    searchInput.value = val ? String(val) : '';

    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = window.setTimeout(() => {
        emit('update:searchQuery', searchInput.value);
    }, 250);
};

onBeforeUnmount(() => {
    if (searchDebounce) {
        clearTimeout(searchDebounce);
    }
});
</script>

<template>
    <div class="flex w-full flex-col gap-4">
        <!-- Search Bar -->
        <div class="flex w-full flex-row flex-wrap items-center gap-3">
            <div v-if="props.showSearch" class="relative flex-1 min-w-0">
                <SearchInput
                    :model-value="searchInput"
                    @update:model-value="emitSearchQuery"
                    :placeholder="props.searchPlaceholder"
                />
            </div>
            <Button
                variant="outline"
                size="sm"
                :class="['gap-2', hasActiveFilters ? 'border-primary text-primary' : ' cursor-pointer']"
                aria-label="Toggle filters"
                @click="showFilters = !showFilters"
            >
                <Filter class="h-4 w-4 " />
                Filters
                <span v-if="hasActiveFilters" class="ml-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                    !
                </span>
            </Button>
        </div>

        <!-- Filter Panel -->
        <div
            v-if="showFilters"
            class="grid w-full grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
        >
            <!-- Department -->
            <div v-if="props.departments.length > 0" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Division</Label>
                <Select
                    :model-value="toModel(props.departmentFilter)"
                    @update:model-value="(val) => emit('update:departmentFilter', fromModel(String(val)))"
                >
                    <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                        <SelectValue placeholder="All Division" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All Division</SelectItem>
                        <SelectItem v-for="dept in props.departments" :key="dept.id" :value="String(dept.id)">
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Status -->
            <div v-if="props.showStatus && props.statuses.length > 0" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Status</Label>
                <Select
                    :model-value="toModel(props.statusFilter)"
                    @update:model-value="(val) => emit('update:statusFilter', fromModel(String(val)))"
                >
                    <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All Statuses</SelectItem>
                        <SelectItem v-for="opt in normalizeOptions(props.statuses)" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Retention Status -->
            <div v-if="props.showRetentionStatus && props.retentionStatuses.length > 0" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Retention Status</Label>
                <Select
                    :model-value="toModel(props.retentionStatusFilter)"
                    @update:model-value="(val) => emit('update:retentionStatusFilter', fromModel(String(val)))"
                >
                    <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem v-for="s in props.retentionStatuses" :key="s" :value="s">
                            {{ s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Retention Period -->
            <div v-if="props.showRetentionDuration && props.retentionDurations.length > 0" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Retention Period</Label>
                <Select
                    :model-value="toModel(props.retentionDurationFilter)"
                    @update:model-value="(val) => emit('update:retentionDurationFilter', fromModel(String(val)))"
                >
                    <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                        <SelectValue placeholder="All Period" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All Period</SelectItem>
                        <SelectItem v-for="opt in props.retentionDurations" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Visibility -->
            <div v-if="props.showVisibility && props.visibilities.length > 0" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Visibility</Label>
                <Select
                    :model-value="toModel(props.visibilityFilter)"
                    @update:model-value="(val) => emit('update:visibilityFilter', fromModel(String(val)))"
                >
                    <SelectTrigger class="w-full cursor-pointer hover:bg-secondary/80">
                        <SelectValue placeholder="All" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All</SelectItem>
                        <SelectItem v-for="v in props.visibilities" :key="v" :value="v">
                            {{ v.charAt(0).toUpperCase() + v.slice(1) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Date range -->
            <div v-if="props.showDateRange" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Date From</Label>
                <DatePicker
                    :model-value="props.dateFromFilter ?? ''"
                    placeholder="MM/DD/YYYY"
                    @update:model-value="emit('update:dateFromFilter', $event)"
                    class="w-full cursor-pointer"
                />
            </div>
            <div v-if="props.showDateRange" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Date To</Label>
                <DatePicker
                    :model-value="props.dateToFilter ?? ''"
                    placeholder="MM/DD/YYYY"
                    @update:model-value="emit('update:dateToFilter', $event)"
                    class="w-full cursor-pointer"
                />
            </div>

            <!-- Period range -->
            <div v-if="props.showPeriodRange" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Period From</Label>
                <DatePicker
                    class="w-full"
                    :model-value="props.periodFromFilter ?? ''"
                    placeholder="MM/DD/YYYY"
                    @update:model-value="emit('update:periodFromFilter', $event)"
                />
            </div>
            <div v-if="props.showPeriodRange" class="flex w-full flex-col gap-2">
                <Label class="text-sm font-medium">Period To</Label>
                <DatePicker
                    class="w-full"
                    :model-value="props.periodToFilter ?? ''"
                    placeholder="MM/DD/YYYY"
                    @update:model-value="emit('update:periodToFilter', $event)"
                />
            </div>

            <!-- Metadata tags -->
            <div v-if="props.showMetadataTags" class="flex w-full flex-col gap-2">
                <TagInput
                    label="Metadata Tags"
                    type="metadata"
                    :model-value="props.metadataTagIds"
                    @update:model-value="emit('update:metadataTagIds', $event)"
                    @search="emit('metadataTagSearch', $event)"
                    class="cursor-pointer"
                />
            </div>

            <!-- Physical location tags -->
            <div v-if="props.showPhysicalLocationTags" class="flex w-full flex-col gap-2">
                <TagInput
                    label="Location Tags"
                    type="physical_location"
                    :model-value="props.physicalLocationTagIds"
                    @update:model-value="emit('update:physicalLocationTagIds', $event)"
                    @search="emit('physicalLocationTagSearch', $event)"
                />
            </div>
        </div>
    </div>
</template>
