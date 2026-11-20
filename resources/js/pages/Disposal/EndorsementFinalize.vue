<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { today } from '@internationalized/date';
import { AlertCircle, ArrowLeft, CheckCircle2, ChevronDown, ChevronUp, Download, Eye, EyeOff, FileCheck, FileText, Image, Upload, X } from 'lucide-vue-next';

import {
    endorsementFinalizeSubmit,
    approvalLetterDownload,
    approvalLetterPreview,
    show as showBatch,
} from '@/actions/App/Http/Controllers/DisposalBatchController';
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import DatePicker from '@/components/common/DatePicker.vue';
import PersonNameRow from '@/components/common/PersonNameRow.vue';
import PdfPreviewPane from '@/components/common/PdfPreviewPane.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { formatDateTime } from '@/utils/format';
import type { BreadcrumbItem } from '@/types';

interface DocumentItem {
    name: string;
    path: string | null;
    storage_path: string | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
    mime_type: string | null;
    size: number | null;
}

type ContactPerson = {
    id: string | null;
    name: string;
    email: string | null;
    position: string | null;
};

interface Props {
    batch: {
        id: string;
        reference: string;
        status: string;
        file_count: number;
        mode_of_disposal_label: string | null;
        inspected_at: string | null;
        scheduled_at: string | null;
        approval_letter_path: string | null;
        approval_letter_generated_at: string | null;
        approval_letter_file_exists: boolean;
    };
    defaultData: {
        approval_notes: string;
        letter_reference: string | null;
        letter_date: string;
        actual_disposal_date: string | null;
        honorific: string;
        recipient_name: string;
        recipient_first_name: string;
        recipient_middle_name: string;
        recipient_last_name: string;
        recipient_extension: string;
        recipient_position: string | null;
        recipient_designation: string | null;
        recipient_address: string | null;
    };
    napDocuments: Array<{
        id: string;
        label: string;
        storage_path: string | null;
        download_url: string | null;
        original_name: string | null;
        uploaded_at: string | null;
    }>;
    currentUser: {
        id: string;
        name: string;
        email: string;
        position: string | null;
    };
    people: {
        inspector: ContactPerson | null;
        scheduler: ContactPerson | null;
    };
    director: {
        name: string;
        designation: string;
    };
    approvalDocuments: DocumentItem[];
    maxPictures: number;
}

interface PicturePreview {
    id: string;
    name: string;
    size: number;
    url: string;
    file: File;
}

interface NapDocumentField {
    id: string;
    label: string;
    storage_path: string | null;
    download_url: string | null;
    original_name: string | null;
    uploaded_at: string | null;
    file: File | null;
}

const cloneApprovalDocuments = (documents: DocumentItem[]): DocumentItem[] =>
    documents.map((document) => ({ ...document }));

const props = defineProps<Props>();
const { success, error } = useToast();
const { confirm: openConfirm } = useConfirm();

const confirmRemovalAction = async (title: string, description: string): Promise<boolean> => {
    const response = await openConfirm({
        title,
        description,
        confirmText: 'Remove',
        cancelText: 'Cancel',
    });

    return response === 'confirm';
};

const generateDocumentId = (): string => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `nap-doc-${Date.now()}-${Math.round(Math.random() * 1_000_000)}`;
};

const napDocuments = ref<NapDocumentField[]>(
    props.napDocuments.length
        ? props.napDocuments.map((document, index) => ({
            id: document.id || generateDocumentId(),
            label: document.label || `Supporting Document ${index + 1}`,
            storage_path: document.storage_path,
            download_url: document.download_url,
            original_name: document.original_name,
            uploaded_at: document.uploaded_at,
            file: null,
        }))
        : [
            {
                id: generateDocumentId(),
                label: 'NAP Form 1: Records Inventory and Appraisal',
                storage_path: null,
                download_url: null,
                original_name: null,
                uploaded_at: null,
                file: null,
            },
            {
                id: generateDocumentId(),
                label: 'NAP Form 3: Request for Authority to Dispose',
                storage_path: null,
                download_url: null,
                original_name: null,
                uploaded_at: null,
                file: null,
            },
            {
                id: generateDocumentId(),
                label: 'NAP Form 5: Authority to Dispose of Records',
                storage_path: null,
                download_url: null,
                original_name: null,
                uploaded_at: null,
                file: null,
            },
        ],
);
const isApprovedMode = computed(() => props.batch.status === 'approved');
const pageHeading = computed(() => isApprovedMode.value ? 'Endorsement Letter to COA Preview' : 'Finalize Endorsement Letter');
const pageSubtitle = computed(() =>
    isApprovedMode.value
    ? 'Review and regenerate the Endorsement Letter to COA PDF.'
    : 'Single-step endorsement, letter generation, and download.',
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Disposal Management', href: '/disposal-management' },
    { title: 'Disposal Batches', href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
    { title: pageHeading.value, href: '' },
]);

const form = useForm({
    approval_notes: props.defaultData.approval_notes,
    letter_reference: props.defaultData.letter_reference || `ENDORSEMENT-LETTER-COA-${props.batch.reference}`,
    letter_date: props.defaultData.letter_date,
    actual_disposal_date: props.defaultData.actual_disposal_date,
    honorific: props.defaultData.honorific || '',
    recipient_first_name: props.defaultData.recipient_first_name,
    recipient_middle_name: props.defaultData.recipient_middle_name || '',
    recipient_last_name: props.defaultData.recipient_last_name,
    recipient_extension: props.defaultData.recipient_extension || '',
    recipient_name: props.defaultData.recipient_name,
    recipient_position: props.defaultData.recipient_position || '',
    recipient_designation: props.defaultData.recipient_designation || '',
    recipient_address: props.defaultData.recipient_address || '',
    pictures: [] as File[],
    removed_documents: [] as string[],
    nap_documents: [] as Array<{
        id: string;
        label: string;
        existing_path: string | null;
        original_name: string | null;
        uploaded_at: string | null;
        file: File | null;
    }>,
});

const existingPhotoDocuments = ref<DocumentItem[]>(cloneApprovalDocuments(props.approvalDocuments));
const removedPhotoStoragePaths = ref<string[]>([]);

watch(
    () => props.approvalDocuments,
    (documents) => {
        existingPhotoDocuments.value = cloneApprovalDocuments(documents);
        removedPhotoStoragePaths.value = [];
    },
    { deep: true },
);

watch(removedPhotoStoragePaths, (paths) => {
    form.removed_documents = [...paths];
});

const letterDateMinValue = today('Asia/Manila');

const formatDateForPicker = (value: string | null | undefined): string => {
    const parsed = value ? new Date(value) : null;
    if (!parsed || Number.isNaN(parsed.valueOf())) {
        return '';
    }

    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');

    return `${month}/${day}/${parsed.getFullYear()}`;
};

const formatDateForDisplay = (value: string | null | undefined): string => {
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
const actualDisposalDatePickerValue = ref(formatDateForPicker(form.actual_disposal_date));

const syncNapDocumentsField = () => {
    form.nap_documents = napDocuments.value.map((document) => ({
        id: document.id,
        label: document.label,
        existing_path: document.storage_path,
        original_name: document.original_name,
        uploaded_at: document.uploaded_at,
        file: document.file ?? null,
    }));
};

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

watch(
    () => form.actual_disposal_date,
    (value) => {
        const formatted = formatDateForPicker(value);
        if (formatted !== actualDisposalDatePickerValue.value) {
            actualDisposalDatePickerValue.value = formatted;
        }
    },
);

watch(actualDisposalDatePickerValue, (value) => {
    const formatted = formatDateForDisplay(value);
    if (formatted !== form.actual_disposal_date) {
        form.actual_disposal_date = formatted;
    }
});

watch(napDocuments, () => {
    syncNapDocumentsField();
}, { deep: true, immediate: true });

const picturePreviews = ref<PicturePreview[]>([]);
const pictureInputRef = ref<HTMLInputElement | null>(null);
const previewDialog = ref(false);
const previewImageUrl = ref('');
const previewImageName = ref('');
const showPreview = ref(typeof window !== 'undefined' && window.innerWidth >= 1280);

const hasGeneratedPreview = computed(() =>
    Boolean(
        props.batch.approval_letter_generated_at &&
        props.batch.approval_letter_path &&
        props.batch.approval_letter_file_exists,
    ),
);
const hasGeneratedEndorsementLetter = computed(() => hasGeneratedPreview.value);
const previewPdfPath = ref<string | null>(null);
const previewVersion = ref(Date.now());
const previewErrorMessage = ref<string | null>(null);
const isPreviewSyncing = ref(false);
const hasPreviewDocument = computed(() => hasGeneratedPreview.value || Boolean(previewPdfPath.value));

const inlinePdfUrl = computed(() => {
    if (typeof window === 'undefined') {
        return null;
    }

    if (!hasGeneratedPreview.value && !previewPdfPath.value) {
        return null;
    }

    const url = new URL(approvalLetterDownload.url(props.batch.id), window.location.origin);
    url.searchParams.set('disposition', 'inline');
    url.searchParams.set('t', previewVersion.value.toString());

    if (previewPdfPath.value) {
        url.searchParams.set('preview_path', previewPdfPath.value);
    }

    return url.toString() + '#navpanes=0&view=FitH';
});

const addNapDocument = () => {
    napDocuments.value = [
        ...napDocuments.value,
        {
            id: generateDocumentId(),
            label: 'Supporting Document',
            storage_path: null,
            download_url: null,
            original_name: null,
            uploaded_at: null,
            file: null,
        },
    ];
};

const removeNapDocument = async (id: string) => {
    if (napDocuments.value.length <= 1) {
        error('Cannot remove document', 'At least one supporting document is required.');
        return;
    }

    const targetIndex = napDocuments.value.findIndex((document) => document.id === id);
    if (targetIndex < 0) {
        return;
    }

    const label = sanitizeDocumentLabel(napDocuments.value[targetIndex], targetIndex);
    const confirmed = await confirmRemovalAction(
        'Remove supporting document?',
        `“${label}” will no longer be included in the generated PDF bundle.`,
    );

    if (!confirmed) {
        return;
    }

    napDocuments.value = napDocuments.value.filter((document) => document.id !== id);
};

const handleNapDocumentFileChange = (id: string, event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    const document = napDocuments.value.find((item) => item.id === id);

    if (! document) {
        return;
    }

    if (file && file.type !== 'application/pdf') {
        error('Invalid file type', 'Only PDF files are accepted for supporting documents.');
        target.value = '';
        return;
    }

    document.file = file;

    target.value = '';
};

const clearNapDocumentFile = (id: string) => {
    const document = napDocuments.value.find((item) => item.id === id);
    if (! document) {
        return;
    }

    document.file = null;
};

const remainingSlots = computed(() =>
    Math.max(props.maxPictures - existingPhotoDocuments.value.length - picturePreviews.value.length, 0),
);
const totalPictureCount = computed(() => existingPhotoDocuments.value.length + picturePreviews.value.length);
const requiredFields = computed(() => [
    { key: 'letter_date', label: 'Letter Date', value: form.letter_date },
    { key: 'actual_disposal_date', label: 'Date of Actual Disposal', value: form.actual_disposal_date },
    { key: 'recipient_first_name', label: 'Recipient First Name', value: form.recipient_first_name },
    { key: 'recipient_last_name', label: 'Recipient Last Name', value: form.recipient_last_name },
    { key: 'recipient_position', label: 'Recipient Position', value: form.recipient_position },
    { key: 'recipient_designation', label: 'Recipient Designation', value: form.recipient_designation },
]);

const missingFieldEntries = computed(() =>
    requiredFields.value.filter((field) => !String(field.value || '').trim()),
);

const missingFieldKeys = computed(() => new Set(missingFieldEntries.value.map((field) => field.key)));
const hasFieldError = (key: string): boolean => missingFieldKeys.value.has(key);
const fieldErrorMessage = (key: string, label: string): string =>
    hasFieldError(key) ? `${label} is required.` : '';

const documentHasAttachment = (document: NapDocumentField): boolean => {
    return Boolean(document.storage_path || document.file);
};

const pendingNapDocs = computed(() =>
    napDocuments.value
        .map((document, index) => ({
            label: sanitizeDocumentLabel(document, index),
            hasPdf: documentHasAttachment(document),
        }))
        .filter((document) => !document.hasPdf)
        .map((document) => document.label),
);

const supplementalMissingMessages = computed(() => {
    const issues: string[] = [];

    if (totalPictureCount.value < 1) {
        issues.push('At least 1 documentation picture');
    }

    if (napDocuments.value.length === 0) {
        issues.push('At least 1 supporting document');
    }

    if (pendingNapDocs.value.length > 0) {
        issues.push(`Upload PDF for all supporting documents (${pendingNapDocs.value.join(', ')})`);
    }

    return issues;
});

const isPhotoRequirementUnmet = computed(() => totalPictureCount.value < 1);
const hasSupportingDocumentRequirementIssue = computed(
    () => napDocuments.value.length === 0 || pendingNapDocs.value.length > 0,
);

const allMissingFields = computed(() => [
    ...missingFieldEntries.value.map((field) => field.label),
    ...supplementalMissingMessages.value,
]);

const isFormValid = computed(() => allMissingFields.value.length === 0);

const goBack = () => {
    router.visit(showBatch.url(props.batch.id));
};

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const buildPreviewPayload = () => ({
    letter_reference: form.letter_reference,
    letter_date: form.letter_date,
    actual_disposal_date: form.actual_disposal_date,
    honorific: form.honorific,
    recipient_first_name: form.recipient_first_name,
    recipient_middle_name: form.recipient_middle_name,
    recipient_last_name: form.recipient_last_name,
    recipient_extension: form.recipient_extension,
    recipient_name: form.recipient_name,
    recipient_position: form.recipient_position,
    recipient_designation: form.recipient_designation,
    recipient_address: form.recipient_address,
    approval_notes: form.approval_notes,
});

const createPreviewFormData = (): FormData => {
    const payload = buildPreviewPayload();
    const formData = new FormData();

    const appendStringField = (key: string, value: string | null | undefined) => {
        if (value === null || value === undefined) {
            return;
        }

        formData.append(key, value);
    };

    Object.entries(payload).forEach(([key, value]) => {
        appendStringField(key, typeof value === 'string' ? value : value ?? '');
    });

    form.removed_documents.forEach((path, index) => {
        appendStringField(`removed_documents[${index}]`, path);
    });

    form.pictures.forEach((file) => {
        formData.append('pictures[]', file);
    });

    napDocuments.value.forEach((document, index) => {
        appendStringField(`nap_documents[${index}][id]`, document.id || `nap-doc-preview-${index}`);
        appendStringField(`nap_documents[${index}][label]`, sanitizeDocumentLabel(document, index));

        if (document.storage_path) {
            appendStringField(`nap_documents[${index}][existing_path]`, document.storage_path);
        }

        if (document.original_name) {
            appendStringField(`nap_documents[${index}][original_name]`, document.original_name);
        }

        if (document.uploaded_at) {
            appendStringField(`nap_documents[${index}][uploaded_at]`, document.uploaded_at);
        }

        if (document.file) {
            formData.append(`nap_documents[${index}][file]`, document.file);
        }
    });

    return formData;
};

const syncPdfPreview = () => {
    if (!isFormValid.value) {
        previewErrorMessage.value = `Please complete: ${allMissingFields.value.join(', ')}`;
        return;
    }

    previewErrorMessage.value = null;
    isPreviewSyncing.value = true;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const formData = createPreviewFormData();

    fetch(approvalLetterPreview.url(props.batch.id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: formData,
    })
        .then(async (response) => {
            if (response.ok) {
                return response.json();
            }

            let message = `Preview failed with status ${response.status}`;
            try {
                const data = await response.json();
                if (data?.message) {
                    message = String(data.message);
                } else if (data?.errors && typeof data.errors === 'object') {
                    const combined = Object.values(data.errors).flat().join(' ');
                    if (combined) {
                        message = combined;
                    }
                }
            } catch {
                // Ignore JSON parse errors and use default message.
            }

            throw new Error(message);
        })
        .then((payload) => {
            if (payload?.preview_path) {
                previewPdfPath.value = String(payload.preview_path);
            }
            previewVersion.value = Date.now();
            success('Preview synced', 'Live preview now shows the generated PDF.');
        })
        .catch((syncError: any) => {
            const message = String(syncError?.message || 'Failed to sync preview.');
            previewErrorMessage.value = message;
            error('Preview sync failed', message);
        })
        .finally(() => {
            isPreviewSyncing.value = false;
        });
};

const formatFileSize = (size: number | null) => {
    if (!size) return 'Unknown size';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const formatUploadedLabel = (value: string | null | undefined): string => {
    const formatted = formatDateTime(value ?? undefined);

    if (!formatted || formatted === '-') {
        return 'Unknown upload time';
    }

    return formatted;
};

const syncRecipientDerivedFields = () => {
    const segments = [
        form.recipient_first_name?.trim() ?? '',
        form.recipient_middle_name?.trim() ?? '',
        form.recipient_last_name?.trim() ?? '',
    ].filter((segment) => segment !== '');

    const extension = (form.recipient_extension || '').trim();
    const base = segments.join(' ');

    form.recipient_name = base ? `${base}${extension ? `, ${extension}` : ''}` : '';
};

watch(
    () => [
        form.recipient_first_name,
        form.recipient_middle_name,
        form.recipient_last_name,
        form.recipient_extension,
    ],
    () => {
        syncRecipientDerivedFields();
    },
    { immediate: true },
);

const sanitizeDocumentLabel = (document: NapDocumentField, index: number): string => {
    const label = document.label?.trim();

    if (label) {
        return label;
    }

    return `Supporting Document ${index + 1}`;
};

const moveNapDocument = (index: number, direction: 'up' | 'down'): void => {
    const targetIndex = direction === 'up' ? index - 1 : index + 1;

    if (targetIndex < 0 || targetIndex >= napDocuments.value.length) {
        return;
    }

    const updated = [...napDocuments.value];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIndex, 0, moved);
    napDocuments.value = updated;
};

const canMoveNapDocument = (index: number, direction: 'up' | 'down'): boolean => {
    if (direction === 'up') {
        return index > 0;
    }

    return index < napDocuments.value.length - 1;
};

const describeGenerationOutputs = (): string => {
    const formattedDocs = napDocuments.value.map((document, index) => ({
        label: sanitizeDocumentLabel(document, index),
        hasFile: Boolean(document.storage_path || document.file),
    }));

    const uploadedDocs = formattedDocs.filter((document) => document.hasFile);
    const pendingDocs = formattedDocs.filter((document) => !document.hasFile);

    const uploadsDescription = uploadedDocs.length
        ? `Supporting PDFs: ${uploadedDocs.map((document) => document.label).join(', ')}`
        : 'Supporting PDFs: none attached';

    const pendingDescription = pendingDocs.length
        ? `Pending uploads: ${pendingDocs.map((document) => document.label).join(', ')}`
        : 'No pending supporting documents.';

    const photosCount = totalPictureCount.value;
    const photoLine = photosCount > 0
        ? `Photo Documentations page (${photosCount} image${photosCount === 1 ? '' : 's'})`
        : 'Photo Documentations page (skipped — no photos attached)';

    const packageLabel = isApprovedMode.value ? 'an updated endorsement package' : 'the endorsement package';

    return `This action will generate ${packageLabel} containing the Endorsement Letter to COA PDF, ${photoLine}, and ${uploadsDescription}. ${pendingDescription}`;
};

const confirmGenerationIntent = async (): Promise<boolean> => {
    const response = await openConfirm({
        title: isApprovedMode.value ? 'Regenerate endorsement package?' : 'Finalize endorsement package?',
        description: describeGenerationOutputs(),
        confirmText: isApprovedMode.value ? 'Generate PDF' : 'Finalize & Generate',
        cancelText: 'Keep Editing',
    });

    return response === 'confirm';
};

const romanNumeralRegex = /^(?=[ivxlcdm]+$)m{0,4}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/i;

const formatTitleCaseSegment = (segment: string): string => {
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
};

const toSmartTitleCase = (value: string): string => {
    return value
        .trim()
        .replace(/\s+/g, ' ')
        .split(/(\s+|-|')/)
        .map((segment) => formatTitleCaseSegment(segment))
        .join('');
};

type RecipientTitleCaseField =
    | 'recipient_first_name'
    | 'recipient_middle_name'
    | 'recipient_last_name'
    | 'recipient_position'
    | 'recipient_designation';

const applyRecipientTitleCase = (field: RecipientTitleCaseField): void => {
    form[field] = toSmartTitleCase(form[field] || '');
    syncRecipientDerivedFields();
};

const applyAllRecipientTitleCase = (): void => {
    applyRecipientTitleCase('recipient_first_name');
    applyRecipientTitleCase('recipient_middle_name');
    applyRecipientTitleCase('recipient_last_name');
    applyRecipientTitleCase('recipient_position');
    applyRecipientTitleCase('recipient_designation');
};

const toSentenceCase = (value: string): string => {
    const trimmed = value.trim();

    if (!trimmed) {
        return '';
    }

    const chars = trimmed.split('');
    let shouldCapitalize = true;

    for (let index = 0; index < chars.length; index += 1) {
        const currentChar = chars[index];

        if (/[a-z]/.test(currentChar) && shouldCapitalize) {
            chars[index] = currentChar.toUpperCase();
            shouldCapitalize = false;
            continue;
        }

        if (/[A-Z]/.test(currentChar)) {
            shouldCapitalize = false;
            continue;
        }

        if (/[.!?]/.test(currentChar) || currentChar === '\n') {
            shouldCapitalize = true;
        }
    }

    return chars.join('');
};

const applyEndorsementNotesSentenceCase = (): void => {
    form.approval_notes = toSentenceCase(form.approval_notes || '');
};

const handlePicturesChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);

    if (files.length === 0) {
        return;
    }

    const existingFileNames = new Set([
        ...picturePreviews.value.map((preview) => preview.name),
        ...existingPhotoDocuments.value.map((document) => document.name),
    ]);

    const uniqueFiles = files.filter((file) => !existingFileNames.has(file.name));
    const duplicateFiles = files.filter((file) => existingFileNames.has(file.name));

    if (duplicateFiles.length > 0) {
        const duplicateNames = duplicateFiles.map((file) => file.name);
        const previewNames = duplicateNames.slice(0, 2).join(', ');
        const remainingCount = duplicateNames.length - 2;
        const suffix = remainingCount > 0 ? `, and ${remainingCount} more` : '';
        error('Duplicate files detected', `Already attached: ${previewNames}${suffix}`);
    }

    if (uniqueFiles.length === 0) {
        target.value = '';
        return;
    }

    if (uniqueFiles.length > remainingSlots.value) {
        error(
            'Picture limit reached',
            `You can only add ${remainingSlots.value} more picture${remainingSlots.value === 1 ? '' : 's'}.`,
        );
        target.value = '';
        return;
    }

    const newPreviews: PicturePreview[] = uniqueFiles.map((file) => ({
        id: `${Date.now()}-${Math.random()}-${file.name}`,
        name: file.name,
        size: file.size,
        url: URL.createObjectURL(file),
        file,
    }));

    picturePreviews.value = [...picturePreviews.value, ...newPreviews];

    await nextTick();

    form.pictures = picturePreviews.value.map((preview) => preview.file);
    target.value = '';
};

const removePicture = (id: string) => {
    const preview = picturePreviews.value.find((item) => item.id === id);
    if (preview) {
        URL.revokeObjectURL(preview.url);
    }

    picturePreviews.value = picturePreviews.value.filter((item) => item.id !== id);
    form.pictures = picturePreviews.value.map((item) => item.file);
};

const clearSelectedPictures = () => {
    picturePreviews.value.forEach((preview) => URL.revokeObjectURL(preview.url));
    picturePreviews.value = [];
    form.pictures = [];

    if (pictureInputRef.value) {
        pictureInputRef.value.value = '';
    }
};

const openPreview = (preview: PicturePreview) => {
    previewImageUrl.value = preview.url;
    previewImageName.value = preview.name;
    previewDialog.value = true;
};

const openExistingDocumentPreview = (document: DocumentItem) => {
    if (!document.path) {
        return;
    }

    previewImageUrl.value = document.path;
    previewImageName.value = document.name;
    previewDialog.value = true;
};

const removeExistingPicture = async (document: DocumentItem) => {
    const confirmed = await confirmRemovalAction(
        'Remove attachment?',
        `“${document.name}” will be removed from the endorsement package. This cannot be undone after saving.`,
    );

    if (!confirmed) {
        return;
    }

    existingPhotoDocuments.value = existingPhotoDocuments.value.filter((item) => item !== document);

    if (document.storage_path && !removedPhotoStoragePaths.value.includes(document.storage_path)) {
        removedPhotoStoragePaths.value = [...removedPhotoStoragePaths.value, document.storage_path];
    }
};

const openNapDocumentPreview = (document: NapDocumentField) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (document.file) {
        const objectUrl = URL.createObjectURL(document.file);
        window.open(objectUrl, '_blank', 'noopener,noreferrer');
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 10_000);
        return;
    }

    if (document.download_url) {
        window.open(document.download_url, '_blank', 'noopener,noreferrer');
    }
};

const closePreview = (open: boolean) => {
    previewDialog.value = open;
    if (!open) {
        previewImageUrl.value = '';
        previewImageName.value = '';
    }
};

const submitFinalize = async () => {
    if (!isFormValid.value) {
        error('Missing information', `Please complete: ${allMissingFields.value.join(', ')}`);
        return;
    }

    const shouldProceed = await confirmGenerationIntent();
    if (!shouldProceed) {
        return;
    }

    applyAllRecipientTitleCase();
    applyEndorsementNotesSentenceCase();
    syncRecipientDerivedFields();
    syncNapDocumentsField();

    const onSuccess = () => {
        allowNavigation.value = true;
        success(
            isApprovedMode.value ? 'Endorsement Letter to COA regenerated' : 'Endorsement completed',
            isApprovedMode.value
                ? 'Endorsement Letter to COA has been regenerated. Download will begin shortly.'
                : 'Batch endorsed and letter generated. Download will begin shortly.',
        );

        setTimeout(() => {
            window.location.href = approvalLetterDownload.url(props.batch.id, {
                query: {
                    format: 'pdf',
                    t: Date.now().toString(),
                },
            });
        }, 500);
    };

    form.post(endorsementFinalizeSubmit.url(props.batch.id), {
        preserveScroll: true,
        forceFormData: true,
        onError: (errors) => {
            error('Endorsement failed', Object.values(errors).join(' '));
        },
        onSuccess,
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
        <Head :title="`${pageHeading} - ${batch.reference}`" />

        <div class="border-b bg-background px-6 py-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">{{ pageHeading }}</h1>
                        <p class="text-sm text-muted-foreground">{{ pageSubtitle }}</p>
                    </div>
                </div>
                <Button variant="outline" size="sm" class="hidden lg:inline-flex" @click="togglePreview">
                        <Eye v-if="!showPreview" class="mr-2 h-4 w-4" />
                        <EyeOff v-else class="mr-2 h-4 w-4" />
                        {{ showPreview ? 'Hide' : 'Show' }} Preview
                    </Button>
            </div>
        </div>

        <div class="flex h-[calc(100vh-10rem)] overflow-hidden">
            <div
                class="min-w-0 flex-1 overflow-y-auto overflow-x-hidden transition-all duration-300"
                :class="showPreview ? 'w-full lg:w-1/2' : 'w-full'"
            >
                <div class="space-y-6 p-6">
                    <Card class="border-blue-200 bg-blue-50/60 dark:border-blue-800 dark:bg-blue-900/20">
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3 text-sm">
                                <FileText class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-300" />
                                <div class="space-y-2 text-blue-900 dark:text-blue-100">
                                    <p class="font-medium">Endorsement Checklist</p>
                                    <ul class="list-disc space-y-1 pl-5 text-blue-800 dark:text-blue-200">
                                        <li>Confirm recipient details and salutation fields.</li>
                                        <li>Add any final disposal notes for the audit trail.</li>
                                        <li>Upload up to {{ maxPictures }} photos as supporting documentation.</li>
                                        <li>Arrange the supporting PDFs (NAP Forms 1, 3, and 5 by default) plus any additional documents in the order they should appear.</li>
                                    </ul>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Letter Details - {{ form.letter_reference }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="letter-date">Letter Date <span class="text-red-500">*</span></Label>
                                <DatePicker
                                    id="letter-date"
                                    v-model="letterDatePickerValue"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="false"
                                    :min-value="letterDateMinValue"
                                    :invalid="hasFieldError('letter_date')"
                                />
                                <p class="text-xs" :class="hasFieldError('letter_date') ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ hasFieldError('letter_date') ? 'Letter Date is required.' : 'Converted to long-form (e.g., March 13, 2026) for the PDF.' }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="actual-disposal-date">Date of Actual Disposal <span class="text-red-500">*</span></Label>
                                <DatePicker
                                    id="actual-disposal-date"
                                    v-model="actualDisposalDatePickerValue"
                                    placeholder="MM/DD/YYYY"
                                    :allow-manual-entry="false"
                                    :invalid="hasFieldError('actual_disposal_date')"
                                />
                                <p class="text-xs" :class="hasFieldError('actual_disposal_date') ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ hasFieldError('actual_disposal_date') ? 'Date of Actual Disposal is required.' : 'Cited in the endorsement letter narrative and attachments summary.' }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <PersonNameRow
                                    v-model:prefix="form.honorific"
                                    v-model:first="form.recipient_first_name"
                                    v-model:middle="form.recipient_middle_name"
                                    v-model:last="form.recipient_last_name"
                                    v-model:extension="form.recipient_extension"
                                    prefix-placeholder="Select prefix"
                                    first-field-name="recipient_first_name"
                                    last-field-name="recipient_last_name"
                                    :first-error="fieldErrorMessage('recipient_first_name', 'Recipient First Name')"
                                    :last-error="fieldErrorMessage('recipient_last_name', 'Recipient Last Name')"
                                    @blur:first="() => applyRecipientTitleCase('recipient_first_name')"
                                    @blur:middle="() => applyRecipientTitleCase('recipient_middle_name')"
                                    @blur:last="() => applyRecipientTitleCase('recipient_last_name')"
                                    @blur:extension="() => { form.recipient_extension = form.recipient_extension?.trim() || ''; syncRecipientDerivedFields(); }"
                                />
                                <p class="text-xs text-muted-foreground">Used in the salutation and printed recipient block.</p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-1">
                                    <Label for="recipient-position">Recipient Position <span class="text-red-500">*</span></Label>
                                    <Input
                                        id="recipient-position"
                                        v-model="form.recipient_position"
                                        placeholder="Supervising Auditor"
                                        @blur="applyRecipientTitleCase('recipient_position')"
                                        @keydown.enter.prevent="applyRecipientTitleCase('recipient_position')"
                                        :class="[
                                            hasFieldError('recipient_position')
                                                ? 'border-destructive focus-visible:ring-destructive/40'
                                                : '',
                                        ]"
                                    />
                                    <p v-if="hasFieldError('recipient_position')" class="text-xs text-destructive">
                                        Recipient Position is required.
                                    </p>
                                </div>
                                <div class="space-y-2 md:col-span-1">
                                    <Label for="recipient-designation">Recipient Designation <span class="text-red-500">*</span></Label>
                                    <Input
                                        id="recipient-designation"
                                        v-model="form.recipient_designation"
                                        placeholder="Team Leader"
                                        @blur="applyRecipientTitleCase('recipient_designation')"
                                        @keydown.enter.prevent="applyRecipientTitleCase('recipient_designation')"
                                        :class="[
                                            hasFieldError('recipient_designation')
                                                ? 'border-destructive focus-visible:ring-destructive/40'
                                                : '',
                                        ]"
                                    />
                                    <p v-if="hasFieldError('recipient_designation')" class="text-xs text-destructive">
                                        Recipient Designation is required.
                                    </p>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-1">
                                <div class="space-y-2">
                                    <Label for="recipient-address">Recipient Address</Label>
                                    <Input
                                        id="recipient-address"
                                        v-model="form.recipient_address"
                                        placeholder="e.g. Davao City"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Endorsement Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                id="endorsement-notes"
                                v-model="form.approval_notes"
                                :rows="4"
                                placeholder="Add final remarks about the disposal..."
                                @blur="applyEndorsementNotesSentenceCase"
                                @keyup.enter="applyEndorsementNotesSentenceCase"
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between gap-3">
                                <CardTitle class="flex items-center gap-2">
                                    <FileCheck class="h-5 w-5" />
                                    Supporting Documents
                                </CardTitle>
                                <Button variant="outline" size="sm" @click="addNapDocument">
                                    Add Document
                                </Button>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Upload or update the supporting PDFs. NAP Forms 1, 3, and 5 are provided by default, but you can add or remove other documents as needed.
                            </p>
                            <p v-if="hasSupportingDocumentRequirementIssue" class="text-sm text-destructive">
                                {{ pendingNapDocs.length && napDocuments.length ? `Upload PDFs for: ${pendingNapDocs.join(', ')}` : 'Attach at least one supporting document PDF.' }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-for="(document, index) in napDocuments"
                                :key="document.id"
                                class="rounded-lg border p-4 space-y-3"
                                :class="documentHasAttachment(document) ? '' : 'border-destructive/60 bg-destructive/5'"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex-1 space-y-2">
                                        <Label :for="`nap-document-${document.id}`">Document Label</Label>
                                        <Input
                                            :id="`nap-document-${document.id}`"
                                            v-model="document.label"
                                            placeholder="e.g. NAP Form 1: Records Inventory and Appraisal"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-9 w-9"
                                            :disabled="!canMoveNapDocument(index, 'up')"
                                            @click="moveNapDocument(index, 'up')"
                                            title="Move up"
                                        >
                                            <ChevronUp class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-9 w-9"
                                            :disabled="!canMoveNapDocument(index, 'down')"
                                            @click="moveNapDocument(index, 'down')"
                                            title="Move down"
                                        >
                                            <ChevronDown class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            :disabled="napDocuments.length === 1"
                                            @click="removeNapDocument(document.id)"
                                            title="Remove document"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                                <div
                                    class="rounded-md border bg-muted/30 px-3 py-2 text-sm transition focus-within:outline-none focus-within:ring-2 focus-within:ring-primary/40"
                                    :class="[
                                        document.download_url || document.file
                                            ? 'cursor-pointer hover:border-primary/50'
                                            : 'cursor-default',
                                        documentHasAttachment(document)
                                            ? ''
                                            : 'border-destructive/70 bg-destructive/10 text-destructive',
                                    ]"
                                    role="button"
                                    :tabindex="document.download_url || document.file ? 0 : -1"
                                    @click="document.download_url || document.file ? openNapDocumentPreview(document) : undefined"
                                    @keydown.enter.prevent="document.download_url || document.file ? openNapDocumentPreview(document) : undefined"
                                    @keydown.space.prevent="document.download_url || document.file ? openNapDocumentPreview(document) : undefined"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-medium truncate">
                                                {{ document.file?.name || document.original_name || 'No PDF attached' }}
                                            </p>
                                            <p v-if="document.uploaded_at && !document.file" class="text-xs text-muted-foreground">
                                                Uploaded {{ formatUploadedLabel(document.uploaded_at) }}
                                            </p>
                                            <p v-else-if="document.file" class="text-xs text-muted-foreground">
                                                Ready to upload when finalized
                                            </p>
                                            <p v-else class="text-xs text-destructive">
                                                Attach a PDF for {{ sanitizeDocumentLabel(document, index) }}.
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                as-child
                                                class="gap-2"
                                            >
                                                <label class="inline-flex cursor-pointer items-center gap-2" @click.stop>
                                                    <Upload class="h-4 w-4" />
                                                    {{ document.file || document.storage_path ? 'Replace PDF' : 'Choose PDF' }}
                                                    <input
                                                        type="file"
                                                        accept="application/pdf"
                                                        class="hidden"
                                                        @change="handleNapDocumentFileChange(document.id, $event)"
                                                    />
                                                </label>
                                            </Button>
                                            <Button
                                                v-if="document.file"
                                                variant="ghost"
                                                size="sm"
                                                @click.stop="clearNapDocumentFile(document.id)"
                                            >
                                                Clear
                                            </Button>
                                            <Button
                                                v-if="!document.file && document.download_url"
                                                variant="outline"
                                                size="sm"
                                                as-child
                                                class="gap-2"
                                            >
                                                <a
                                                    :href="document.download_url"
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    @click.stop
                                                >
                                                    <Download class="mr-1 h-4 w-4" />
                                                    Download
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between gap-3">
                                <CardTitle>Photo Documentations</CardTitle>
                                <Button v-if="picturePreviews.length > 0" variant="ghost" size="sm" @click="clearSelectedPictures">Clear All</Button>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-if="remainingSlots > 0"
                                class="rounded-lg border border-dashed p-4 text-center"
                                :class="isPhotoRequirementUnmet ? 'border-destructive bg-destructive/5' : ''"
                            >
                                <label
                                    for="pictures"
                                    class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    <Image class="h-4 w-4" />
                                    Choose Files
                                </label>
                                <Input ref="pictureInputRef" id="pictures" type="file" accept="image/*" multiple class="hidden" @change="handlePicturesChange" />
                                <p class="mt-2 text-xs" :class="isPhotoRequirementUnmet ? 'text-destructive' : 'text-muted-foreground'">
                                    {{ isPhotoRequirementUnmet ? 'Attach at least one documentation picture.' : `${remainingSlots} slot${remainingSlots === 1 ? '' : 's'} left (max ${maxPictures} total).` }}
                                </p>
                            </div>
                            <div
                                v-else
                                class="rounded-lg border border-dashed p-4 text-center text-sm"
                                :class="isPhotoRequirementUnmet ? 'text-destructive border-destructive bg-destructive/5' : 'text-muted-foreground'"
                            >
                                Maximum pictures reached ({{ maxPictures }}/{{ maxPictures }})
                            </div>
                            <p v-if="isPhotoRequirementUnmet" class="text-xs text-destructive">
                                Upload at least one image; existing attachments count toward this requirement.
                            </p>

                            <div v-if="picturePreviews.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div v-for="preview in picturePreviews" :key="preview.id" class="group relative aspect-square overflow-hidden rounded-md border">
                                    <img :src="preview.url" :alt="preview.name" class="h-full w-full object-cover transition-transform group-hover:scale-105" />
                                    <div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100">
                                        <Button variant="secondary" size="icon" class="h-8 w-8" @click="openPreview(preview)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <Button variant="destructive" size="icon" class="h-8 w-8" @click="removePicture(preview.id)">
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div v-if="existingPhotoDocuments.length" class="space-y-2 border-t pt-4">
                                <p class="text-sm font-medium">Existing attachments already on file</p>
                                <div
                                    v-for="(document, index) in existingPhotoDocuments"
                                    :key="`${document.name}-${document.storage_path ?? index}`"
                                    class="flex items-start justify-between rounded-md border p-3 text-sm transition focus-within:outline-none focus-within:ring-2 focus-within:ring-primary/40"
                                    :class="document.path ? 'cursor-pointer hover:border-primary/60' : 'cursor-default opacity-70'"
                                    role="button"
                                    :tabindex="document.path ? 0 : -1"
                                    @click="document.path ? openExistingDocumentPreview(document) : undefined"
                                    @keydown.enter.prevent="document.path ? openExistingDocumentPreview(document) : undefined"
                                    @keydown.space.prevent="document.path ? openExistingDocumentPreview(document) : undefined"
                                >
                                    <div class="min-w-0 pr-3">
                                        <p class="font-medium">Attachment {{ index + 1 }}</p>
                                        <p class="break-all text-xs text-muted-foreground">{{ document.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Uploaded {{ formatUploadedLabel(document.uploaded_at) }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <Button
                                            v-if="document.path"
                                            variant="outline"
                                            size="sm"
                                            as-child
                                            class="gap-2"
                                        >
                                            <a :href="document.path" :download="document.name" @click.stop>
                                                <Download class="mr-1 h-4 w-4" />
                                                Download
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click.stop="removeExistingPicture(document)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="space-y-4 pt-6">
                            <div v-if="allMissingFields.length" class="rounded-md border border-orange-300 bg-orange-50 p-3 text-sm text-orange-800 dark:border-orange-700 dark:bg-orange-900/20 dark:text-orange-100">
                                <div class="mb-2 flex items-center gap-2 font-medium">
                                    <AlertCircle class="h-4 w-4" />
                                    Complete required fields before finalizing the endorsement:
                                </div>
                                <p>{{ allMissingFields.join(', ') }}</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <Button variant="outline" @click="goBack">Cancel</Button>
                                <Button class="bg-emerald-600 hover:bg-emerald-700" :disabled="form.processing || !isFormValid" @click="submitFinalize">
                                    <CheckCircle2 class="mr-2 h-4 w-4" />
                                    {{ hasGeneratedEndorsementLetter ? 'Regenerate & Download' : 'Generate & Download' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div v-if="showPreview" class="hidden h-full w-1/2 shrink-0 border-l border-border bg-muted/20 lg:block">
                <PdfPreviewPane
                    class="h-full"
                    :pdf-url="inlinePdfUrl"
                    :has-generated-preview="hasPreviewDocument"
                    :is-syncing="isPreviewSyncing"
                    :error-message="previewErrorMessage"
                    title="Endorsement Letter Preview"
                    iframe-title="Endorsement Letter PDF Preview"
                    @refresh="syncPdfPreview"
                />
            </div>
        </div>

        <ImagePreviewDialog
            :open="previewDialog"
            :image-url="previewImageUrl"
            :image-name="previewImageName"
            @update:open="closePreview"
        />
    </AppLayout>
</template>
