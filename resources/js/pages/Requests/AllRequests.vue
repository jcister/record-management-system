<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { FileX, Paperclip, Upload, CheckCircle2, MoreVertical, Eye, FileUp } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableEmpty,
} from '@/components/ui/table';
import RequestDetailsSheet from '@/components/request/RequestDetailsSheet.vue';
import RequestFilterComponent from '@/components/request/RequestFilterComponent.vue';
import RequestTableSkeleton from '@/components/request/RequestTableSkeleton.vue';
import RequestMobileSkeleton from '@/components/request/RequestMobileSkeleton.vue';
import type { RequestRecord, Department } from '@/types/schema';
import { formatDate, formatRequestCode } from '@/utils/format';

const props = defineProps<{
    requests: {
        data: RequestRecord[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    departments: Department[];
}>();

const page = usePage();

const selectedRequest = ref<RequestRecord | null>(null);
const showDetails = ref(false);
const showFilters = ref(false);
const isLoading = ref(true);

// Filter state
const searchQuery = ref('');
const statusFilter = ref<string>('');
const formatFilter = ref<string>('');
const priorityFilter = ref<string>('');
const departmentFilter = ref<string>('');
const dateFromFilter = ref<string>('');
const dateToFilter = ref<string>('');

const openDetails = (request: RequestRecord) => {
    selectedRequest.value = request;
    showDetails.value = true;
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Complete':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800';
        case 'Rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800';
        case 'Pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-300 dark:border-yellow-800';
        case 'Claimed':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 border-blue-300 dark:border-blue-800';
        case 'Awaiting Update':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300 dark:border-amber-800';
        case 'In Review':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 border-purple-300 dark:border-purple-800';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800';
    }
};

const getScanStatusLabel = (request: RequestRecord): string => {
    if (!request.requires_scan) {
        return 'Not Required';
    }

    return request.scan_review_status ?? 'Pending';
};

const getScanStatusColor = (request: RequestRecord): string => {
    if (!request.requires_scan) {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800';
    }

    if (request.scan_review_status === 'Approved') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800';
    }

    if (request.scan_review_status === 'Rejected') {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800';
    }

    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-300 dark:border-yellow-800';
};

const getLinkedFilesCount = (request: RequestRecord): number => {
    if (!request.files || request.files.length === 0) return 0;
    return request.files.filter(file => !file.record_id?.startsWith('SCAN-')).length;
};

const getUploadedScansCount = (request: RequestRecord): number => {
    if (!request.files || request.files.length === 0) return 0;
    return request.files.filter(file => file.record_id?.startsWith('SCAN-')).length;
};

const hasLinkedFiles = (request: RequestRecord): boolean => {
    return getLinkedFilesCount(request) > 0;
};

const hasUploadedScans = (request: RequestRecord): boolean => {
    return getUploadedScansCount(request) > 0;
};

const isScanApproved = (request: RequestRecord): boolean => {
    return request.scan_review_status === 'Approved';
};

const getScanIndicatorState = (request: RequestRecord): 'approved' | 'uploaded' | 'empty' => {
    if (isScanApproved(request)) return 'approved';
    if (hasUploadedScans(request)) return 'uploaded';
    return 'empty';
};



const formatPriorityLabel = (priority?: RequestRecord['priority'] | null): string => {
    const value = priority ?? 'standard';
    return value.charAt(0).toUpperCase() + value.slice(1);
};

// Filtered requests
const filteredRequests = computed(() => {
    let filtered = [...props.requests.data];

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(request =>
            request.request_title.toLowerCase().includes(query) ||
            (request.document_type ? request.document_type.toLowerCase().includes(query) : false) ||
            request.request_id?.toLowerCase().includes(query) ||
            request.requested_by?.name.toLowerCase().includes(query)
        );
    }

    // Status filter
    if (statusFilter.value) {
        filtered = filtered.filter(request => request.status === statusFilter.value);
    }

    // Format filter
    if (formatFilter.value) {
        filtered = filtered.filter(request => request.required_format === formatFilter.value);
    }

    // Priority filter
    if (priorityFilter.value) {
        filtered = filtered.filter(request => (request.priority ?? 'standard') === priorityFilter.value);
    }

    // Department filter
    if (departmentFilter.value) {
        filtered = filtered.filter(request =>
            (request.department_id ?? '').toString() === departmentFilter.value
        );
    }

    // Date from filter
    if (dateFromFilter.value) {
        filtered = filtered.filter(request => {
            const requestDate = new Date(request.created_at);
            const filterDate = new Date(dateFromFilter.value);
            return requestDate >= filterDate;
        });
    }

    // Date to filter
    if (dateToFilter.value) {
        filtered = filtered.filter(request => {
            const requestDate = new Date(request.created_at);
            const filterDate = new Date(dateToFilter.value);
            return requestDate <= filterDate;
        });
    }

    return filtered;
});

const hasActiveFilters = computed(() => {
    return !!(searchQuery.value || statusFilter.value || formatFilter.value || priorityFilter.value ||
        departmentFilter.value || dateFromFilter.value || dateToFilter.value);
});

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    formatFilter.value = '';
    priorityFilter.value = '';
    departmentFilter.value = '';
    dateFromFilter.value = '';
    dateToFilter.value = '';
};

const highlightedRequestId = computed(() => {
    const [, queryString] = page.url.split('?');

    if (!queryString) {
        return null;
    }

    const params = new URLSearchParams(queryString);
    return params.get('highlight');
});

const highlightedRequestLabel = computed(() => formatRequestCode(highlightedRequestId.value));

const isRowHighlighted = (request: RequestRecord): boolean =>
    Boolean(highlightedRequestId.value && highlightedRequestId.value === request.request_id);

const scrollToHighlightedRequest = (requestId: string): void => {
    if (typeof window === 'undefined') {
        return;
    }

    nextTick(() => {
        const row = document.querySelector<HTMLElement>(`[data-request-id="${requestId}"]`);
        row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
};

watch(highlightedRequestId, (newId) => {
    if (!newId) {
        return;
    }

    scrollToHighlightedRequest(newId);
}, { immediate: true });

const clearHighlightedRequest = (): void => {
    if (!highlightedRequestId.value) {
        return;
    }

    const [path, queryString] = page.url.split('?');
    const params = new URLSearchParams(queryString ?? '');

    if (!params.has('highlight')) {
        return;
    }

    params.delete('highlight');
    const nextUrl = params.toString() ? `${path}?${params.toString()}` : path;

    router.visit(nextUrl || '/requests', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const applyFilters = () => {
    router.get('/requests', {}, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
};

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'All Requests', description: 'View and manage all incoming requests', href: '/requests' },
    ]">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Search and Filters -->
            <RequestFilterComponent :search-query="searchQuery"
                :search-placeholder="'Search by Request ID, Title, Document type, or Requester...'"
                :show-filters="showFilters" :status-filter="statusFilter" :format-filter="formatFilter"
                :department-filter="departmentFilter" :date-from-filter="dateFromFilter" :date-to-filter="dateToFilter"
                :priority-filter="priorityFilter"
                :departments="departments" :show-request-filters="true" @update:search-query="searchQuery = $event"
                @update:show-filters="showFilters = $event" @update:status-filter="statusFilter = $event"
                @update:format-filter="formatFilter = $event" @update:priority-filter="priorityFilter = $event"
                @update:department-filter="departmentFilter = $event"
                @update:date-from-filter="dateFromFilter = $event" @update:date-to-filter="dateToFilter = $event"
                @clear-filters="clearFilters" />

            <!-- Results count -->
            <div v-if="!isLoading" class="flex items-center justify-between text-sm text-muted-foreground">
                <div>
                    Showing {{ filteredRequests.length }} of {{ requests.total }} Requests
                </div>
                <div v-if="requests.last_page > 1">
                    Page {{ requests.current_page }} / {{ requests.last_page }}
                </div>
            </div>

            <div v-if="highlightedRequestLabel && !isLoading"
                class="flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 shadow-sm dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    Request <span class="font-semibold">{{ highlightedRequestLabel }}</span> is currently highlighted.
                </span>
                <Button variant="ghost" size="sm"
                    class="self-start text-blue-900 hover:bg-blue-100 dark:text-blue-100 dark:hover:bg-blue-900/60 sm:self-auto"
                    @click="clearHighlightedRequest">
                    Clear highlight
                </Button>
            </div>

            <!-- Web View -->

            <div class="hidden md:flex flex-1 rounded-xl border bg-card shadow-sm overflow-hidden flex-col">
                <RequestTableSkeleton v-if="isLoading" variant="allRequests" :rows="6" />
                <div v-else-if="filteredRequests.length > 0" class="flex flex-col h-full">
                    <div class="w-full">
                        <Table class="w-full table-fixed">
                            <TableHeader class="bg-background sticky top-0 z-10">
                                <TableRow class="border-b">
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm max-w-xs md:max-w-sm">
                                        Request ID</TableHead>
                                    <TableHead
                                        class="text-left py-2 md:py-3 text-xs md:text-sm max-w-sm md:max-w-2xl truncate">
                                        Request Title</TableHead>
                                    <!-- <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden lg:table-cell">Document Type</TableHead> -->
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden md:table-cell">
                                        Requester</TableHead>
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm">Attachments</TableHead>
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden xl:table-cell">
                                        Priority</TableHead>
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm">Status</TableHead>
                                    <!-- <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm">Scan Status</TableHead> -->
                                    <TableHead class="text-left py-2 md:py-3 text-xs md:text-sm hidden lg:table-cell">
                                        Created</TableHead>
                                    <TableHead class="py-2 md:py-3 text-xs md:text-sm text-right w-10">&nbsp;
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                        </Table>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <Table class="w-full table-fixed">
                            <TableBody>
                                <TableRow v-for="request in filteredRequests" :key="request.id" :class="[
                                    'cursor-pointer even:bg-muted/30 transition-colors',
                                    isRowHighlighted(request)
                                        ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/40 hover:bg-blue-100 dark:hover:bg-blue-900/60 border border-blue-200 dark:border-blue-800 shadow-sm'
                                        : 'hover:bg-muted/50'
                                ]" :data-request-id="request.request_id ?? ''" @click="openDetails(request)">
                                    <TableCell
                                        class="text-xs md:text-sm text-left py-2 md:py-3 max-w-xs md:max-w-sm">
                                        REQ-{{ request.request_id?.slice(0, 8) }}
                                    </TableCell>
                                    <TableCell
                                        class="font-medium text-xs md:text-sm text-left py-2 md:py-3 max-w-sm md:max-w-2xl truncate">
                                        {{ request.request_title }}</TableCell>
                                    <!-- <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 hidden lg:table-cell">{{ request.document_type || '—' }}</TableCell> -->
                                    <TableCell
                                        class="text-xs md:text-sm text-left py-2 md:py-3 hidden md:table-cell truncate">
                                        {{ request.requested_by?.name || '—' }}</TableCell>

                                    <TableCell class="text-xs md:text-sm text-center py-2 md:py-3">
                                        <TooltipProvider>
                                            <div class="flex items-start justify-start gap-2">
                                                <!-- Linked Files Indicator -->
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <div class="flex items-center gap-1 px-2 py-1 rounded-md transition-colors"
                                                            :class="hasLinkedFiles(request)
                                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                : 'bg-gray-100 text-gray-400 dark:bg-gray-900/30 dark:text-gray-600'">
                                                            <Paperclip
                                                                :class="hasLinkedFiles(request) ? 'h-3.5 w-3.5' : 'h-3.5 w-3.5'" />
                                                            <span class="text-xs font-medium">{{
                                                                getLinkedFilesCount(request) }}</span>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p class="text-xs">
                                                            {{ hasLinkedFiles(request)
                                                                ? `${getLinkedFilesCount(request)} linked
                                                            file${getLinkedFilesCount(request) === 1 ? '' : 's'} from
                                                            records`
                                                                : 'No linked files from records'
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>

                                                <!-- Uploaded Scans Indicator -->
                                                <Tooltip v-if="request.requires_scan">
                                                    <TooltipTrigger as-child>
                                                        <div class="flex items-center gap-1 px-2 py-1 rounded-md transition-colors"
                                                            :class="getScanIndicatorState(request) === 'approved'
                                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                                : getScanIndicatorState(request) === 'uploaded'
                                                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                                    : 'bg-gray-100 text-gray-400 dark:bg-gray-900/30 dark:text-gray-600'">
                                                            <CheckCircle2
                                                                v-if="getScanIndicatorState(request) === 'approved'"
                                                                class="h-3.5 w-3.5" />
                                                            <Upload v-else class="h-3.5 w-3.5" />
                                                            <span class="text-xs font-medium">
                                                                {{ getScanIndicatorState(request) === 'approved' ? '✓' :
                                                                    getUploadedScansCount(request) }}
                                                            </span>
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p class="text-xs">
                                                            {{ getScanIndicatorState(request) === 'approved'
                                                                ? 'Scan approved and merged into file version'
                                                                : hasUploadedScans(request)
                                                                    ? `${getUploadedScansCount(request)} uploaded
                                                            scan${getUploadedScansCount(request) === 1 ? '' : 's'}`
                                                                    : 'No uploaded scans yet'
                                                            }}
                                                        </p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 hidden xl:table-cell">
                                        <Badge v-if="request.priority === 'urgent'"
                                            class="border text-[11px] bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800">
                                            {{ formatPriorityLabel(request.priority) }}
                                        </Badge>
                                        <Badge v-else class="border text-[11px] bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800">
                                            {{ formatPriorityLabel(request.priority) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3">
                                        <Badge :class="getStatusColor(request.status)"
                                            class="border text-[11px] md:text-[11px] ">
                                            {{ request.status }}
                                        </Badge>
                                    </TableCell>
                                    <!-- <TableCell class="text-xs md:text-sm text-left py-2 md:py-3">
                                            <Badge :class="getScanStatusColor(request)" class="border text-xs md:text-sm">
                                                {{ getScanStatusLabel(request) }}
                                            </Badge>
                                        </TableCell> -->
                                    <TableCell class="text-xs md:text-sm text-left py-2 md:py-3 hidden lg:table-cell">{{
                                        formatDate(request.created_at) }}</TableCell>

                                    <TableCell class="text-xs md:text-sm text-center py-2 md:py-3 w-10">
                                        <div class="flex justify-center">
                                            <button type="button" class="p-1 rounded hover:bg-muted/50"
                                                @click.stop="openDetails(request)">
                                                <Eye class="h-4 w-4 text-muted-foreground" />
                                            </button>
                                        </div>
                                    </TableCell>

                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <FileX class="h-24 w-24 text-muted-foreground/50 mb-4" />
                    <h2 class="text-xl font-semibold mb-2">
                        {{ hasActiveFilters ? 'No Matching Requests' : 'No Requests' }}
                    </h2>
                    <p class="text-muted-foreground max-w-md">
                        {{ hasActiveFilters ? 'Try adjusting your filters to see more results.' : 'There are no requests at the moment.' }}
                    </p>
                </div>
            </div>

            <!-- Mobile View -->
            <RequestMobileSkeleton v-if="isLoading" class="md:hidden" />
            <div v-else-if="filteredRequests.length > 0" class="flex md:hidden flex-1 flex-col gap-3 overflow-y-auto">
                <div v-for="request in filteredRequests" :key="request.id"
                    class="rounded-lg border bg-card p-4 shadow-sm cursor-pointer transition-colors"
                    :class="isRowHighlighted(request) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 dark:border-blue-800' : 'hover:bg-muted/50'"
                    @click="openDetails(request)">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs text-muted-foreground flex items-center gap-2">
                                <span>REQ-{{ request.request_id?.slice(0, 8) }}</span>
                            </p>
                            <p class="font-medium text-sm line-clamp-2">{{ request.request_title }}</p>
                        </div>
                        <div class="flex items-start gap-2">
                            <Badge :class="getStatusColor(request.status)" class="border whitespace-nowrap text-xs">
                                {{ request.status }}
                            </Badge>
                            <button type="button" class="p-1 rounded hover:bg-muted/50"
                                @click.stop="openDetails(request)">
                                <Eye class="h-4 w-4 text-muted-foreground" />
                            </button>
                        </div>
                    </div>
                    <div class="space-y-1 text-xs text-muted-foreground">
                        <div v-if="request.requested_by?.name" class="flex justify-between">
                            <span>Requester:</span>
                            <span class="font-medium text-foreground">{{ request.requested_by.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Document Type:</span>
                            <span class="font-medium text-foreground">{{ request.document_type || '—' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Attachments:</span>
                            <div class="flex items-center gap-1.5">
                                <div class="flex items-center gap-1 px-1.5 py-0.5 rounded" :class="hasLinkedFiles(request)
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-gray-100 text-gray-500 dark:bg-gray-900/30 dark:text-gray-600'">
                                    <Paperclip class="h-3 w-3" />
                                    <span class="text-[10px] font-medium">{{ getLinkedFilesCount(request) }}</span>
                                </div>
                                <div v-if="request.requires_scan" class="flex items-center gap-1 px-1.5 py-0.5 rounded"
                                    :class="getScanIndicatorState(request) === 'approved'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : getScanIndicatorState(request) === 'uploaded'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-900/30 dark:text-gray-600'">
                                    <CheckCircle2 v-if="getScanIndicatorState(request) === 'approved'"
                                        class="h-3 w-3" />
                                    <Upload v-else class="h-3 w-3" />
                                    <span class="text-[10px] font-medium">
                                        {{ getScanIndicatorState(request) === 'approved' ? '✓' :
                                            getUploadedScansCount(request) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span>Scan Review:</span>
                            <Badge :class="`${getScanStatusColor(request)} border text-[10px]`">
                                {{ getScanStatusLabel(request) }}
                            </Badge>
                        </div>
                        <div class="flex justify-between">
                            <span>Priority:</span>
                            <Badge v-if="request.priority === 'urgent'" class="border text-[11px] bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800">
                                {{ formatPriorityLabel(request.priority) }}
                            </Badge>
                            <span v-else class="font-medium text-foreground">Standard</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Created:</span>
                            <span class="font-medium text-foreground">{{ formatDate(request.created_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex md:hidden flex-col items-center justify-center py-20 text-center">
                <FileUp class="h-24 w-24 text-muted-foreground/50 mb-4" />
                <h2 class="text-xl font-semibold mb-2">
                    {{ hasActiveFilters ? 'No Matching Requests' : 'No Requests' }}
                </h2>
                <p class="text-muted-foreground max-w-md">
                    {{ hasActiveFilters ? 'Try adjusting your filters to see more results.' : 'There are no requests at the moment.' }}
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="!isLoading && requests.links?.length" class="flex flex-wrap items-center gap-1.5">
                <Button v-for="link in requests.links" :key="link.label" :disabled="!link.url" variant="outline"
                    size="sm" class="h-8 min-w-8 px-2 text-xs cursor-pointer" :class="{
                        'bg-primary text-primary-foreground dark:bg-white dark:text-black hover:bg-primary hover:text-primary-foreground dark:hover:bg-white dark:hover:text-black': link.active,
                    }"
                    @click="link.url && router.visit(link.url, { preserveState: false, preserveScroll: true, replace: true })">
                    <span v-html="link.label" />
                </Button>
            </div>
        </div>

        <RequestDetailsSheet v-model:open="showDetails" :request="selectedRequest" :show-attached-files="true"
            :show-status-actions="true" :show-scanned-docs="true" />
    </AppLayout>
</template>
