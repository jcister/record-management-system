<script setup lang="ts">
import DatePicker from '@/components/common/DatePicker.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Department } from '@/types/schema';
import { parseDate as parseCalendarDate } from '@internationalized/date';
import { Filter, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    searchQuery: string;
    searchPlaceholder?: string;
    showFilters: boolean;
    showFilterButton?: boolean;
    showDateRange?: boolean;
    // Request filters
    statusFilter?: string;
    formatFilter?: string;
    priorityFilter?: string;
    departmentFilter?: string;
    departments?: Department[];
    dateFromFilter?: string;
    dateToFilter?: string;
    // History filters
    userFilter?: string;
    actionFilter?: string;
    // Configuration
    showRequestFilters?: boolean;
    showHistoryFilters?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Search...',
    showFilterButton: true,
    showDateRange: true,
    showRequestFilters: false,
    showHistoryFilters: false,
    departments: () => [],
    priorityFilter: '',
});

const emit = defineEmits<{
    'update:searchQuery': [value: string];
    'update:showFilters': [value: boolean];
    'update:statusFilter': [value: string];
    'update:formatFilter': [value: string];
    'update:priorityFilter': [value: string];
    'update:departmentFilter': [value: string];
    'update:dateFromFilter': [value: string];
    'update:dateToFilter': [value: string];
    'update:userFilter': [value: string];
    'update:actionFilter': [value: string];
    'clear-filters': [];
}>();

const dateRangeError = ref('');

const parseDateInput = (value: string): Date | null => {
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

    if (
        Number.isNaN(month) ||
        Number.isNaN(day) ||
        Number.isNaN(year) ||
        month < 1 ||
        month > 12 ||
        day < 1 ||
        day > 31
    ) {
        return null;
    }

    return new Date(year, month - 1, day);
};

const validateDateRange = (): boolean => {
    const start = parseDateInput(props.dateFromFilter || '');
    const end = parseDateInput(props.dateToFilter || '');

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

watch(
    () => [props.dateFromFilter, props.dateToFilter],
    () => {
        validateDateRange();
    },
);

const hasActiveFilters = computed(() => {
    return !!(
        props.searchQuery ||
        (props.showRequestFilters &&
            (props.statusFilter ||
                props.formatFilter ||
                props.priorityFilter ||
                props.departmentFilter ||
                (props.showDateRange &&
                    (props.dateFromFilter || props.dateToFilter)))) ||
        (props.showHistoryFilters &&
            (props.userFilter ||
                props.actionFilter ||
                (props.showDateRange &&
                    (props.dateFromFilter || props.dateToFilter))))
    );
});

const minDateForTo = computed(() => {
    const start = parseDateInput(props.dateFromFilter || '');

    if (!start) {
        return undefined;
    }

    const month = String(start.getMonth() + 1).padStart(2, '0');
    const day = String(start.getDate()).padStart(2, '0');
    const year = start.getFullYear();

    try {
        return parseCalendarDate(`${year}-${month}-${day}`);
    } catch {
        return undefined;
    }
});

const clearFilters = () => {
    emit('update:searchQuery', '');
    emit('update:statusFilter', '');
    emit('update:formatFilter', '');
    emit('update:priorityFilter', '');
    emit('update:departmentFilter', '');
    emit('update:dateFromFilter', '');
    emit('update:dateToFilter', '');
    emit('update:userFilter', '');
    emit('update:actionFilter', '');
    dateRangeError.value = '';
    emit('clear-filters');
};
</script>

<template>
    <div class="flex w-full flex-col gap-4">
        <!-- Search Bar -->
        <div class="flex w-full flex-row flex-wrap items-center gap-3">
            <div class="relative min-w-0 flex-1">
                <SearchInput
                    :model-value="searchQuery"
                    @update:model-value="
                        (val) =>
                            emit('update:searchQuery', val ? String(val) : '')
                    "
                    :placeholder="searchPlaceholder"
                />
            </div>
            <Button
                v-if="showFilterButton"
                variant="outline"
                @click="emit('update:showFilters', !showFilters)"
                :class="[
                    'flex shrink-0 items-center justify-center gap-2 px-3 whitespace-nowrap',
                    { 'bg-accent': hasActiveFilters, 'cursor-pointer': true },
                ]"
            >
                <Filter class="h-4 w-4 shrink-0" />
                <span>Filters</span>
                <span
                    v-if="hasActiveFilters"
                    class="ml-1 inline-flex items-center justify-center rounded-full bg-primary/10 px-2 py-1 text-xs text-primary hover:bg-primary/20"
                    @click.stop="clearFilters"
                    title="Clear Filters"
                >
                    <X class="h-3 w-3" />
                    <span class="sr-only">Clear Filters</span>
                </span>
            </Button>
        </div>

        <!-- Filter Panel -->
        <div
            v-if="showFilters"
            class="grid w-full grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3"
        >
            <!-- Request Filters -->
            <div v-if="showRequestFilters" class="flex w-full flex-col gap-2">
                <Label>Status</Label>
                <Select
                    :model-value="statusFilter || 'all'"
                    @update:model-value="
                        (val) =>
                            emit(
                                'update:statusFilter',
                                val === 'all' ? '' : String(val),
                            )
                    "
                >
                    <SelectTrigger
                        class="w-full cursor-pointer hover:bg-secondary/80"
                    >
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="Pending">Pending</SelectItem>
                        <SelectItem value="Claimed">Claimed</SelectItem>
                        <SelectItem value="Awaiting Update"
                            >Awaiting Update</SelectItem
                        >
                        <SelectItem value="In Review">In Review</SelectItem>
                        <SelectItem value="Complete">Complete</SelectItem>
                        <SelectItem value="Rejected">Rejected</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="showRequestFilters" class="flex w-full flex-col gap-2">
                <Label>Priority</Label>
                <Select
                    :model-value="priorityFilter || 'all'"
                    @update:model-value="
                        (val) =>
                            emit(
                                'update:priorityFilter',
                                val === 'all' ? '' : String(val),
                            )
                    "
                >
                    <SelectTrigger
                        class="w-full cursor-pointer hover:bg-secondary/80"
                    >
                        <SelectValue placeholder="All Priorities" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Priorities</SelectItem>
                        <SelectItem value="standard">Standard</SelectItem>
                        <SelectItem value="urgent">Urgent</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="showRequestFilters" class="flex w-full flex-col gap-2">
                <Label>Format</Label>
                <Select
                    :model-value="formatFilter || 'all'"
                    @update:model-value="
                        (val) =>
                            emit(
                                'update:formatFilter',
                                val === 'all' ? '' : String(val),
                            )
                    "
                >
                    <SelectTrigger
                        class="w-full cursor-pointer hover:bg-secondary/80"
                    >
                        <SelectValue placeholder="All Formats" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Formats</SelectItem>
                        <SelectItem value="Retrieve Original Copy"
                            >Retrieve Original Copy</SelectItem
                        >
                        <SelectItem value="Reproduce Hardcopy"
                            >Reproduce Hardcopy</SelectItem
                        >
                        <SelectItem value="Electronic Copy Only"
                            >Electronic Copy Only</SelectItem
                        >
                    </SelectContent>
                </Select>
            </div>

            <div v-if="showRequestFilters" class="flex w-full flex-col gap-2">
                <Label>Division</Label>
                <Select
                    :model-value="departmentFilter || 'all'"
                    @update:model-value="
                        (val) =>
                            emit(
                                'update:departmentFilter',
                                val === 'all' ? '' : String(val),
                            )
                    "
                >
                    <SelectTrigger
                        class="w-full cursor-pointer hover:bg-secondary/80"
                    >
                        <SelectValue placeholder="All Division" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Division</SelectItem>
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

            <!-- Date Range Filters -->
            <div
                class="col-start-1 flex w-full flex-col sm:col-span-2 md:col-span-2 lg:col-span-2"
                v-if="
                    showDateRange && (showRequestFilters || showHistoryFilters)
                "
            >
                <Label class="text-sm font-medium">Date Range</Label>
                <div
                    class="flex w-full flex-col gap-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center"
                >
                    <DatePicker
                        class="w-full"
                        :model-value="dateFromFilter"
                        @update:model-value="
                            (val) => emit('update:dateFromFilter', val || '')
                        "
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                    />
                    <div
                        class="hidden h-10 shrink-0 items-center px-1 text-muted-foreground sm:flex"
                    >
                        →
                    </div>
                    <DatePicker
                        class="w-full"
                        :model-value="dateToFilter"
                        @update:model-value="
                            (val) => emit('update:dateToFilter', val || '')
                        "
                        placeholder="MM/DD/YYYY"
                        :allow-manual-entry="true"
                        :disabled="!dateFromFilter"
                        :min-value="minDateForTo"
                    />
                </div>
                <p v-if="dateRangeError" class="mt-1 text-xs text-destructive">
                    {{ dateRangeError }}
                </p>
            </div>
        </div>
    </div>
</template>
