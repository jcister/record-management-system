<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Filter, X } from 'lucide-vue-next';
import SearchInput from '@/components/common/SearchInput.vue';
import DatePicker from '@/components/common/DatePicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface RetentionDuration {
    value: string;
    label: string;
    description: string;
}

interface RootFolder {
    id: string;
    name: string;
}

interface Department {
    id: string | number;
    name: string;
}

interface Props {
    searchQuery: string;
    searchPlaceholder?: string;
    showFilters: boolean;
    retentionDurationFilter?: string;
    rootFolderFilter?: string;
    departmentFilter?: string;
    expiresFromFilter?: string;
    expiresToFilter?: string;
    releasedFromFilter?: string;
    releasedToFilter?: string;
    retentionDurations?: RetentionDuration[];
    rootFolders?: RootFolder[];
    departments?: Department[];
}

const props = withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Search...',
    retentionDurations: () => [],
    rootFolders: () => [],
    departments: () => [],
    retentionDurationFilter: '',
    rootFolderFilter: '',
    departmentFilter: '',
    expiresFromFilter: '',
    expiresToFilter: '',
    releasedFromFilter: '',
    releasedToFilter: '',
});

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:showFilters': [value: boolean];
    'update:retentionDurationFilter': [value: string];
    'update:rootFolderFilter': [value: string];
    'update:departmentFilter': [value: string];
    'update:expiresFromFilter': [value: string];
    'update:expiresToFilter': [value: string];
    'update:releasedFromFilter': [value: string];
    'update:releasedToFilter': [value: string];
    'clear-filters': [];
}>();

const dateRangeError = ref('');

const parseDate = (value: string): Date | null => {
    if (!value) {
        return null;
    }

    const parts = value.split('/');
    if (parts.length !== 3) {
        return null;
    }

    const month = Number(parts[0]);
    const day = Number(parts[1]);
    let year = Number(parts[2]);

    // Convert 2-digit year to 4-digit (00-49 = 2000-2049, 50-99 = 1950-1999)
    if (year < 50) {
        year += 2000;
    } else if (year < 100) {
        year += 1900;
    }

    if (Number.isNaN(month) || Number.isNaN(day) || Number.isNaN(year) || month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    return new Date(year, month - 1, day);
};

const validateExpiresDateRange = (): boolean => {
    const start = parseDate(props.expiresFromFilter || '');
    const end = parseDate(props.expiresToFilter || '');

    if (!start || !end) {
        dateRangeError.value = '';
        return true;
    }

    if (end >= start) {
        dateRangeError.value = '';
        return true;
    }

    dateRangeError.value = 'End date must be after the start date.';
    return false;
};

watch(() => [props.expiresFromFilter, props.expiresToFilter], () => {
    validateExpiresDateRange();
});

const hasActiveFilters = computed(() => {
    return !!(
        props.searchQuery ||
        props.retentionDurationFilter ||
        props.rootFolderFilter ||
        props.departmentFilter ||
        props.expiresFromFilter ||
        props.expiresToFilter ||
        props.releasedFromFilter ||
        props.releasedToFilter
    );
});

const clearFilters = () => {
    emit('update:searchQuery', '');
    emit('update:retentionDurationFilter', '');
    emit('update:rootFolderFilter', '');
    emit('update:departmentFilter', '');
    emit('update:expiresFromFilter', '');
    emit('update:expiresToFilter', '');
    emit('update:releasedFromFilter', '');
    emit('update:releasedToFilter', '');
    dateRangeError.value = '';
    emit('clear-filters');
    emit('update:showFilters', false);
};
</script>

<template>
    <div class="flex w-full flex-col gap-4">
        <!-- Search Bar -->
        <div class="flex w-full flex-row flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-0">
                <SearchInput
                    :model-value="searchQuery"
                    @update:model-value="(val) => emit('update:searchQuery', val ? String(val) : '')"
                    :placeholder="searchPlaceholder"
                />
            </div>
            <Button
                variant="outline"
                @click="emit('update:showFilters', !showFilters)"
                :class="['flex shrink-0 items-center justify-center gap-2 whitespace-nowrap px-3', { 'bg-accent': hasActiveFilters, 'cursor-pointer': true }]"
            >
                <Filter class="h-4 w-4 shrink-0" />
                <span>Filters</span>
                <span
                    v-if="hasActiveFilters"
                    class="ml-1 inline-flex items-center justify-center rounded-full bg-primary/10 px-2 py-1 text-xs text-primary hover:bg-primary/20"
                    @click.stop="clearFilters"
                    title="Clear filters"
                >
                    <X class="h-3 w-3" />
                    <span class="sr-only">Clear filters</span>
                </span>
            </Button>
        </div>

        <!-- Filter Panel -->
        <div
            v-if="showFilters"
            class="grid w-full grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
        >
            <!-- Retention Period Filter -->
            <div class="flex w-full flex-col gap-2">
                <Label>Retention Period</Label>
                <Select
                    :model-value="retentionDurationFilter || 'all'"
                    @update:model-value="(val) => emit('update:retentionDurationFilter', val === 'all' ? '' : String(val))"
                >
                    <SelectTrigger class="w-full cursor-pointer">
                        <SelectValue placeholder="All Periods" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Periods</SelectItem>
                        <SelectItem
                            v-for="duration in retentionDurations"
                            :key="duration.value"
                            :value="duration.value"
                        >
                            {{ duration.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Root Folder Filter -->
            <div v-if="rootFolders.length > 0" class="flex w-full flex-col gap-2">
                <Label>Document Type</Label>
                <Select
                    :model-value="rootFolderFilter || 'all'"
                    @update:model-value="(val) => emit('update:rootFolderFilter', val === 'all' ? '' : String(val))"
                >
                    <SelectTrigger class="w-full cursor-pointer">
                        <SelectValue placeholder="All Folders" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Folders</SelectItem>
                        <SelectItem
                            v-for="folder in rootFolders"
                            :key="folder.id"
                            :value="folder.id"
                        >
                            {{ folder.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Department Filter -->
            <div v-if="departments.length > 0" class="flex w-full flex-col gap-2">
                <Label>Department</Label>
                <Select
                    :model-value="departmentFilter || 'all'"
                    @update:model-value="(val) => emit('update:departmentFilter', val === 'all' ? '' : String(val))"
                >
                    <SelectTrigger class="w-full cursor-pointer">
                        <SelectValue placeholder="All Departments" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        <SelectItem
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="String(dept.id)"
                        >
                            {{ dept.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Expiry Date Range Filters -->
            <div class="col-span-1 sm:col-span-2 md:col-span-2 lg:col-span-2 flex w-full flex-col">
                <Label class="text-sm font-medium">Expiry Date Range</Label>
                <div class="flex w-full flex-col gap-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <DatePicker
                        class="w-full cursor-pointer"
                        :model-value="expiresFromFilter"
                        @update:model-value="(val) => emit('update:expiresFromFilter', val || '')"
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                    />
                    <div class="text-muted-foreground hidden h-10 shrink-0 items-center px-1 sm:flex">→</div>
                    <DatePicker
                        class="w-full cursor-pointer"
                        :model-value="expiresToFilter"
                        @update:model-value="(val) => emit('update:expiresToFilter', val || '')"
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                        :disabled="!expiresFromFilter"
                    />
                </div>
                <p v-if="dateRangeError" class="mt-1 text-xs text-destructive">{{ dateRangeError }}</p>
            </div>

            <!-- Date Released Range Filters -->
            <div class="col-span-1 sm:col-span-2 md:col-span-2 lg:col-span-2 flex w-full flex-col">
                <Label class="text-sm font-medium">Date Released Range</Label>
                <div class="flex w-full flex-col gap-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <DatePicker
                        class="w-full cursor-pointer"
                        :model-value="releasedFromFilter"
                        @update:model-value="(val) => emit('update:releasedFromFilter', val || '')"
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                    />
                    <div class="text-muted-foreground hidden h-10 shrink-0 items-center px-1 sm:flex">→</div>
                    <DatePicker
                        class="w-full cursor-pointer"
                        :model-value="releasedToFilter"
                        @update:model-value="(val) => emit('update:releasedToFilter', val || '')"
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                        :disabled="!releasedFromFilter"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
