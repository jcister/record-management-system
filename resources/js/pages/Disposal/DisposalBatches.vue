<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import {
    AlertCircle,
    Archive,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    CheckCircle2,
    ClipboardList,
    Eye,
    Filter,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';

import { show as showBatch, destroy as destroyBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import DatePicker from '@/components/common/DatePicker.vue';
import SearchInput from '@/components/common/SearchInput.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { formatDate, formatDateTime } from '@/utils/format';

interface DisposalBatch {
    id: string;
    reference: string;
    status: string;
    file_count: number;
    items_count: number;
    scheduled_by: { id: string; name: string } | null;
    scheduled_at: string | null;
    report_submitted_by: { id: string; name: string } | null;
    report_submitted_to_coa_at: string | null;
    scheduled_note: string | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

type Props = {
    batches: Paginator<DisposalBatch>;
    statusOptions: { value: string; label: string }[];
};

const props = defineProps<Props>();

const { success, error } = useToast();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Batches', description: 'View All Disposal Batches and their Status', href: '/disposal-batches' },
];

// Search and filter state
type FilterForm = {
    search: string;
};

const filterState = reactive<FilterForm>({
    search: '',
});

// Additional filter states
const showFilters = ref(false);
const statusFilter = ref('');
const scheduledFromFilter = ref('');
const scheduledToFilter = ref('');
const createdFromFilter = ref('');
const createdToFilter = ref('');

// Sorting state
type SortField = 'reference' | 'status' | 'file_count' | 'scheduled_at' | 'report_submitted_to_coa_at' | 'created_at';
type SortDirection = 'asc' | 'desc';
const sortBy = ref<SortField>('created_at');
const sortDirection = ref<SortDirection>('desc');

const deleteDialogOpen = ref(false);
const batchToDelete = ref<DisposalBatch | null>(null);

// Client-side filtering
const filteredBatches = computed(() => {
    let filtered = [...props.batches.data];

    // Search filter
    if (filterState.search) {
        const query = filterState.search.toLowerCase();
        filtered = filtered.filter(batch =>
            batch.reference.toLowerCase().includes(query) ||
            (batch.scheduled_note && batch.scheduled_note.toLowerCase().includes(query))
        );
    }

    // Status filter
    if (statusFilter.value) {
        filtered = filtered.filter(batch => batch.status === statusFilter.value);
    }

    // Scheduled date range filters
    if (scheduledFromFilter.value) {
        filtered = filtered.filter(batch => {
            if (!batch.scheduled_at) return false;
            const scheduledDate = new Date(batch.scheduled_at);
            scheduledDate.setHours(0, 0, 0, 0);
            const filterDate = new Date(scheduledFromFilter.value);
            filterDate.setHours(0, 0, 0, 0);
            return scheduledDate >= filterDate;
        });
    }

    if (scheduledToFilter.value) {
        filtered = filtered.filter(batch => {
            if (!batch.scheduled_at) return false;
            const scheduledDate = new Date(batch.scheduled_at);
            scheduledDate.setHours(0, 0, 0, 0);
            const filterDate = new Date(scheduledToFilter.value);
            filterDate.setHours(0, 0, 0, 0);
            return scheduledDate <= filterDate;
        });
    }

    // Created date range filters
    if (createdFromFilter.value) {
        filtered = filtered.filter(batch => {
            if (!batch.created_at) return false;
            const createdDate = new Date(batch.created_at);
            createdDate.setHours(0, 0, 0, 0);
            const filterDate = new Date(createdFromFilter.value);
            filterDate.setHours(0, 0, 0, 0);
            return createdDate >= filterDate;
        });
    }

    if (createdToFilter.value) {
        filtered = filtered.filter(batch => {
            if (!batch.created_at) return false;
            const createdDate = new Date(batch.created_at);
            createdDate.setHours(0, 0, 0, 0);
            const filterDate = new Date(createdToFilter.value);
            filterDate.setHours(0, 0, 0, 0);
            return createdDate <= filterDate;
        });
    }

    // Sort the filtered results
    filtered.sort((a, b) => {
        let aVal: any;
        let bVal: any;

        switch (sortBy.value) {
            case 'reference':
                aVal = a.reference.toLowerCase();
                bVal = b.reference.toLowerCase();
                break;
            case 'status':
                aVal = a.status.toLowerCase();
                bVal = b.status.toLowerCase();
                break;
            case 'file_count':
                aVal = a.file_count;
                bVal = b.file_count;
                break;
            case 'scheduled_at':
                aVal = a.scheduled_at ? new Date(a.scheduled_at).getTime() : 0;
                bVal = b.scheduled_at ? new Date(b.scheduled_at).getTime() : 0;
                break;
            case 'report_submitted_to_coa_at':
                aVal = a.report_submitted_to_coa_at ? new Date(a.report_submitted_to_coa_at).getTime() : 0;
                bVal = b.report_submitted_to_coa_at ? new Date(b.report_submitted_to_coa_at).getTime() : 0;
                break;
            case 'created_at':
                aVal = a.created_at ? new Date(a.created_at).getTime() : 0;
                bVal = b.created_at ? new Date(b.created_at).getTime() : 0;
                break;
            default:
                return 0;
        }

        if (aVal < bVal) return sortDirection.value === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection.value === 'asc' ? 1 : -1;
        return 0;
    });

    return filtered;
});

const handleSort = (field: SortField) => {
    if (sortBy.value === field) {
        // Toggle direction if clicking the same field
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        // New field, default to ascending
        sortBy.value = field;
        sortDirection.value = 'asc';
    }
};

const getSortIcon = (field: SortField) => {
    if (sortBy.value !== field) return ArrowUpDown;
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const clearFilters = () => {
    filterState.search = '';
    statusFilter.value = '';
    scheduledFromFilter.value = '';
    scheduledToFilter.value = '';
    createdFromFilter.value = '';
    createdToFilter.value = '';
    showFilters.value = false;
};

const hasActiveFilters = computed(() => {
    return !!(
        filterState.search ||
        statusFilter.value ||
        scheduledFromFilter.value ||
        scheduledToFilter.value ||
        createdFromFilter.value ||
        createdToFilter.value
    );
});

const statusDisplayLabel = (status: string): string => {
    switch (status) {
        case 'scheduled':
            return 'Scheduled';
        case 'completed':
            return 'Verified';
        case 'request_letter_ready':
            return 'Request Letter Ready';
        case 'waiting_for_inspection':
            return 'Waiting for Inspection';
        case 'inspected':
            return 'Inspected';
        case 'approved':
            return 'Approved';
        case 'complete':
            return 'Complete';
        case 'rejected':
            return 'Rejected';
        default:
            return status.charAt(0).toUpperCase() + status.slice(1);
    }
};

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'scheduled':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
        case 'completed':
            return 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-900/40 dark:text-teal-300';
        case 'request_letter_ready':
            return 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300';
        case 'waiting_for_inspection':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
        case 'inspected':
            return 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300';
        case 'approved':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'complete':
            return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300';
        case 'rejected':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300';
        default:
            return 'border-muted-foreground/40 text-muted-foreground';
    }
};

const isOverdue = (batch: DisposalBatch): boolean => {
    if (batch.status !== 'scheduled' || !batch.scheduled_at) return false;
    return new Date(batch.scheduled_at) < new Date();
};

const viewBatch = (batchId: string) => {
    router.visit(showBatch.url(batchId));
};

const paginationLinks = computed(() => props.batches.links ?? []);

const visitLink = (url: string | null) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const rangeLabel = computed(() => {
    const totalDisplayed = filteredBatches.value.length;
    const totalAll = props.batches.total;

    if (totalAll === 0) {
        return 'Showing 0 disposal batches';
    }

    if (filterState.search && totalDisplayed !== totalAll) {
        return `Showing ${totalDisplayed} of ${totalAll} disposal batches`;
    }

    return `Showing ${props.batches.from ?? 1}–${props.batches.to ?? props.batches.data.length} of ${totalAll} disposal batches`;
});

const openDeleteDialog = (batch: DisposalBatch) => {
    batchToDelete.value = batch;
    deleteDialogOpen.value = true;
};

const isSubmitting = ref(false);

const confirmDelete = () => {
    if (!batchToDelete.value) return;

    isSubmitting.value = true;

    router.delete(destroyBatch.url(batchToDelete.value.id), {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to delete batch', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Batch deleted', 'The disposal batch has been deleted successfully.');
            deleteDialogOpen.value = false;
            batchToDelete.value = null;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Disposal Batches" />

        <div class="space-y-6 p-6">
            <!-- Header -->
            <div>
                <!-- <h1 class="text-2xl font-semibold text-foreground">Disposal Batches</h1>
                <p class="text-sm text-muted-foreground">
                    View All Disposal Batches and their Status
                </p> -->
            </div>

            <!-- Search & Filters -->
                <div class="flex w-full flex-col gap-4">
                    <!-- Search Bar and Quick Filters -->
                    <div class="flex w-full flex-row flex-wrap items-center gap-3">
                        <div class="relative flex-1 min-w-0">
                            <SearchInput
                                v-model="filterState.search"
                                placeholder="Search by Reference or Note..."
                            />
                        </div>
                        <Button
                            variant="outline"
                            @click="showFilters = !showFilters"
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
                        class="grid w-full grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-3"
                    >
                        <!-- Status Filter -->
                        <div class="flex w-full flex-col gap-2">
                            <Label>Status</Label>
                            <Select
                                :model-value="statusFilter || 'all'"
                                @update:model-value="(val) => statusFilter = val === 'all' ? '' : String(val)"
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    <SelectItem
                                        v-for="option in props.statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Scheduled Date Range -->
                        <div class="col-span-1 sm:col-span-2 md:col-span-2 flex w-full flex-col">
                            <Label class="text-sm font-medium">Scheduled Date Range</Label>
                            <div class="flex w-full flex-col gap-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                                <DatePicker
                                    class="w-full"
                                    :model-value="scheduledFromFilter"
                                    @update:model-value="(val) => scheduledFromFilter = val || ''"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="true"
                                />
                                <div class="text-muted-foreground hidden h-10 shrink-0 items-center px-1 sm:flex">→</div>
                                <DatePicker
                                    class="w-full"
                                    :model-value="scheduledToFilter"
                                    @update:model-value="(val) => scheduledToFilter = val || ''"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="true"
                                    :disabled="!scheduledFromFilter"
                                />
                            </div>
                        </div>

                        <!-- Created Date Range -->
                        <div class="col-span-1 sm:col-span-2 md:col-span-3 flex w-full flex-col">
                            <Label class="text-sm font-medium">Created Date Range</Label>
                            <div class="flex w-full flex-col gap-2 sm:grid sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                                <DatePicker
                                    class="w-full"
                                    :model-value="createdFromFilter"
                                    @update:model-value="(val) => createdFromFilter = val || ''"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="true"
                                />
                                <div class="text-muted-foreground hidden h-10 shrink-0 items-center px-1 sm:flex">→</div>
                                <DatePicker
                                    class="w-full"
                                    :model-value="createdToFilter"
                                    @update:model-value="(val) => createdToFilter = val || ''"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="true"
                                    :disabled="!createdFromFilter"
                                />
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Table - Desktop -->
            <div
                class="hidden w-full min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm md:flex"
            >
                <div class="border-b px-4 py-3">
                    <p class="text-sm font-medium">{{ rangeLabel }}</p>
                </div>

                <div class="flex-1 overflow-x-auto w-full">
                    <Table class="w-full min-w-[1320px] table-fixed">
                        <TableHeader
                            class="sticky top-0 z-10 bg-background [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed"
                        >
                            <TableRow>
                                <TableHead
                                    class="w-[180px] sm:w-[220px] md:w-[280px] lg:w-[320px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('reference')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Reference</span>
                                        <component :is="getSortIcon('reference')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    class="w-[100px] sm:w-[120px] md:w-[140px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('status')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Status</span>
                                        <component :is="getSortIcon('status')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    class="w-[60px] sm:w-[80px] md:w-[100px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('file_count')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Files</span>
                                        <component :is="getSortIcon('file_count')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    class="w-[160px] sm:w-[180px] md:w-[220px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('scheduled_at')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Scheduled By</span>
                                        <component :is="getSortIcon('scheduled_at')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    class="w-[160px] sm:w-[180px] md:w-[220px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('report_submitted_to_coa_at')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Submission to COA</span>
                                        <component :is="getSortIcon('report_submitted_to_coa_at')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead
                                    class="w-[100px] sm:w-[120px] md:w-[140px] cursor-pointer select-none hover:bg-muted/50"
                                    @click="() => handleSort('created_at')"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>Date</span>
                                        <component :is="getSortIcon('created_at')" class="h-4 w-4" />
                                    </div>
                                </TableHead>
                                <TableHead class="w-24"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody
                            class="block max-h-[min(60vh,32rem)] overflow-y-auto [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed"
                        >
                            <TableRow
                                v-for="batch in filteredBatches"
                                :key="batch.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="viewBatch(batch.id)"
                            >
                                <TableCell class="w-[180px] sm:w-[220px] md:w-[280px] lg:w-[320px]">
                                    <div class="space-y-1 min-w-0">
                                        <p class="font-medium truncate">{{ batch.reference }}</p>
                                        <p v-if="batch.scheduled_note" class="text-xs text-muted-foreground truncate">
                                            {{ batch.scheduled_note }}
                                        </p>
                                    </div>
                                </TableCell>

                                <TableCell class="w-[100px] sm:w-[120px] md:w-[140px]">
                                    <Badge
                                        variant="outline"
                                        :class="statusBadgeClass(batch.status)"
                                    >
                                        {{ statusDisplayLabel(batch.status) }}
                                    </Badge>
                                </TableCell>

                                <TableCell class="w-[60px] sm:w-[80px] md:w-[100px]">
                                    <div class="flex items-center gap-2">
                                        <ClipboardList class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-medium">{{ batch.file_count }}</span>
                                    </div>
                                </TableCell>

                                <TableCell class="w-[160px] sm:w-[180px] md:w-[220px]">
                                    <div v-if="batch.scheduled_by" class="space-y-1 min-w-0">
                                        <p class="text-sm truncate">{{ batch.scheduled_by.name }}</p>
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-xs text-muted-foreground truncate">
                                                {{ formatDateTime(batch.scheduled_at ?? '') }}
                                            </p>
                                            <div
                                                v-if="isOverdue(batch)"
                                                class="flex items-center gap-1 rounded-md bg-orange-100 px-1.5 py-0.5 dark:bg-orange-900/30"
                                            >
                                                <AlertCircle class="h-3 w-3 text-orange-600 dark:text-orange-400" />
                                                <span class="text-xs font-medium text-orange-600 dark:text-orange-400">Overdue</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground">—</p>
                                </TableCell>

                                <TableCell class="w-[160px] sm:w-[180px] md:w-[220px]">
                                    <div v-if="batch.report_submitted_by || batch.report_submitted_to_coa_at" class="space-y-1 min-w-0">
                                        <p v-if="batch.report_submitted_by" class="text-sm truncate">{{ batch.report_submitted_by.name }}</p>
                                        <p v-if="batch.report_submitted_to_coa_at" class="text-xs text-muted-foreground truncate">
                                            {{ formatDateTime(batch.report_submitted_to_coa_at) }}
                                        </p>
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground">—</p>
                                </TableCell>

                                <TableCell class="w-[100px] sm:w-[120px] md:w-[140px]">
                                    <p class="text-sm">{{ formatDate(batch.created_at) }}</p>
                                </TableCell>

                                <TableCell class="w-24">
                                    <div class="flex w-full  gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click.stop="viewBatch(batch.id)"
                                            class="h-8 w-8 cursor-pointer ml-auto"
                                            title="View batch"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <TooltipProvider v-if="batch.status === 'scheduled'">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="text-destructive hover:text-destructive cursor-pointer"
                                                        @click.stop="openDeleteDialog(batch)"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    <p class="text-sm">Delete batch</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                    </div>
                                </TableCell>
                            </TableRow>

                            <TableEmpty v-if="filteredBatches.length === 0" :colspan="7">
                                <div class="flex flex-col items-center gap-2 py-8 text-center">
                                    <Archive class="h-8 w-8 text-muted-foreground" />
                                    <p class="font-medium">No batches found</p>
                                    <p class="text-sm text-muted-foreground">
                                        No disposal batches match the current filters.
                                    </p>
                                    <Button v-if="hasActiveFilters" size="sm" variant="outline" @click="clearFilters">
                                        Clear filters
                                    </Button>
                                </div>
                            </TableEmpty>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="paginationLinks.length && props.batches.data.length > 0"
                    class="flex items-center justify-between border-t px-4 py-3"
                >
                    <p class="text-sm text-muted-foreground">
                        Page {{ props.batches.current_page }} of {{ props.batches.last_page }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="(link, index) in paginationLinks"
                            :key="`${link.label}-${index}`"
                            :disabled="!link.url"
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                            @click="visitLink(link.url)"
                        >
                            <span v-html="link.label" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div v-if="filteredBatches.length > 0" class="flex md:hidden flex-col gap-3">
                <div
                    v-for="batch in filteredBatches"
                    :key="batch.id"
                    class="rounded-lg border bg-card p-4 shadow-sm transition-colors cursor-pointer hover:bg-muted/50"
                    @click="viewBatch(batch.id)"
                >
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-sm truncate">{{ batch.reference }}</p>
                            <p v-if="batch.scheduled_note" class="text-xs text-muted-foreground truncate">{{ batch.scheduled_note }}</p>
                        </div>
                        <Badge
                            variant="outline"
                            :class="statusBadgeClass(batch.status)"
                            class="shrink-0 text-xs"
                        >
                            {{ statusDisplayLabel(batch.status) }}
                        </Badge>
                    </div>

                    <div class="space-y-1 text-xs text-muted-foreground">
                        <div class="flex justify-between items-center">
                            <span>Files:</span>
                            <div class="flex items-center gap-1">
                                <ClipboardList class="h-3.5 w-3.5" />
                                <span class="font-medium text-foreground">{{ batch.file_count }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span>Scheduled By:</span>
                            <div v-if="batch.scheduled_by" class="flex flex-col items-end gap-0.5">
                                <span class="font-medium text-foreground">{{ batch.scheduled_by.name }}</span>
                                <div class="flex items-center gap-1">
                                    <span>{{ formatDateTime(batch.scheduled_at ?? '') }}</span>
                                    <div
                                        v-if="isOverdue(batch)"
                                        class="flex items-center gap-1 rounded-md bg-orange-100 px-1.5 py-0.5 dark:bg-orange-900/30"
                                    >
                                        <AlertCircle class="h-3 w-3 text-orange-600 dark:text-orange-400" />
                                        <span class="text-xs font-medium text-orange-600 dark:text-orange-400">Overdue</span>
                                    </div>
                                </div>
                            </div>
                            <span v-else class="font-medium text-foreground">—</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span>Submission to COA:</span>
                            <div v-if="batch.report_submitted_by || batch.report_submitted_to_coa_at" class="flex flex-col items-end gap-0.5">
                                <span v-if="batch.report_submitted_by" class="font-medium text-foreground">{{ batch.report_submitted_by.name }}</span>
                                <span v-if="batch.report_submitted_to_coa_at">{{ formatDateTime(batch.report_submitted_to_coa_at) }}</span>
                            </div>
                            <span v-else class="font-medium text-foreground">—</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Created:</span>
                            <span class="font-medium text-foreground">{{ formatDate(batch.created_at) }}</span>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-end gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 text-xs"
                            @click.stop="viewBatch(batch.id)"
                        >
                            View
                        </Button>
                        <TooltipProvider v-if="batch.status === 'scheduled'">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 text-destructive hover:text-destructive"
                                        @click.stop="openDeleteDialog(batch)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p class="text-sm">Delete batch</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                    </div>
                </div>

                <!-- Mobile Pagination -->
                <div v-if="paginationLinks.length" class="flex flex-wrap items-center gap-1.5">
                    <Button
                        v-for="(link, index) in paginationLinks"
                        :key="`${link.label}-${index}`"
                        :disabled="!link.url"
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        class="h-8 min-w-8 px-2 text-xs"
                        @click="visitLink(link.url)"
                    >
                        <span v-html="link.label" />
                    </Button>
                </div>
            </div>

            <!-- Mobile empty state -->
            <div v-if="filteredBatches.length === 0" class="flex md:hidden flex-col items-center justify-center py-20 text-center">
                <Archive class="h-16 w-16 text-muted-foreground/50 mb-4" />
                <h2 class="text-lg font-semibold mb-2">No Batches Found</h2>
                <p class="text-muted-foreground max-w-md text-sm">
                    {{ hasActiveFilters ? 'Try adjusting your filters to see more results.' : 'There are no disposal batches to display.' }}
                </p>
                <Button v-if="hasActiveFilters" size="sm" variant="outline" class="mt-3" @click="clearFilters">
                    Clear filters
                </Button>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Delete Disposal Batch</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this disposal batch? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="batchToDelete" class="py-4">
                    <div class="rounded-lg bg-destructive/10 border border-destructive/20 p-4 space-y-2">
                        <p class="text-sm font-medium">{{ batchToDelete.reference }}</p>
                        <div class="text-xs text-muted-foreground space-y-1">
                            <p>File count: {{ batchToDelete.file_count }}</p>
                            <p v-if="batchToDelete.scheduled_note">Note: {{ batchToDelete.scheduled_note }}</p>
                        </div>
                        <p class="text-xs text-destructive font-medium mt-2">
                            ⚠️ All files in this batch will be removed and the batch will be permanently deleted.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteDialogOpen = false"
                        :disabled="isSubmitting"
                        class="cursor-pointer hover:bg-primary/90 hover:bg-gray-100"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmDelete"
                        :disabled="isSubmitting"
                        class="cursor-pointer hover:bg-primary/90 hover:bg-red-700"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete Batch
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
