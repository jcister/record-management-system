<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ArrowLeft, Download, Eye, EyeOff, FileText } from 'lucide-vue-next';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { napForm3Download, napForm3Generate, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';
import PdfPreviewPane from '@/components/common/PdfPreviewPane.vue';

interface Props {
    batch: {
        id: string;
        reference: string;
        status: string;
        file_count: number;
        nap_report_generated_at: string | null;
        nap_report_file_exists: boolean;
    };
    defaultData: {
        agency_name: string;
        address: string;
        date: string;
        telephone_number: string;
        location_of_records: string;
        volume_in_cubic_meter: string;
        prepared_by_user_id: string | null;
        prepared_by_name: string;
        prepared_by_position: string;
        certified_by_name: string;
        certified_by_designation: string;
        items: Array<{
            grds_rds_item_no: string;
            record_series_title: string;
            period_covered: string;
            retention_period: string;
        }>;
    };
}

const props = defineProps<Props>();

const NAP_REFRESH_STORAGE_KEY = 'napRefreshBatch';
const NAP_FORM3_DOWNLOAD_EVENT = 'nap:form3-downloaded';

const triggerFileDownload = async (url: string, fileName: string) => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Download failed with status ${response.status}`);
    }

    const blob = await response.blob();
    const objectUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(objectUrl);
};

const wait = (milliseconds: number) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const downloadWithRetry = async (url: string, fileName: string) => {
    const maxAttempts = 8;

    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        try {
            await triggerFileDownload(url, fileName);
            return;
        } catch (downloadError: any) {
            const message = String(downloadError?.message || '');
            const isNotFound = message.includes('status 404');

            if (!isNotFound || attempt === maxAttempts) {
                throw downloadError;
            }

            await wait(400 * attempt);
        }
    }
};

const markNapDownloadPending = () => {
    if (typeof window === 'undefined') return;
    window.sessionStorage.setItem(NAP_REFRESH_STORAGE_KEY, props.batch.id);
};

const notifyNapDownload = () => {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(NAP_FORM3_DOWNLOAD_EVENT, { detail: { batchId: props.batch.id } }));
};

const { success, error } = useToast();

const form = useForm({
    agency_name: props.defaultData.agency_name,
    address: props.defaultData.address,
    date: props.defaultData.date,
    telephone_number: props.defaultData.telephone_number,
    location_of_records: props.defaultData.location_of_records,
    volume_in_cubic_meter: props.defaultData.volume_in_cubic_meter,
    prepared_by_user_id: props.defaultData.prepared_by_user_id,
    prepared_by_name: props.defaultData.prepared_by_name,
    prepared_by_position: props.defaultData.prepared_by_position,
    certified_by_name: props.defaultData.certified_by_name,
    certified_by_designation: props.defaultData.certified_by_designation,
    items: props.defaultData.items,
});

// Pagination for Records in Disposal Batch table
const recordsPage = ref(1);
const recordsPerPage = ref(15);
const paginatedRecords = computed(() => {
    const start = (recordsPage.value - 1) * recordsPerPage.value;
    const end = start + recordsPerPage.value;
    return form.items.slice(start, end);
});
const recordsTotalPages = computed(() =>
    Math.ceil(form.items.length / recordsPerPage.value)
);
const recordsFrom = computed(() =>
    form.items.length > 0 ? (recordsPage.value - 1) * recordsPerPage.value + 1 : 0
);
const recordsTo = computed(() =>
    Math.min(recordsPage.value * recordsPerPage.value, form.items.length)
);

const goToRecordsPage = (page: number) => {
    recordsPage.value = page;
};

const changeRecordsPerPage = (newPerPage: number) => {
    recordsPerPage.value = newPerPage;
    recordsPage.value = 1;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: 'NAP Form 3', href: '' },
];

const goBack = () => {
    router.visit(showBatch.url(props.batch.id));
};

const romanNumeralRegex = /^(?=[ivxlcdm]+$)m{0,4}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/i;

const toSmartTitleCase = (value: string): string => {
    return value
        .trim()
        .replace(/\s+/g, ' ')
        .split(/(\s+|-|')/)
        .map((segment) => {
            if (segment.trim() === '' || segment === '-' || segment === "'") {
                return segment;
            }

            if (/^[a-z]\.?$/i.test(segment)) {
                return segment.charAt(0).toUpperCase() + (segment.endsWith('.') ? '.' : '');
            }

            if (romanNumeralRegex.test(segment)) {
                return segment.toUpperCase();
            }

            return segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase();
        })
        .join('');
};

const applyLocationTitleCase = (): void => {
    form.location_of_records = toSmartTitleCase(form.location_of_records || '');
};

const handleVolumeInput = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    const rawValue = target.value;
    form.volume_in_cubic_meter = rawValue ? Math.floor(Number(rawValue)).toString() : '';
};

const isDownloading = ref(false);
const isPreviewSyncing = ref(false);
const previewErrorMessage = ref<string | null>(null);
const hasGeneratedPreview = ref(Boolean(props.batch.nap_report_generated_at && props.batch.nap_report_file_exists));
const previewVersion = ref(Date.now());
const previewPdfPath = ref<string | null>(null);
const finalizeDownloadDialogOpen = ref(false);

const hasPreviewDocument = computed(() => hasGeneratedPreview.value || Boolean(previewPdfPath.value));

const inlinePdfUrl = computed(() => {
    if (previewPdfPath.value && typeof window !== 'undefined') {
        const query = new URLSearchParams({
            disposition: 'inline',
            preview_path: previewPdfPath.value,
            t: previewVersion.value.toString(),
        });

        return new URL(`/disposal-batches/${props.batch.id}/nap-form-3/download?${query.toString()}`, window.location.origin).toString() + '#navpanes=0&view=FitH';
    }

    if (!hasGeneratedPreview.value || typeof window === 'undefined') {
        return null;
    }

    const query = new URLSearchParams({
        disposition: 'inline',
        t: previewVersion.value.toString(),
    });

    return new URL(`/disposal-batches/${props.batch.id}/nap-form-3/download?${query.toString()}`, window.location.origin).toString() + '#navpanes=0&view=FitH';
});

// Validation for required fields
const isFormValid = computed(() => {
    return !!(form.location_of_records && form.prepared_by_user_id);
});

const missingFields = computed(() => {
    const missing: string[] = [];
    if (!form.location_of_records) missing.push('Location of Records');
    if (!form.prepared_by_user_id) missing.push('Prepared By (linked to NAP Form 1)');
    return missing;
});

// Preview pane visibility - default to visible on desktop
const showPreview = ref(typeof window !== 'undefined' && window.innerWidth >= 1280);

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const generatePdf = async (mode: 'preview' | 'finalize'): Promise<{ preview_path?: string }> => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const payload = {
        ...form.data(),
        preview_sync: mode === 'preview',
    };

    const response = await fetch(napForm3Generate.url(props.batch.id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
    });

    if (response.ok) {
        return await response.json();
    }

    let message = `Generation failed with status ${response.status}`;
    try {
        const payload = await response.json();
        if (payload?.message) {
            message = String(payload.message);
        } else if (payload?.errors && typeof payload.errors === 'object') {
            const joined = Object.values(payload.errors).flat().join(' ');
            if (joined) {
                message = joined;
            }
        }
    } catch {
        // Use fallback message when response is not JSON.
    }

    throw new Error(message);
};

const syncPdfPreview = () => {
    if (!isFormValid.value) {
        previewErrorMessage.value = `Please complete: ${missingFields.value.join(', ')}`;
        return;
    }

    previewErrorMessage.value = null;
    isPreviewSyncing.value = true;

    generatePdf('preview')
        .then((payload) => {
            if (payload?.preview_path) {
                previewPdfPath.value = String(payload.preview_path);
            }
            previewVersion.value = Date.now();
            success('Preview Synced', 'Live preview now shows the latest generated PDF output.');
        })
        .catch((syncError: any) => {
            previewErrorMessage.value = String(syncError?.message || 'Failed to sync preview.');
            error('Sync Failed', previewErrorMessage.value);
        })
        .finally(() => {
            isPreviewSyncing.value = false;
        });
};

const openFinalizeDownloadDialog = () => {
    if (!isFormValid.value) {
        return;
    }

    finalizeDownloadDialogOpen.value = true;
};

const confirmFinalizeAndDownloadReport = () => {
    if (!isFormValid.value) {
        return;
    }

    finalizeDownloadDialogOpen.value = false;
    isDownloading.value = true;
    applyLocationTitleCase();

    generatePdf('finalize')
        .then(async () => {
            const url = napForm3Download.url(props.batch.id, {
                query: {
                    format: 'pdf',
                    t: Date.now().toString(),
                },
            });

            await downloadWithRetry(url, `nap-form-3-${props.batch.reference}.pdf`);
            markNapDownloadPending();
            notifyNapDownload();
            previewPdfPath.value = null;
            hasGeneratedPreview.value = true;
            previewVersion.value = Date.now();
            success('NAP Form 3 Ready', 'Final NAP Form 3 PDF has been generated and downloaded.');

            setTimeout(() => {
                allowNavigation.value = true;
                router.visit(showBatch.url(props.batch.id));
            }, 200);
        })
        .catch((finalizeError: any) => {
            error('Finalize Failed', String(finalizeError?.message || 'Failed to finalize NAP Form 3 PDF.'));
        })
        .finally(() => {
            isDownloading.value = false;
        });
};

const allowNavigation = ref(false);

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (form.isDirty && !allowNavigation.value) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
};

let removeRouterListener: (() => void) | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeRouterListener = router.on('before', (event) => {
        if (form.isDirty && !allowNavigation.value) {
            if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                event.preventDefault();
            }
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    if (removeRouterListener) {
        removeRouterListener();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`NAP Form 3 - Request for Authority to Dispose of Records - ${batch.reference}`" />

        <!-- Header - Full Width -->
        <div class="border-b bg-background px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">
                            NAP Form 3 - Request for Authority to Dispose of Records
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Prepared By follows NAP Form 1. Update Form 1 if you need to change it before finalizing this report.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Badge variant="outline" class="capitalize">
                        {{ batch.reference }}
                    </Badge>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="togglePreview"
                        class="hidden lg:flex"
                    >
                        <Eye v-if="!showPreview" class="mr-2 h-4 w-4" />
                        <EyeOff v-else class="mr-2 h-4 w-4" />
                        {{ showPreview ? 'Hide' : 'Show' }} Preview
                    </Button>
                </div>
            </div>
        </div>

        <!-- Split Layout: Form + Preview -->
        <div class="flex h-[calc(100vh-10rem)] overflow-hidden">
            <!-- Left Panel: Form Fields -->
            <div
                class="min-w-0 overflow-y-auto overflow-x-hidden transition-all duration-300"
                :class="showPreview ? 'w-full lg:w-1/2' : 'w-full'"
            >
                <div class="space-y-6 p-6">
                    <!-- Simplified Info Message -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                        <div class="flex items-start gap-3">
                            <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-1">
                                    NAP Form No. 3 - Request for Authority to Dispose of Records
                                </p>
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    Agency information is pre-configured from System Settings and the Prepared By fields mirror NAP Form 1. Review the records below and supply the remaining required details before generating the PDF.
                                </p>
                            </div>
                        </div>
                    </div>

            <!-- Disposal Batch Records -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span>Records to be Disposed</span>
                        <Badge variant="secondary" class="text-xs">
                            {{ form.items.length }} {{ form.items.length === 1 ? 'record' : 'records' }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Desktop table with horizontal scroll when preview is shown -->
                    <div class="hidden md:block" :class="showPreview ? 'overflow-x-auto' : ''">
                        <Table :class="[showPreview ? 'min-w-[850px]' : '', 'table-fixed']">
                            <TableHeader>
                                <TableRow>
                                    <TableHead :class="showPreview ? 'w-[150px]' : 'w-[200px]'">GRDS/RDS Item No.</TableHead>
                                    <TableHead :class="showPreview ? 'w-[300px]' : 'w-[450px]'">Record Series Title</TableHead>
                                    <TableHead :class="showPreview ? 'w-[250px]' : 'w-[250px]'">Period Covered</TableHead>
                                    <TableHead :class="showPreview ? 'w-[150px]' : 'w-[200px]'">Retention Period</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(item, index) in paginatedRecords" :key="index">
                                    <TableCell class="overflow-hidden">
                                        <TooltipProvider :delay-duration="300">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="text-xs block truncate cursor-default">
                                                        {{ item.grds_rds_item_no }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="text-sm">{{ item.grds_rds_item_no }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="overflow-hidden">
                                        <TooltipProvider :delay-duration="300">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="text-xs block truncate cursor-default">
                                                        {{ item.record_series_title }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-md">
                                                    <p class="text-sm">{{ item.record_series_title }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="overflow-hidden">
                                        <span class="text-xs truncate block">{{ item.period_covered || 'N/A' }}</span>
                                    </TableCell>
                                    <TableCell class="overflow-hidden">
                                        <span class="text-xs truncate block">{{ item.retention_period || 'N/A' }}</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Mobile: card list for records -->
                    <div v-if="paginatedRecords.length > 0" class="md:hidden flex flex-col gap-3">
                        <div v-for="(item, index) in paginatedRecords" :key="index" class="rounded-lg border bg-card p-4 shadow-sm hover:bg-muted/50 transition-colors">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-sm truncate">{{ item.record_series_title }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ item.grds_rds_item_no }}</p>
                                    </div>
                                    <Badge variant="outline" class="text-xs ml-2 self-start">{{ item.retention_period || 'N/A' }}</Badge>
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    {{ item.period_covered || 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination for Records -->
                    <div v-if="form.items.length > 0" class="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 md:flex-row md:items-center md:justify-between md:rounded-none md:border-none md:bg-transparent md:px-0 md:py-0">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ recordsFrom }}–{{ recordsTo }} of {{ form.items.length }} records
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 w-full md:w-auto">
                            <div class="flex items-center gap-2 flex-none">
                                <Label class="text-xs text-muted-foreground">Show</Label>
                                <Select
                                    :model-value="String(recordsPerPage)"
                                    @update:model-value="(val) => changeRecordsPerPage(Number(val))"
                                >
                                    <SelectTrigger class="h-8 w-16 text-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="15">15</SelectItem>
                                        <SelectItem value="20">20</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
                                        <SelectItem value="100">100</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex items-center gap-1 ml-auto md:ml-0">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="goToRecordsPage(recordsPage - 1)"
                                    :disabled="recordsPage === 1"
                                    class="h-8 px-2 flex-none"
                                >
                                    Previous
                                </Button>
                                <div class="px-3 text-xs text-muted-foreground min-w-0 text-center">
                                    Page {{ recordsPage }} of {{ recordsTotalPages }}
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="goToRecordsPage(recordsPage + 1)"
                                    :disabled="recordsPage === recordsTotalPages"
                                    class="h-8 px-2 flex-none"
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Required Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Complete Required Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Location and Volume -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="location_of_records" class="text-sm font-medium">
                                Location of Records <span class="text-red-500">*</span>
                            </Label>
                            <Input
                                id="location_of_records"
                                v-model="form.location_of_records"
                                placeholder="e.g., Storage Room, Building A, 2nd Floor"
                                class="h-10"
                                @blur="applyLocationTitleCase"
                                @keydown.enter.prevent="applyLocationTitleCase"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="volume_in_cubic_meter" class="text-sm font-medium">
                                Volume in Cubic Meter
                            </Label>
                            <div class="relative">
                                <Input
                                    id="volume_in_cubic_meter"
                                    v-model="form.volume_in_cubic_meter"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="e.g., 5"
                                    class="h-10 pr-10"
                                    @input="handleVolumeInput"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground pointer-events-none">
                                    m³
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">Optional - Numbers only</p>
                        </div>
                    </div>

                    <!-- Prepared By -->
                    <div class="space-y-3 border-t pt-4">
                        <div class="flex items-center justify-between gap-2">
                            <Label class="text-sm font-medium">Prepared By</Label>
                            <Badge variant="outline" class="text-[11px] uppercase tracking-wide">Linked to NAP Form 1</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Update NAP Form 1 to change who prepared this report. The details shown here are read-only to keep both forms in sync.
                        </p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label class="text-sm text-muted-foreground">Name</Label>
                                <div class="rounded-md border bg-muted/30 px-3 py-2.5 text-sm h-11 flex items-center">
                                    {{ form.prepared_by_name || 'Not captured yet' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-sm text-muted-foreground">Position</Label>
                                <div class="rounded-md border bg-muted/30 px-3 py-2.5 text-sm h-11 flex items-center">
                                    {{ form.prepared_by_position || 'Position not set in profile' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Generate Certificate Action -->
            <Card class="border-2">
                <CardContent class="pt-6">
                    <div class="flex flex-col gap-4">
                        <!-- Warning for missing fields -->
                        <div
                            v-if="!isFormValid"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/10"
                        >
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                Required fields missing
                            </p>
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                Please complete: {{ missingFields.join(', ') }}
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                            {{ isFormValid ? 'Ready to finalize the NAP Form 3 PDF?' : 'Complete the form to continue' }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-1">
                                            {{ isFormValid ? 'Use Sync PDF to refresh the preview, then finalize and download the PDF.' : 'Fill in all required fields above.' }}
                                </p>
                            </div>
                                    <Button
                                        @click="openFinalizeDownloadDialog"
                                        :disabled="form.processing || isDownloading || !isFormValid"
                                        size="lg"
                                        class="sm:shrink-0"
                                    >
                                        <Download class="mr-2 h-4 w-4" />
                                        {{ isDownloading ? 'Generating PDF...' : 'Finalize & Download PDF' }}
                                    </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
                </div>
            </div>

            <!-- Right Panel: Live Preview -->
            <div v-if="showPreview" class="hidden lg:block w-1/2 shrink-0 border-l border-border overflow-hidden">
                <div class="h-full">
                    <PdfPreviewPane
                        :pdf-url="inlinePdfUrl"
                        :has-generated-preview="hasPreviewDocument"
                        :is-syncing="isPreviewSyncing"
                        :error-message="previewErrorMessage"
                        iframe-title="NAP Form 3 PDF Preview"
                        @refresh="syncPdfPreview"
                    />
                </div>
            </div>
        </div>

        <Dialog v-model:open="finalizeDownloadDialogOpen">
            <DialogContent class="sm:max-w-110">
                <DialogHeader>
                    <DialogTitle>Finalize NAP Form 3 PDF</DialogTitle>
                    <DialogDescription>
                        This regenerates NAP Form 3 with your most recent details and downloads the final PDF. The Request Letter will also use this refreshed version, so everything stays in sync.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" @click="finalizeDownloadDialogOpen = false">Cancel</Button>
                    <Button @click="confirmFinalizeAndDownloadReport" :disabled="isDownloading">
                        <Download class="mr-2 h-4 w-4" />
                        {{ isDownloading ? 'Generating PDF...' : 'Confirm & Download' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
