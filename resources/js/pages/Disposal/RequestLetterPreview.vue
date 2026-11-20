<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { today } from '@internationalized/date';
import { AlertCircle, ArrowLeft, CheckCircle2, FileText, Eye, EyeOff } from 'lucide-vue-next';

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
import DatePicker from '@/components/common/DatePicker.vue';
import PersonNameRow from '@/components/common/PersonNameRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { requestLetterGenerate, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';
import PdfPreviewPane from '@/components/common/PdfPreviewPane.vue';

const REQUEST_LETTER_DOWNLOAD_FLAG = 'requestLetterDownloadBatch';
const letterDateMinValue = today('Asia/Manila');

interface Props {
    batch: {
        id: string;
        reference: string;
        status: string;
        file_count: number;
        request_letter_path: string | null;
        request_letter_generated_at: string | null;
        request_letter_file_exists: boolean;
    };
    defaultData: {
        letter_date: string;
        recipient_name: string;
        recipient_first_name: string;
        recipient_middle_name: string;
        recipient_last_name: string;
        recipient_extension: string;
        recipient_position: string;
        recipient_designation: string;
        address_1: string;
        address_2: string;
        telephone: string;
        honorific: string;
        user_name: string;
        user_position: string;
        control_id: string;
    };
    director: {
        name: string;
        designation: string;
    };
    currentUser: {
        id: string;
        name: string;
        email: string;
        position: string | null;
    };
}

const props = defineProps<Props>();

const { success, error } = useToast();

const form = useForm({
    letter_date: props.defaultData.letter_date,
    recipient_name: props.defaultData.recipient_name,
    recipient_first_name: props.defaultData.recipient_first_name || '',
    recipient_middle_name: props.defaultData.recipient_middle_name || '',
    recipient_last_name: props.defaultData.recipient_last_name || '',
    recipient_extension: props.defaultData.recipient_extension || '',
    recipient_position: props.defaultData.recipient_position,
    recipient_designation: props.defaultData.recipient_designation,
    address_1: props.defaultData.address_1,
    address_2: props.defaultData.address_2,
    telephone: props.defaultData.telephone,
    // If the backend provided a generic default (e.g. 'Dr.') treat it as unset
    // so the UI shows "No Prefix" unless the user has explicitly saved a value.
    honorific:
        props.defaultData.honorific && props.defaultData.honorific !== 'Dr.'
            ? props.defaultData.honorific
            : '',
    user_name: props.defaultData.user_name || props.currentUser.name,
    user_position: props.defaultData.user_position || props.currentUser.position || '',
    control_id: props.defaultData.control_id || '',
});

const salutationPreviewLastName = computed(() => {
    const trimmed = (form.recipient_last_name || '').trim();
    if (trimmed !== '') {
        return trimmed.replace(/,$/, '');
    }

    const fallback = (form.recipient_name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .pop();

    return fallback ? fallback.replace(/,$/, '') : 'Lastname';
});

const salutationPreviewHonorific = computed(() => (form.honorific || '').trim());
const salutationPreviewText = computed(() => {
    return salutationPreviewHonorific.value
        ? `${salutationPreviewHonorific.value} ${salutationPreviewLastName.value}`
        : salutationPreviewLastName.value;
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: 'Request Letter', href: '' },
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

type TitleCaseField =
    | 'recipient_name'
    | 'recipient_position'
    | 'recipient_designation'
    | 'recipient_first_name'
    | 'recipient_middle_name'
    | 'recipient_last_name';

const applyTitleCase = (field: TitleCaseField): void => {
    form[field] = toSmartTitleCase(String(form[field] || ''));
};

const buildRecipientName = () => {
    const first = (form.recipient_first_name || '').trim();
    const middle = (form.recipient_middle_name || '').trim();
    const last = (form.recipient_last_name || '').trim();
    const extension = (form.recipient_extension || '').trim();

    const primary = [first, middle, last].filter((segment) => segment !== '').join(' ');
    form.recipient_name = primary
        ? `${primary}${extension !== '' ? `, ${extension}` : ''}`
        : '';
};

watch(
    () => [
        form.recipient_first_name,
        form.recipient_middle_name,
        form.recipient_last_name,
        form.recipient_extension,
    ],
    () => {
        buildRecipientName();
    },
    { immediate: true },
);

const formatDateForPicker = (value: string): string => {
    const parsed = value ? new Date(value) : null;
    if (!parsed || Number.isNaN(parsed.valueOf())) {
        return '';
    }

    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${month}/${day}/${parsed.getFullYear()}`;
};

const formatDateForDisplay = (value: string): string => {
    if (!value) {
        return '';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.valueOf())) {
        return value;
    }

    return parsed.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
};

const letterDatePickerValue = ref(formatDateForPicker(form.letter_date));

watch(
    () => form.letter_date,
    (value) => {
        const formatted = formatDateForPicker(value);
        if (formatted !== letterDatePickerValue.value) {
            letterDatePickerValue.value = formatted;
        }
    },
);

watch(letterDatePickerValue, (value) => {
    const formatted = formatDateForDisplay(value);
    if (formatted !== form.letter_date) {
        form.letter_date = formatted;
    }
});

const requiredFields = computed(() => {
    return [
        { key: 'letter_date', label: 'Letter Date', value: form.letter_date },
        { key: 'recipient_first_name', label: 'Recipient First Name', value: form.recipient_first_name },
        { key: 'recipient_last_name', label: 'Recipient Last Name', value: form.recipient_last_name },
        { key: 'recipient_position', label: 'Recipient Position', value: form.recipient_position },
        { key: 'recipient_designation', label: 'Recipient Designation', value: form.recipient_designation },
        { key: 'address_1', label: 'Address Line 1', value: form.address_1 },
    ];
});

const missingFields = computed(() => {
    return requiredFields.value.filter((field) => !String(field.value || '').trim());
});

const missingFieldLabels = computed(() => missingFields.value.map((field) => field.label));
const missingFieldKeys = computed(() => new Set(missingFields.value.map((field) => field.key)));
const hasFieldError = (key: string): boolean => missingFieldKeys.value.has(key);
const fieldErrorMessage = (key: string, label: string): string =>
    hasFieldError(key) ? `${label} is required.` : '';

const isFormValid = computed(() => missingFields.value.length === 0);

const isPreviewSyncing = ref(false);
const previewErrorMessage = ref<string | null>(null);
const hasGeneratedPreview = ref(
    Boolean(props.batch.request_letter_generated_at && props.batch.request_letter_path && props.batch.request_letter_file_exists),
);
const previewVersion = ref(Date.now());
const previewPdfPath = ref<string | null>(null);

const hasPreviewDocument = computed(() => hasGeneratedPreview.value || Boolean(previewPdfPath.value));

const inlinePdfUrl = computed(() => {
    if (typeof window === 'undefined') {
        return null;
    }

    if (previewPdfPath.value) {
        const query = new URLSearchParams({
            disposition: 'inline',
            preview_path: previewPdfPath.value,
            t: previewVersion.value.toString(),
        });

        return new URL(
            `/disposal-batches/${props.batch.id}/request-letter/download?${query.toString()}`,
            window.location.origin,
        ).toString() + '#navpanes=0&view=FitH';
    }

    if (!hasGeneratedPreview.value) {
        return null;
    }

    const query = new URLSearchParams({
        disposition: 'inline',
        t: previewVersion.value.toString(),
    });

    return new URL(
        `/disposal-batches/${props.batch.id}/request-letter/download?${query.toString()}`,
        window.location.origin,
    ).toString() + '#navpanes=0&view=FitH';
});

const showPreview = ref(typeof window !== 'undefined' && window.innerWidth >= 1024);
const finalizeDialogOpen = ref(false);

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const openFinalizeDialog = () => {
    if (!isFormValid.value || isDownloading.value) {
        return;
    }

    finalizeDialogOpen.value = true;
};

const rememberPendingDownload = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(REQUEST_LETTER_DOWNLOAD_FLAG, props.batch.id);
};

const generatePdf = async (
    mode: 'preview' | 'finalize',
): Promise<{ preview_path?: string; request_letter_path?: string }> => {
    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const payload = {
        ...form.data(),
        preview_sync: mode === 'preview',
    };

    const response = await fetch(requestLetterGenerate.url(props.batch.id), {
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
        // Use default message when body is not JSON.
    }

    throw new Error(message);
};

const isDownloading = ref(false);
const confirmFinalizeAndRedirect = () => {
    if (isDownloading.value) {
        return;
    }

    isDownloading.value = true;

    generatePdf('finalize')
        .then(() => {
            rememberPendingDownload();
            allowNavigation.value = true;
            router.visit(showBatch.url(props.batch.id));
        })
        .catch((generationError: any) => {
            error('Generation Failed', String(generationError?.message || 'Unable to generate Request Letter.'));
        })
        .finally(() => {
            isDownloading.value = false;
            finalizeDialogOpen.value = false;
        });
};

const syncPdfPreview = () => {
    if (!isFormValid.value) {
        previewErrorMessage.value = `Please complete: ${missingFieldLabels.value.join(', ')}`;
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
            const message = String(syncError?.message || 'Failed to sync preview.');
            previewErrorMessage.value = message;
            error('Sync Failed', message);
        })
        .finally(() => {
            isPreviewSyncing.value = false;
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
        <Head :title="`Request Letter - ${batch.reference}`" />

        <!-- Header - Full Width -->
        <div class="border-b bg-background px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">
                            Request Letter for NAP Preview
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Fill in recipient details and review before generating
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
                class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden transition-all duration-300"
                :class="showPreview ? 'w-full lg:w-1/2' : 'w-full'"
            >
                <div class="space-y-6 p-6">
                    <!-- Info Message -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-800 dark:bg-blue-900/10">
                        <div class="flex items-start gap-3">
                            <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-1">
                                    Request for Authority to Dispose of Records
                                </p>
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    This letter requests approval to dispose valueless records. The NAP Form 3 will be attached as additional pages when the PDF is generated.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Letter Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Letter Details</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label for="letter_date">Letter Date <span class="text-red-500">*</span></Label>
                                <DatePicker
                                    id="letter_date"
                                    v-model="letterDatePickerValue"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="false"
                                    :min-value="letterDateMinValue"
                                    :invalid="hasFieldError('letter_date')"
                                />
                                <p class="mt-1 text-xs" :class="hasFieldError('letter_date') ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ hasFieldError('letter_date') ? 'Letter Date is required.' : 'Converted to long-form (e.g., March 13, 2026) for the PDF.' }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recipient Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Recipient Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <PersonNameRow
                                    v-model:prefix="form.honorific"
                                    v-model:first="form.recipient_first_name"
                                    v-model:middle="form.recipient_middle_name"
                                    v-model:last="form.recipient_last_name"
                                    v-model:extension="form.recipient_extension"
                                    prefix-label="Prefix"
                                    prefix-placeholder="Select prefix"
                                    first-field-name="recipient_first_name"
                                    middle-field-name="recipient_middle_name"
                                    last-field-name="recipient_last_name"
                                    extension-field-name="recipient_extension"
                                    :first-error="fieldErrorMessage('recipient_first_name', 'Recipient First Name')"
                                    :last-error="fieldErrorMessage('recipient_last_name', 'Recipient Last Name')"
                                    @blur:first="() => applyTitleCase('recipient_first_name')"
                                    @blur:middle="() => applyTitleCase('recipient_middle_name')"
                                    @blur:last="() => applyTitleCase('recipient_last_name')"
                                    @blur:extension="() => (form.recipient_extension = form.recipient_extension?.trim() || '')"
                                />
                                <p class="text-xs text-muted-foreground">Used in: "Dear {{ salutationPreviewText }}:"</p>
                            </div>
                            <div>
                                <Label for="recipient_position">Position <span class="text-red-500">*</span></Label>
                                <Input
                                    id="recipient_position"
                                    v-model="form.recipient_position"
                                    placeholder="e.g. Head-RAN Davao"
                                    :class="[
                                        hasFieldError('recipient_position')
                                            ? 'border-destructive focus-visible:ring-destructive/40'
                                            : '',
                                    ]"
                                    :aria-invalid="hasFieldError('recipient_position') ? 'true' : undefined"
                                />
                                <p v-if="hasFieldError('recipient_position')" class="mt-1 text-xs text-destructive">
                                    Recipient Position is required.
                                </p>
                            </div>
                            <div>
                                <Label for="recipient_designation">Designation <span class="text-red-500">*</span></Label>
                                <Input
                                    id="recipient_designation"
                                    v-model="form.recipient_designation"
                                    placeholder="e.g. Regional Archival Network"
                                    :class="[
                                        hasFieldError('recipient_designation')
                                            ? 'border-destructive focus-visible:ring-destructive/40'
                                            : '',
                                    ]"
                                    :aria-invalid="hasFieldError('recipient_designation') ? 'true' : undefined"
                                />
                                <p v-if="hasFieldError('recipient_designation')" class="mt-1 text-xs text-destructive">
                                    Recipient Designation is required.
                                </p>
                            </div>
                            <div>
                                <Label for="address_1">Address Line 1 <span class="text-red-500">*</span></Label>
                                <Input
                                    id="address_1"
                                    v-model="form.address_1"
                                    placeholder="e.g. 2d Floor, KORE Devt. Bldg., Bgy. 004"
                                    :class="[
                                        hasFieldError('address_1')
                                            ? 'border-destructive focus-visible:ring-destructive/40'
                                            : '',
                                    ]"
                                    :aria-invalid="hasFieldError('address_1') ? 'true' : undefined"
                                />
                                <p v-if="hasFieldError('address_1')" class="mt-1 text-xs text-destructive">
                                    Address Line 1 is required.
                                </p>
                            </div>
                            <div>
                                <Label for="address_2">Address Line 2</Label>
                                <Input id="address_2" v-model="form.address_2" placeholder="e.g. A. Bonifacio St., Poblacion, Davao City" />
                            </div>
                            <div>
                                <Label for="telephone">Telephone Number</Label>
                                <Input id="telephone" v-model="form.telephone" placeholder="e.g. (082) 297-4705" />
                                <p class="mt-1 text-xs text-muted-foreground">Optional. Leave blank for a fill-in line on the PDF.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- User Information (for letter body) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Requesting Officer</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <Label for="user_name">Requesting Officer</Label>
                                <Input id="user_name" v-model="form.user_name" readonly disabled class="bg-muted" />
                                <p class="mt-1 text-xs text-muted-foreground">Sourced from the latest Prepared By details of NAP Form 1.</p>
                            </div>
                            <div>
                                <Label for="user_position">Position</Label>
                                <Input id="user_position" v-model="form.user_position" readonly disabled class="bg-muted" />
                                <p class="mt-1 text-xs text-muted-foreground">Update NAP Form 1 if this information needs to change.</p>
                            </div>
                            <div>
                                <Label for="control_id">Control ID</Label>
                                <Input id="control_id" v-model="form.control_id" placeholder="e.g. 2022-06-22-0049" />
                                <p class="mt-1 text-xs text-muted-foreground">Optional. Leave blank for a fill-in line on the PDF.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Validation + Actions -->
                    <Card>
                        <CardContent class="space-y-4 pt-6">
                            <div v-if="missingFieldLabels.length" class="rounded-md border border-orange-300 bg-orange-50 p-3 text-sm text-orange-800 dark:border-orange-700 dark:bg-orange-900/20 dark:text-orange-100">
                                <div class="mb-2 flex items-center gap-2 font-medium">
                                    <AlertCircle class="h-4 w-4" />
                                    Complete required fields before finalizing:
                                </div>
                                <p>{{ missingFieldLabels.join(', ') }}</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <Button variant="outline" @click="goBack">Cancel</Button>
                                <Button class="bg-emerald-600 hover:bg-emerald-700" :disabled="!isFormValid || isDownloading" @click="openFinalizeDialog">
                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                    {{ isDownloading ? 'Generating...' : (batch.request_letter_path ? 'Regenerate & Download' : 'Generate & Download') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Right Panel: Live Preview -->
            <div v-if="showPreview" class="hidden h-full w-1/2 shrink-0 border-l border-border lg:block">
                <PdfPreviewPane
                    class="h-full w-full"
                    :pdf-url="inlinePdfUrl"
                    :has-generated-preview="hasPreviewDocument"
                    :is-syncing="isPreviewSyncing"
                    :error-message="previewErrorMessage"
                    iframe-title="Request Letter PDF Preview"
                    @refresh="syncPdfPreview"
                />
            </div>
        </div>

        <Dialog v-model:open="finalizeDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Generate Request Letter?</DialogTitle>
                    <DialogDescription>
                        The PDF bundles your latest Request Letter followed by the finalized NAP Form 3 so you only manage one file.
                    </DialogDescription>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    After confirming, you'll return to the disposal batch detail page where the download will begin automatically.
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="finalizeDialogOpen = false" :disabled="isDownloading">Cancel</Button>
                    <Button class="bg-emerald-600 hover:bg-emerald-700" @click="confirmFinalizeAndRedirect" :disabled="isDownloading">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        {{ isDownloading ? 'Generating...' : 'Confirm & Download' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
