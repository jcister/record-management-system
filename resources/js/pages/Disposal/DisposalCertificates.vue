<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { ArrowDown, ArrowUp, ArrowUpDown, Download, Eye, FileText } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import SearchInput from '@/components/common/SearchInput.vue';
import FileViewer from '@/components/records/FileViewer.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { formatDateTime } from '@/utils/format';

interface UserRef {
    id: string;
    name: string;
}

interface DocumentInfo {
    has_document: boolean;
    file_exists: boolean;
    generated_at: string | null;
    generated_by: UserRef | null;
    preview_url: string | null;
    download_url: string | null;
}

interface BatchDocumentItem {
    id: string;
    reference: string;
    status: string;
    status_label: string;
    file_count: number;
    actual_mode_of_disposal_label: string | null;
    created_at: string;
    nap_form_1: DocumentInfo;
    nap_form_3: DocumentInfo;
    request_letter_for_nap: DocumentInfo;
    endorsement_letter_to_coa: DocumentInfo;
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

interface Option {
    value: string;
    label: string;
}

type Props = {
    batches: Paginator<BatchDocumentItem>;
    filters: {
        search: string;
        status: string;
        per_page: number;
    };
    options: {
        status: Option[];
        per_page: number[];
    };
};

type SortField = 'reference' | 'status';
type SortDirection = 'asc' | 'desc';

type ViewerFile = {
    id: string;
    file_name: string;
    file_size: number;
    mime_type: string;
    created_at: string;
    file_path: string;
    retention_status?: 'active' | 'ready_for_disposal' | 'disposed' | 'archived' | null;
    ocr_text?: string | null;
    ocr_status?: string | null;
};

type ViewerState = {
    file: ViewerFile;
    previewUrl: string;
    downloadUrl: string;
    previewAvailable: boolean;
    previewUnavailableReason?: 'disposed' | 'missing' | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Certificates', description: 'Repository of generated disposal PDFs per batch.', href: '/disposal-management/certificates' },
];

const filterState = reactive({
    search: props.filters.search,
});

const statusFilter = ref(props.filters.status || 'all');
const perPageFilter = ref(String(props.filters.per_page || 15));
const sortField = ref<SortField>('reference');
const sortDirection = ref<SortDirection>('asc');
const viewerState = ref<ViewerState | null>(null);

const PDF_TITLES = {
    napForm1: 'NAP Form 1 - Records Inventory and Appraisal',
    napForm3: 'NAP Form 3 - Request for Authority to Dispose of Records',
    requestLetterForNap: 'Request Letter for NAP',
    endorsementLetterToCoa: 'Endorsement Letter to COA',
} as const;

const TABLE_ACTION_BUTTON_CLASS = 'h-8 w-8 p-0';
const MOBILE_ACTION_BUTTON_CLASS = 'h-8 w-8 p-0';

const statusLabelByValue = computed<Record<string, string>>(() => {
    return props.options.status.reduce<Record<string, string>>((carry, option) => {
        carry[option.value] = option.label;
        return carry;
    }, {});
});

const dynamicStatusOptions = computed<Option[]>(() => {
    const statusesInTable = Array.from(new Set(props.batches.data.map((batch) => batch.status)));
    const currentStatus = statusFilter.value;

    if (currentStatus !== 'all' && !statusesInTable.includes(currentStatus)) {
        statusesInTable.push(currentStatus);
    }

    return [
        { value: 'all', label: 'All statuses' },
        ...statusesInTable.map((value) => ({
            value,
            label: statusLabelByValue.value[value]
                ?? value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        })),
    ];
});

const applyFilters = () => {
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);

    if (filterState.search && filterState.search.trim()) {
        params.set('search', filterState.search.trim());
    } else {
        params.delete('search');
    }

    if (statusFilter.value && statusFilter.value !== 'all') {
        params.set('status', statusFilter.value);
    } else {
        params.delete('status');
    }

    if (perPageFilter.value && perPageFilter.value !== '15') {
        params.set('per_page', perPageFilter.value);
    } else {
        params.delete('per_page');
    }

    params.delete('page');

    const newUrl = `${currentUrl.pathname}?${params.toString()}`;
    router.visit(newUrl, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['batches', 'filters', 'options'],
    });
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(
    () => filterState.search,
    () => {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 400);
    }
);

const resultLabel = computed(() => {
    if (props.batches.total === 0) {
        return 'Showing 0 PDF entries';
    }

    return `Showing ${props.batches.from ?? 1}-${props.batches.to ?? props.batches.data.length} of ${props.batches.total} PDF entries`;
});

const sortedBatches = computed(() => {
    const rows = [...props.batches.data];

    return rows.sort((first, second) => {
        let firstValue = '';
        let secondValue = '';

        if (sortField.value === 'reference') {
            firstValue = first.reference;
            secondValue = second.reference;
        } else {
            firstValue = first.status_label;
            secondValue = second.status_label;
        }

        const compareResult = firstValue.localeCompare(secondValue, undefined, { sensitivity: 'base' });

        return sortDirection.value === 'asc' ? compareResult : compareResult * -1;
    });
});

const handleSort = (field: SortField) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortField.value = field;
    sortDirection.value = 'asc';
};

const sortIcon = (field: SortField) => {
    if (sortField.value !== field) {
        return ArrowUpDown;
    }

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

const clearFilters = () => {
    filterState.search = '';
    statusFilter.value = 'all';
    perPageFilter.value = '15';

    applyFilters();
};

const openUrl = (url: string | null) => {
    if (!url) {
        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
};

const openPreview = (batch: BatchDocumentItem, document: DocumentInfo, title: string, key: string) => {
    const previewUrl = document.preview_url ?? document.download_url;
    const downloadUrl = document.download_url ?? document.preview_url;

    if (!previewUrl && !downloadUrl) {
        return;
    }

    viewerState.value = {
        file: {
            id: `${batch.id}-${key}`,
            file_name: title,
            file_size: 0,
            mime_type: 'application/pdf',
            created_at: document.generated_at ?? batch.created_at,
            file_path: previewUrl ?? '',
            retention_status: null,
            ocr_text: null,
            ocr_status: null,
        },
        previewUrl: previewUrl ?? '',
        downloadUrl: downloadUrl ?? '',
        previewAvailable: Boolean(document.file_exists && previewUrl),
        previewUnavailableReason: document.has_document && !document.file_exists ? 'missing' : null,
    };
};

const closePreview = () => {
    viewerState.value = null;
};

const documentAvailabilityLabel = (document: DocumentInfo): string => {
    if (document.file_exists) {
        return 'PDF ready';
    }

    if (document.has_document) {
        return 'Generated file missing';
    }

    return 'No generated PDF';
};

const visitLink = (url: string | null) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const paginationLabel = (label: string): string => {
    return label
        .replace(/&laquo;/g, '')
        .replace(/&raquo;/g, '')
        .replace(/<[^>]*>/g, '')
        .trim();
};

const statusBadgeClass = (status: string): string => {
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="Disposal Certificates" />

        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-4">
                <div class="relative min-w-0 flex-1">
                    <SearchInput v-model="filterState.search" placeholder="Search by reference, status, or mode" />
                </div>

                <div class="flex w-full flex-wrap items-center gap-2 md:w-auto md:flex-nowrap">
                    <span class="text-sm text-muted-foreground">Status</span>
                    <Select v-model="statusFilter" @update:model-value="applyFilters">
                        <SelectTrigger class="w-40 sm:w-44">
                            <SelectValue placeholder="Batch status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="statusOption in dynamicStatusOptions" :key="statusOption.value"
                                :value="statusOption.value">
                                {{ statusOption.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <span class="text-sm text-muted-foreground">Show</span>
                    <Select v-model="perPageFilter" @update:model-value="applyFilters">
                        <SelectTrigger class="w-20 sm:w-24">
                            <SelectValue placeholder="Per page" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="count in props.options.per_page" :key="count" :value="String(count)">
                                {{ count }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button variant="ghost" size="sm" @click="clearFilters">Clear</Button>
                </div>
            </div>

            <div class="hidden w-full min-w-0 flex-1 flex-col overflow-hidden rounded-xl border bg-card shadow-sm md:flex">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <p class="text-sm font-medium text-foreground">{{ resultLabel }}</p>
                </div>

                <div class="flex-1 overflow-x-auto w-full">
                    <Table class="w-full min-w-[1300px] table-fixed">
                    <TableHeader class="sticky top-0 z-10 bg-background [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed">
                        <TableRow>
                            <TableHead class="w-80 cursor-pointer select-none hover:bg-muted/50"
                                @click="() => handleSort('reference')">
                                <div class="flex items-center gap-2">
                                    <span>Batch</span>
                                    <component :is="sortIcon('reference')" class="h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="w-36 cursor-pointer select-none hover:bg-muted/50"
                                @click="() => handleSort('status')">
                                <div class="flex items-center gap-2">
                                    <span>Status</span>
                                    <component :is="sortIcon('status')" class="h-4 w-4" />
                                </div>
                            </TableHead>
                            <TableHead class="w-52">NAP Form 1</TableHead>
                            <TableHead class="w-52">NAP Form 3</TableHead>
                            <TableHead class="w-52">Request Letter for NAP</TableHead>
                            <TableHead class="w-52">Endorsement Letter to COA</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody
                        class="block max-h-[min(60vh,32rem)] overflow-y-auto [&_tr]:table [&_tr]:w-full [&_tr]:table-fixed">
                        <TableEmpty v-if="sortedBatches.length === 0" :colspan="6">
                            No generated PDF documents match the current filters.
                        </TableEmpty>

                        <TableRow v-for="batch in sortedBatches" :key="batch.id">
                            <TableCell class="w-80 align-top">
                                <div class="space-y-1">
                                    <Link :href="`/disposal-batches/${batch.id}`"
                                        class="font-medium text-foreground hover:text-primary hover:underline">
                                        {{ batch.reference }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ batch.actual_mode_of_disposal_label ?? 'Mode not set' }}
                                        <span class="mx-1">|</span>
                                        {{ batch.file_count }} file(s)
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Created {{ formatDateTime(batch.created_at) }}
                                    </p>
                                </div>
                            </TableCell>

                            <TableCell class="w-36 align-top">
                                <Badge variant="outline" :class="statusBadgeClass(batch.status)">
                                    {{ batch.status_label }}
                                </Badge>
                            </TableCell>

                            <TableCell class="w-52 align-top">
                                <div class="flex min-h-[106px] flex-col gap-2">
                                    <div class="min-h-[34px] space-y-1">
                                        <p class="text-xs text-muted-foreground">{{
                                            documentAvailabilityLabel(batch.nap_form_1) }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="batch.nap_form_1.generated_at">Generated {{
                                                formatDateTime(batch.nap_form_1.generated_at) }}</template>
                                            <template v-else>&nbsp;</template>
                                        </p>
                                    </div>
                                    <div class="mt-auto flex items-center gap-2">
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.nap_form_1.has_document"
                                            @click="openPreview(batch, batch.nap_form_1, PDF_TITLES.napForm1, 'nap-form-1')"
                                            :title="`Preview ${PDF_TITLES.napForm1}`"
                                            :aria-label="`Preview ${PDF_TITLES.napForm1}`">
                                            <Eye class="size-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.nap_form_1.download_url"
                                            @click="openUrl(batch.nap_form_1.download_url)"
                                            :title="`Download ${PDF_TITLES.napForm1}`"
                                            :aria-label="`Download ${PDF_TITLES.napForm1}`">
                                            <Download class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </TableCell>

                            <TableCell class="w-52 align-top">
                                <div class="flex min-h-[106px] flex-col gap-2">
                                    <div class="min-h-[34px] space-y-1">
                                        <p class="text-xs text-muted-foreground">{{
                                            documentAvailabilityLabel(batch.nap_form_3) }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="batch.nap_form_3.generated_at">Generated {{
                                                formatDateTime(batch.nap_form_3.generated_at) }}</template>
                                            <template v-else>&nbsp;</template>
                                        </p>
                                    </div>
                                    <div class="mt-auto flex items-center gap-2">
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.nap_form_3.has_document"
                                            @click="openPreview(batch, batch.nap_form_3, PDF_TITLES.napForm3, 'nap-form-3')"
                                            :title="`Preview ${PDF_TITLES.napForm3}`"
                                            :aria-label="`Preview ${PDF_TITLES.napForm3}`">
                                            <Eye class="size-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.nap_form_3.download_url"
                                            @click="openUrl(batch.nap_form_3.download_url)"
                                            :title="`Download ${PDF_TITLES.napForm3}`"
                                            :aria-label="`Download ${PDF_TITLES.napForm3}`">
                                            <Download class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </TableCell>

                            <TableCell class="w-52 align-top">
                                <div class="flex min-h-[106px] flex-col gap-2">
                                    <div class="min-h-[34px] space-y-1">
                                        <p class="text-xs text-muted-foreground">{{
                                            documentAvailabilityLabel(batch.request_letter_for_nap) }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="batch.request_letter_for_nap.generated_at">Generated {{
                                                formatDateTime(batch.request_letter_for_nap.generated_at) }}</template>
                                            <template v-else>&nbsp;</template>
                                        </p>
                                    </div>
                                    <div class="mt-auto flex items-center gap-2">
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.request_letter_for_nap.has_document"
                                            @click="openPreview(batch, batch.request_letter_for_nap, PDF_TITLES.requestLetterForNap, 'request-letter-for-nap')"
                                            :title="`Preview ${PDF_TITLES.requestLetterForNap}`"
                                            :aria-label="`Preview ${PDF_TITLES.requestLetterForNap}`">
                                            <Eye class="size-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.request_letter_for_nap.download_url"
                                            @click="openUrl(batch.request_letter_for_nap.download_url)"
                                            :title="`Download ${PDF_TITLES.requestLetterForNap}`"
                                            :aria-label="`Download ${PDF_TITLES.requestLetterForNap}`">
                                            <Download class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </TableCell>

                            <TableCell class="w-52 align-top">
                                <div class="flex min-h-[106px] flex-col gap-2">
                                    <div class="min-h-[34px] space-y-1">
                                        <p class="text-xs text-muted-foreground">{{
                                            documentAvailabilityLabel(batch.endorsement_letter_to_coa) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="batch.endorsement_letter_to_coa.generated_at">Generated {{
                                                formatDateTime(batch.endorsement_letter_to_coa.generated_at)
                                                }}</template>
                                            <template v-else>&nbsp;</template>
                                        </p>
                                    </div>
                                    <div class="mt-auto flex items-center gap-2">
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.endorsement_letter_to_coa.has_document"
                                            @click="openPreview(batch, batch.endorsement_letter_to_coa, PDF_TITLES.endorsementLetterToCoa, 'endorsement-letter-to-coa')"
                                            :title="`Preview ${PDF_TITLES.endorsementLetterToCoa}`"
                                            :aria-label="`Preview ${PDF_TITLES.endorsementLetterToCoa}`">
                                            <Eye class="size-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" :class="TABLE_ACTION_BUTTON_CLASS"
                                            :disabled="!batch.endorsement_letter_to_coa.download_url"
                                            @click="openUrl(batch.endorsement_letter_to_coa.download_url)"
                                            :title="`Download ${PDF_TITLES.endorsementLetterToCoa}`"
                                            :aria-label="`Download ${PDF_TITLES.endorsementLetterToCoa}`">
                                            <Download class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t px-4 py-3">
                    <Button v-for="link in props.batches.links" :key="link.label" size="sm" variant="outline"
                        :class="{ 'bg-muted': link.active }" :disabled="!link.url" @click="visitLink(link.url)">
                        {{ paginationLabel(link.label) }}
                    </Button>
                </div>
            </div>

            <div v-if="sortedBatches.length > 0" class="flex md:hidden flex-col gap-3">
                <div class="rounded-lg border bg-card px-4 py-3">
                    <p class="text-sm font-bold text-foreground">{{ resultLabel }}</p>
                </div>

                <div v-for="batch in sortedBatches" :key="batch.id" class="rounded-lg border bg-card p-4 shadow-sm">
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <Link :href="`/disposal-batches/${batch.id}`"
                                class="font-medium text-foreground hover:text-primary hover:underline">
                                {{ batch.reference }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ batch.actual_mode_of_disposal_label ?? 'Mode not set' }}
                                <span class="mx-1">|</span>
                                {{ batch.file_count }} file(s)
                            </p>
                            <p class="text-xs text-muted-foreground">Created {{ formatDateTime(batch.created_at) }}</p>
                        </div>
                        <Badge variant="outline" :class="statusBadgeClass(batch.status)">
                            {{ batch.status_label }}
                        </Badge>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">{{ PDF_TITLES.napForm1 }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ documentAvailabilityLabel(batch.nap_form_1)
                                }}</p>
                            <p v-if="batch.nap_form_1.generated_at" class="text-xs text-muted-foreground">Generated {{
                                formatDateTime(batch.nap_form_1.generated_at) }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.nap_form_1.has_document"
                                    @click="openPreview(batch, batch.nap_form_1, PDF_TITLES.napForm1, 'mobile-nap-form-1')"
                                    :title="`Preview ${PDF_TITLES.napForm1}`"
                                    :aria-label="`Preview ${PDF_TITLES.napForm1}`">
                                    <Eye class="size-4" />
                                </Button>
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.nap_form_1.download_url"
                                    @click="openUrl(batch.nap_form_1.download_url)"
                                    :title="`Download ${PDF_TITLES.napForm1}`"
                                    :aria-label="`Download ${PDF_TITLES.napForm1}`">
                                    <Download class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">{{ PDF_TITLES.napForm3 }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ documentAvailabilityLabel(batch.nap_form_3)
                                }}</p>
                            <p v-if="batch.nap_form_3.generated_at" class="text-xs text-muted-foreground">Generated {{
                                formatDateTime(batch.nap_form_3.generated_at) }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.nap_form_3.has_document"
                                    @click="openPreview(batch, batch.nap_form_3, PDF_TITLES.napForm3, 'mobile-nap-form-3')"
                                    :title="`Preview ${PDF_TITLES.napForm3}`"
                                    :aria-label="`Preview ${PDF_TITLES.napForm3}`">
                                    <Eye class="size-4" />
                                </Button>
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.nap_form_3.download_url"
                                    @click="openUrl(batch.nap_form_3.download_url)"
                                    :title="`Download ${PDF_TITLES.napForm3}`"
                                    :aria-label="`Download ${PDF_TITLES.napForm3}`">
                                    <Download class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">Request Letter for NAP</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{
                                documentAvailabilityLabel(batch.request_letter_for_nap)
                                }}</p>
                            <p v-if="batch.request_letter_for_nap.generated_at" class="text-xs text-muted-foreground">
                                Generated {{
                                    formatDateTime(batch.request_letter_for_nap.generated_at) }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.request_letter_for_nap.has_document"
                                    @click="openPreview(batch, batch.request_letter_for_nap, PDF_TITLES.requestLetterForNap, 'mobile-request-letter-for-nap')"
                                    :title="`Preview ${PDF_TITLES.requestLetterForNap}`"
                                    :aria-label="`Preview ${PDF_TITLES.requestLetterForNap}`">
                                    <Eye class="size-4" />
                                </Button>
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.request_letter_for_nap.download_url"
                                    @click="openUrl(batch.request_letter_for_nap.download_url)"
                                    :title="`Download ${PDF_TITLES.requestLetterForNap}`"
                                    :aria-label="`Download ${PDF_TITLES.requestLetterForNap}`">
                                    <Download class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div class="rounded-md border p-3">
                            <p class="text-xs font-medium text-muted-foreground">Endorsement Letter to COA</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{
                                documentAvailabilityLabel(batch.endorsement_letter_to_coa) }}</p>
                            <p v-if="batch.endorsement_letter_to_coa.generated_at"
                                class="text-xs text-muted-foreground">Generated
                                {{ formatDateTime(batch.endorsement_letter_to_coa.generated_at) }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.endorsement_letter_to_coa.has_document"
                                    @click="openPreview(batch, batch.endorsement_letter_to_coa, PDF_TITLES.endorsementLetterToCoa, 'mobile-endorsement-letter-to-coa')"
                                    :title="`Preview ${PDF_TITLES.endorsementLetterToCoa}`"
                                    :aria-label="`Preview ${PDF_TITLES.endorsementLetterToCoa}`">
                                    <Eye class="size-4" />
                                </Button>
                                <Button variant="outline" size="sm" :class="MOBILE_ACTION_BUTTON_CLASS"
                                    :disabled="!batch.endorsement_letter_to_coa.download_url"
                                    @click="openUrl(batch.endorsement_letter_to_coa.download_url)"
                                    :title="`Download ${PDF_TITLES.endorsementLetterToCoa}`"
                                    :aria-label="`Download ${PDF_TITLES.endorsementLetterToCoa}`">
                                    <Download class="size-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <Button v-for="link in props.batches.links" :key="`mobile-${link.label}`" size="sm"
                        variant="outline" :class="{ 'bg-muted': link.active }" :disabled="!link.url"
                        @click="visitLink(link.url)">
                        {{ paginationLabel(link.label) }}
                    </Button>
                </div>
            </div>

            <div v-else class="flex md:hidden flex-col items-center justify-center py-20 text-center">
                <FileText class="h-12 w-12 text-muted-foreground/50 mb-3" />
                <h2 class="text-lg font-semibold mb-2">No PDF Entries Found</h2>
                <p class="text-sm text-muted-foreground max-w-xs">No generated PDF documents match the current filters.
                </p>
                <Button size="sm" variant="outline" class="mt-3" @click="clearFilters">
                    Clear filters
                </Button>
            </div>

            <FileViewer v-if="viewerState" :file="viewerState.file" :preview-url="viewerState.previewUrl"
                :download-url="viewerState.downloadUrl" :preview-available="viewerState.previewAvailable"
                :preview-unavailable-reason="viewerState.previewUnavailableReason" :on-close="closePreview" />
        </div>
    </AppLayout>
</template>
