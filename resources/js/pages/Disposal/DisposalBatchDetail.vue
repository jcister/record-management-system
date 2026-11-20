<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import axios from 'axios';
import {
    AlertCircle,
    Archive,
    ArrowLeft,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Clock,
    Download,
    Edit,
    Eye,
    FileText,
    Info,
    Mail,
    Plus,
    RotateCcw,
    Search,
    Send,
    ShieldCheck,
    Trash2,
    User,
} from 'lucide-vue-next';

import { napForm1Download, napForm1Preview, napForm3Download, napForm3Preview, approvalLetterDownload, endorsementFinalize, requestLetterPreview, requestLetterDownload, update as updateBatch, complete as completeBatch, availableFiles as availableFilesRoute, addFiles, removeFile, destroy as destroyBatch, scheduleInspection, markAsInspected, confirmNapAuthority, confirmCoaSubmission, returnToDraft, show as showBatch } from '@/actions/App/Http/Controllers/DisposalBatchController';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
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
import { Textarea } from '@/components/ui/textarea';
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import DisposalFilterComponent from '@/components/disposal/DisposalFilterComponent.vue';
import ItemMetadataSheet from '@/components/records/ItemMetadataSheet.vue';
import FileViewer from '@/components/records/FileViewer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItem } from '@/types';
import { formatDate, formatDateTime } from '@/utils/format';

interface DisposalBatchItem {
    id: string;
    file_id: string;
    file: {
        id: string;
        record_id: string;
        file_name: string;
        author: string;
        physical_location: string | null;
        physical_location_tags: { id: number; name: string }[];
        retention_status: string;
        file_path: string | null;
        deleted_at: string | null;
        restored_at: string | null;
    } | null;
    status: string;
    scheduled_at: string | null;
    completed_at: string | null;
    physical_snapshot: string | null;
}

interface DisposalAudit {
    id: string;
    action: string;
    description: string | null;
    user: { id: string; name: string } | null;
    meta: Record<string, unknown> | null;
    created_at: string;
}

interface ApprovalDocument {
    name: string;
    path: string | null;
    mime_type: string | null;
    size: number | null;
    uploaded_at: string | null;
    uploaded_by: string | null;
}

interface CoaDetails {
    name: string | null;
    position: string | null;
    designation: string | null;
}

interface ModeOption {
    value: string;
    label: string;
}

interface DisposalBatch {
    id: string;
    reference: string;
    sequence: number;
    status: string;
    file_count: number;
    disposed_files_count: number;
    scheduled_by: { id: string; name: string; email: string } | null;
    scheduled_at: string | null;
    scheduled_note: string | null;
    mode_of_disposal: string | null;
    mode_of_disposal_label: string | null;
    actual_mode_of_disposal: string | null;
    actual_mode_of_disposal_label: string | null;
    completed_by: { id: string; name: string; email: string } | null;
    completed_at: string | null;
    nap_report_path: string | null;
    nap_report_file_exists: boolean;
    nap_report_stage: string | null;
    nap_report_generated_at: string | null;
    nap_report_generated_by: { id: string; name: string; email: string } | null;
    nap_report_downloaded_at: string | null;
    nap_certificate_details: {
        certificate_date: string | null;
    } | null;
    request_letter_path: string | null;
    request_letter_file_exists: boolean;
    request_letter_generated_at: string | null;
    request_letter_generated_by: { id: string; name: string; email: string } | null;
    request_letter_downloaded_at: string | null;
    request_letter_details: {
        letter_date: string | null;
    } | null;
    nap_form_1_path: string | null;
    nap_form_1_stage: string | null;
    nap_form_1_generated_at: string | null;
    nap_form_1_generated_by: { id: string; name: string; email: string } | null;
    nap_form_1_downloaded_at: string | null;
    nap_form_1_details: {
        date_prepared: string | null;
    } | null;
    inspection_scheduled_at: string | null;
    inspection_scheduled_by: { id: string; name: string; email: string } | null;
    inspected_by: { id: string; name: string; email: string } | null;
    inspector_name: string | null;
    inspected_at: string | null;
    inspection_notes: string | null;
    approved_by: { id: string; name: string; email: string } | null;
    approved_at: string | null;
    approval_notes: string | null;
    approval_documents: ApprovalDocument[];
    approval_letter_path: string | null;
    approval_letter_file_exists: boolean;
    approval_letter_generated_at: string | null;
    approval_letter_generated_by: { id: string; name: string; email: string } | null;
    approval_letter_details: {
        letter_date: string | null;
        downloaded_at: string | null;
    } | null;
    nap_authority_issued_at: string | null;
    nap_authority_confirmed_by: { id: string; name: string; email: string } | null;
    report_submitted_to_coa_at: string | null;
    report_submitted_by: { id: string; name: string; email: string } | null;
    coa: CoaDetails | null;
    approval_review_details: {
        coa_name: string | null;
        coa_position: string | null;
        coa_designation: string | null;
    } | null;
    rejected_by: { id: string; name: string; email: string } | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    created_at: string;
    items: DisposalBatchItem[];
    audits: DisposalAudit[];
}

type Props = {
    batch: DisposalBatch;
    modeOptions: ModeOption[];
};

const props = defineProps<Props>();

const NAP_REFRESH_STORAGE_KEY = 'napRefreshBatch';
const NAP_FORM3_DOWNLOAD_EVENT = 'nap:form3-downloaded';
const NAP_FORM_1_REFRESH_STORAGE_KEY = 'napForm1RefreshBatch';
const NAP_FORM_1_DOWNLOAD_EVENT = 'nap:form1-downloaded';
const REQUEST_LETTER_DOWNLOAD_FLAG = 'requestLetterDownloadBatch';
const napReloadTimer = ref<number | null>(null);
const hasDownloadedNap = ref(Boolean(props.batch.nap_report_downloaded_at));
const hasDownloadedNapForm1 = ref(Boolean(props.batch.nap_form_1_downloaded_at));

watch(() => props.batch.nap_report_downloaded_at, (downloadedAt) => {
    if (downloadedAt) {
        hasDownloadedNap.value = true;
    }
});

watch(() => props.batch.nap_form_1_downloaded_at, (downloadedAt) => {
    if (downloadedAt) {
        hasDownloadedNapForm1.value = true;
    }
});

const triggerFileDownload = (url: string) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.location.href = url;
};

const clearNapRefreshFlag = () => {
    if (typeof window === 'undefined') return;

    const stored = window.sessionStorage.getItem(NAP_REFRESH_STORAGE_KEY);
    if (stored === props.batch.id) {
        window.sessionStorage.removeItem(NAP_REFRESH_STORAGE_KEY);
    }
};

const clearNapForm1RefreshFlag = () => {
    if (typeof window === 'undefined') return;

    const stored = window.sessionStorage.getItem(NAP_FORM_1_REFRESH_STORAGE_KEY);
    if (stored === props.batch.id) {
        window.sessionStorage.removeItem(NAP_FORM_1_REFRESH_STORAGE_KEY);
    }
};

const scheduleNapRefresh = () => {
    if (typeof window === 'undefined') return;

    if (napReloadTimer.value) {
        window.clearTimeout(napReloadTimer.value);
    }

    napReloadTimer.value = window.setTimeout(() => {
        router.reload({
            only: ['batch'],
            preserveScroll: true,
            onFinish: () => {
                napReloadTimer.value = null;
                clearNapRefreshFlag();
                clearNapForm1RefreshFlag();
            },
        });
    }, 800);
};

const markNapDownloadPending = () => {
    if (typeof window === 'undefined') return;
    window.sessionStorage.setItem(NAP_REFRESH_STORAGE_KEY, props.batch.id);
};

const notifyNapDownload = () => {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(NAP_FORM3_DOWNLOAD_EVENT, { detail: { batchId: props.batch.id } }));
};

const markNapForm1DownloadPending = () => {
    if (typeof window === 'undefined') return;
    window.sessionStorage.setItem(NAP_FORM_1_REFRESH_STORAGE_KEY, props.batch.id);
};

const notifyNapForm1Download = () => {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(NAP_FORM_1_DOWNLOAD_EVENT, { detail: { batchId: props.batch.id } }));
};

const consumeRequestLetterAutoDownloadFlag = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    const pendingBatchId = window.sessionStorage.getItem(REQUEST_LETTER_DOWNLOAD_FLAG);
    if (pendingBatchId === props.batch.id) {
        window.sessionStorage.removeItem(REQUEST_LETTER_DOWNLOAD_FLAG);
        return true;
    }

    return false;
};

const triggerRequestLetterAutoDownload = () => {
    if (!props.batch.request_letter_path || !props.batch.request_letter_file_exists) {
        return;
    }

    success('Request Letter Ready', 'Downloading the Request Letter with the attached NAP Form 3.');
    downloadRequestLetter();
};

const handleNapDownloadEvent = (event: Event) => {
    const detail = (event as CustomEvent<{ batchId?: string }>).detail;
    if (!detail || detail.batchId !== props.batch.id) {
        return;
    }

    hasDownloadedNap.value = true;
    scheduleNapRefresh();
};

const handleNapForm1DownloadEvent = (event: Event) => {
    const detail = (event as CustomEvent<{ batchId?: string }>).detail;
    if (!detail || detail.batchId !== props.batch.id) {
        return;
    }

    hasDownloadedNapForm1.value = true;
    scheduleNapRefresh();
};

onMounted(() => {
    if (typeof window === 'undefined') return;

    window.addEventListener(NAP_FORM3_DOWNLOAD_EVENT, handleNapDownloadEvent);
    window.addEventListener(NAP_FORM_1_DOWNLOAD_EVENT, handleNapForm1DownloadEvent);

    const pendingBatchId = window.sessionStorage.getItem(NAP_REFRESH_STORAGE_KEY);
    if (pendingBatchId === props.batch.id) {
        hasDownloadedNap.value = true;
        scheduleNapRefresh();
    }

    const pendingForm1BatchId = window.sessionStorage.getItem(NAP_FORM_1_REFRESH_STORAGE_KEY);
    if (pendingForm1BatchId === props.batch.id) {
        hasDownloadedNapForm1.value = true;
        scheduleNapRefresh();
    }

    if (consumeRequestLetterAutoDownloadFlag()) {
        triggerRequestLetterAutoDownload();
    }
});

onBeforeUnmount(() => {
    if (napReloadTimer.value) {
        window.clearTimeout(napReloadTimer.value);
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener(NAP_FORM3_DOWNLOAD_EVENT, handleNapDownloadEvent);
        window.removeEventListener(NAP_FORM_1_DOWNLOAD_EVENT, handleNapForm1DownloadEvent);
    }
});

const page = usePage();
const warningMessage = computed(() => {
    const flash = page.props.flash as Record<string, string | null> | undefined;
    const message = flash?.warning;

    return typeof message === 'string' && message.trim() !== '' ? message : null;
});

const { success, error } = useToast();
const editDialogOpen = ref(false);
const completeDialogOpen = ref(false);
const isSubmitting = ref(false);
const infoSheetOpen = ref(false);
const selectedFileId = ref<string | null>(null);
const selectedFileName = ref<string>('');
const addFilesDialogOpen = ref(false);
const napAuthorityDialogOpen = ref(false);
const napAuthorityForm = reactive({
    actual_mode_of_disposal: '',
    has_form5: false,
});
const isNapAuthoritySubmitting = ref(false);
const coaSubmissionDialogOpen = ref(false);
const coaSubmissionConfirmed = ref(false);
const isCoaSubmissionSubmitting = ref(false);
const availableFiles = ref<any[]>([]);
const selectedFilesToAdd = ref<Set<string>>(new Set());
const searchQuery = ref('');
const isLoadingFiles = ref(false);
const currentPage = ref(1);
const perPageOptions = [15, 20, 50, 100];
const filesPerPage = ref(20);
const pagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
    from: null,
    to: null,
});

// Pagination for Files in Batch table
const batchFilesPage = ref(1);
const batchFilesPerPage = ref(15);

// Sorting for Files in Batch table
const batchFilesSortColumn = ref<'record_id' | 'file_name' | 'author' | 'status' | null>(null);
const batchFilesSortDirection = ref<'asc' | 'desc'>('asc');

const sortedBatchItems = computed(() => {
    if (!batchFilesSortColumn.value) {
        return props.batch.items;
    }

    const items = [...props.batch.items];
    const column = batchFilesSortColumn.value;
    const direction = batchFilesSortDirection.value;

    return items.sort((a, b) => {
        let aValue: string | null = null;
        let bValue: string | null = null;

        switch (column) {
            case 'record_id':
                aValue = a.file?.record_id ?? '';
                bValue = b.file?.record_id ?? '';
                break;
            case 'file_name':
                aValue = a.file?.file_name ?? '';
                bValue = b.file?.file_name ?? '';
                break;
            case 'author':
                aValue = a.file?.author ?? '';
                bValue = b.file?.author ?? '';
                break;
            case 'status':
                aValue = a.file?.retention_status ?? '';
                bValue = b.file?.retention_status ?? '';
                break;
        }

        if (aValue === null || aValue === '') return direction === 'asc' ? 1 : -1;
        if (bValue === null || bValue === '') return direction === 'asc' ? -1 : 1;

        const comparison = aValue.localeCompare(bValue, undefined, { numeric: true, sensitivity: 'base' });
        return direction === 'asc' ? comparison : -comparison;
    });
});

const paginatedBatchItems = computed(() => {
    const start = (batchFilesPage.value - 1) * batchFilesPerPage.value;
    const end = start + batchFilesPerPage.value;
    return sortedBatchItems.value.slice(start, end);
});
const batchFilesTotalPages = computed(() =>
    Math.ceil(props.batch.items.length / batchFilesPerPage.value)
);
const batchFilesFrom = computed(() =>
    props.batch.items.length > 0 ? (batchFilesPage.value - 1) * batchFilesPerPage.value + 1 : 0
);
const batchFilesTo = computed(() =>
    Math.min(batchFilesPage.value * batchFilesPerPage.value, props.batch.items.length)
);

const goToBatchFilesPage = (page: number) => {
    batchFilesPage.value = page;
};

const changeBatchFilesPerPage = (newPerPage: number) => {
    batchFilesPerPage.value = newPerPage;
    batchFilesPage.value = 1;
};

const toggleSort = (column: 'record_id' | 'file_name' | 'author' | 'status') => {
    if (batchFilesSortColumn.value === column) {
        // Toggle direction or clear sort
        if (batchFilesSortDirection.value === 'asc') {
            batchFilesSortDirection.value = 'desc';
        } else {
            // Clear sort after desc
            batchFilesSortColumn.value = null;
            batchFilesSortDirection.value = 'asc';
        }
    } else {
        // Set new column, default to asc
        batchFilesSortColumn.value = column;
        batchFilesSortDirection.value = 'asc';
    }
    // Reset to first page when sorting changes
    batchFilesPage.value = 1;
};

const getSortIcon = (column: 'record_id' | 'file_name' | 'author' | 'status') => {
    if (batchFilesSortColumn.value !== column) {
        return ArrowUpDown;
    }
    return batchFilesSortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};
const removeFileDialogOpen = ref(false);
const fileToRemove = ref<{ id: string; name: string } | null>(null);
const deleteBatchDialogOpen = ref(false);
const inspectDialogOpen = ref(false);
const scheduleInspectionDialogOpen = ref(false);
const requestLetterWarningDialogOpen = ref(false);
const requestLetterSentConfirmed = ref(false);
const returnToDraftDialogOpen = ref(false);
const inspectionNotes = ref('');
const inspectorName = ref('');
const inspectorNameError = ref('');
const inspectionResult = ref<'passed' | 'rejected'>('passed');
const inspectionRejectionReason = ref('');
const inspectionScheduledAt = ref('');
const returnToDraftReason = ref('');

watch(scheduleInspectionDialogOpen, (isOpen) => {
    if (!isOpen) {
        inspectionScheduledAt.value = '';
        requestLetterSentConfirmed.value = false;
    }
});

watch(coaSubmissionDialogOpen, (isOpen) => {
    if (!isOpen) {
        coaSubmissionConfirmed.value = false;
    }
});

const romanNumeralRegex = /^(?=[ivxlcdm]+$)m{0,4}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/i;

const toInspectorTitleCase = (value: string): string => {
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

const applyInspectorTitleCase = (): void => {
    inspectorName.value = toInspectorTitleCase(inspectorName.value);
};

const applyInspectionNotesSentenceCase = (): void => {
    inspectionNotes.value = toSentenceCase(inspectionNotes.value);
};

const applyInspectionRejectionReasonSentenceCase = (): void => {
    inspectionRejectionReason.value = toSentenceCase(inspectionRejectionReason.value);
};

const applyReturnToDraftReasonSentenceCase = (): void => {
    returnToDraftReason.value = toSentenceCase(returnToDraftReason.value);
};

// Image preview state
const imagePreviewOpen = ref(false);
const previewImageUrl = ref('');
const previewImageName = ref('');

// PDF viewer state
const viewerState = ref<any>(null);

const openPDFViewer = (title: string, downloadAction: any, hasFile: boolean) => {
    if (!hasFile) return;

    const query: Record<string, string> = {
        disposition: 'inline',
        t: Date.now().toString(),
    };
    const downloadQuery: Record<string, string> = {
        t: Date.now().toString(),
    };

    if (title.includes('Form')) {
        query.format = 'pdf';
        downloadQuery.format = 'pdf';
    }

    const previewUrl = downloadAction.url(props.batch.id, { query });
    const downloadUrl = downloadAction.url(props.batch.id, { query: downloadQuery });

    viewerState.value = {
        file: {
            id: `${props.batch.id}-${title.toLowerCase().replace(/\s+/g, '-')}`,
            file_name: title,
            file_size: 0,
            mime_type: 'application/pdf',
            created_at: props.batch.created_at,
            file_path: previewUrl,
            retention_status: null,
            ocr_text: null,
            ocr_status: null,
        },
        previewUrl,
        downloadUrl,
        previewAvailable: true,
        previewUnavailableReason: null,
    };
};

const closePreview = () => {
    viewerState.value = null;
};

// Filter states for Add Files modal
const showFilters = ref(false);
const urgencyFilter = ref('all');
const retentionDurationFilter = ref('');
const rootFolderFilter = ref('');
const departmentFilter = ref('');
const expiresFromFilter = ref('');
const expiresToFilter = ref('');
const releasedFromFilter = ref('');
const releasedToFilter = ref('');

// Options for filters (fetched with available files)
const filterOptions = ref<{
    retention_durations: { value: string; label: string; description: string }[];
    root_folders: { id: string; name: string }[];
    departments: { id: string | number; name: string }[];
}>({
    retention_durations: [],
    root_folders: [],
    departments: [],
});

const editForm = reactive({
    scheduled_at: '',
    completed_at: '',
    scheduled_note: '',
});

const toManilaDateTimeLocalValue = (value?: string | Date | null): string => {
    if (!value) {
        return '';
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);

    const partValue = (type: Intl.DateTimeFormatPartTypes): string => {
        return parts.find((part) => part.type === type)?.value ?? '';
    };

    const year = partValue('year');
    const month = partValue('month');
    const day = partValue('day');
    const hour = partValue('hour');
    const minute = partValue('minute');

    if (!year || !month || !day || !hour || !minute) {
        return '';
    }

    return `${year}-${month}-${day}T${hour}:${minute}`;
};

const openNapAuthorityDialog = () => {
    napAuthorityForm.actual_mode_of_disposal = props.batch.actual_mode_of_disposal
        ?? props.batch.mode_of_disposal
        ?? '';
    napAuthorityForm.has_form5 = Boolean(props.batch.nap_authority_issued_at);
    napAuthorityDialogOpen.value = true;
};

const submitNapAuthority = () => {
    if (!napAuthorityForm.actual_mode_of_disposal) {
        error('Actual mode required', 'Select the actual mode of disposal before saving.');
        return;
    }

    if (!napAuthorityForm.has_form5) {
        error('NAP Form 5 required', 'Confirm that NAP has issued the Form 5 authority before saving.');
        return;
    }

    isNapAuthoritySubmitting.value = true;

    router.post(confirmNapAuthority.url(props.batch.id), {
        actual_mode_of_disposal: napAuthorityForm.actual_mode_of_disposal,
        nap_form_5_received: napAuthorityForm.has_form5,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors).flat().find(Boolean);
            error('Unable to record authority', typeof firstError === 'string' ? firstError : 'Please review the form and try again.');
        },
        onSuccess: () => {
            success('Authority recorded', 'Actual disposal mode and authority issuance saved.');
            napAuthorityDialogOpen.value = false;
        },
        onFinish: () => {
            isNapAuthoritySubmitting.value = false;
        },
    });
};

const openCoaSubmissionDialog = () => {
    coaSubmissionConfirmed.value = false;
    coaSubmissionDialogOpen.value = true;
};

const submitCoaSubmission = () => {
    if (!coaSubmissionConfirmed.value) {
        error('Confirmation required', 'Confirm that the endorsement package was sent to COA.');
        return;
    }

    isCoaSubmissionSubmitting.value = true;

    router.post(confirmCoaSubmission.url(props.batch.id), {
        report_submitted: true,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors).flat().find(Boolean);
            error('Unable to record submission', typeof firstError === 'string' ? firstError : 'Please review and try again.');
        },
        onSuccess: () => {
            success('Submission recorded', 'COA submission logged and batch marked complete.');
            coaSubmissionDialogOpen.value = false;
        },
        onFinish: () => {
            isCoaSubmissionSubmitting.value = false;
        },
    });
};

const nowInManilaDateTime = computed(() => toManilaDateTimeLocalValue(new Date()));

// Compute minimum allowed scheduled date:
// Schedule edits must not be earlier than current Manila time.
const minScheduledDate = computed(() => {
    return nowInManilaDateTime.value;
});

const isScheduleBeforeNow = computed(() => {
    if (!editForm.scheduled_at) {
        return false;
    }

    return editForm.scheduled_at < nowInManilaDateTime.value;
});

const canSaveScheduleChanges = computed(() => {
    return !isSubmitting.value && !isScheduleBeforeNow.value;
});

const isOverdue = computed(() => {
    if (props.batch.status !== 'scheduled' || !props.batch.scheduled_at) return false;
    return new Date(props.batch.scheduled_at) < new Date();
});

const scheduledSetAt = computed(() => props.batch.created_at || props.batch.scheduled_at);

const napForm1Stage = computed(() => props.batch.nap_form_1_stage || null);
const napStage = computed(() => props.batch.nap_report_stage || null);

const hasNapForm1Progress = computed(() => {
    if (props.batch.status === 'scheduled') {
        return false;
    }

    if (props.batch.nap_form_1_downloaded_at) {
        return true;
    }

    return ['completed', 'generated'].includes(napForm1Stage.value || '');
});

const napForm1Badge = computed(() => {
    if (props.batch.nap_form_1_downloaded_at) {
        return {
            label: 'Downloaded',
            classes:
                'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300 text-xs',
        };
    }

    switch (napForm1Stage.value) {
        case 'generated':
            return {
                label: 'Generated',
                classes:
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs',
            };
        case 'completed':
            return {
                label: 'Completed',
                classes:
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs',
            };
        default:
            return {
                label: 'Pending',
                classes: 'text-xs',
            };
    }
});

const napForm1Timestamp = computed(() => {
    if (props.batch.status === 'scheduled') {
        return null;
    }

    if (props.batch.nap_form_1_downloaded_at) {
        return props.batch.nap_form_1_downloaded_at;
    }

    if (['completed', 'generated'].includes(napForm1Stage.value || '')) {
        return props.batch.nap_form_1_generated_at;
    }

    return null;
});

const showNapForm1Details = computed(() => {
    if (!napForm1Stage.value) {
        return false;
    }

    return napForm1Stage.value !== 'scheduled';
});

const hasNapForm3Progress = computed(() => {
    if (props.batch.status === 'scheduled') {
        return false;
    }

    if (props.batch.nap_report_downloaded_at) {
        return true;
    }

    return ['completed', 'generated'].includes(napStage.value || '');
});

const napForm3Badge = computed(() => {
    if (props.batch.nap_report_downloaded_at) {
        return {
            label: 'Downloaded',
            classes:
                'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300 text-xs',
        };
    }

    switch (napStage.value) {
        case 'generated':
            return {
                label: 'Generated',
                classes:
                    'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs',
            };
        case 'completed':
            return {
                label: 'Completed',
                classes:
                    'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs',
            };
        default:
            return {
                label: 'Pending',
                classes: 'text-xs',
            };
    }
});

const napForm3Timestamp = computed(() => {
    if (props.batch.status === 'scheduled') {
        return null;
    }

    if (props.batch.nap_report_downloaded_at) {
        return props.batch.nap_report_downloaded_at;
    }

    if (['completed', 'generated'].includes(napStage.value || '')) {
        return props.batch.nap_report_generated_at;
    }

    return null;
});

const showNapStageDetails = computed(() => {
    if (!napStage.value) {
        return false;
    }

    return napStage.value !== 'scheduled';
});

// Maximum allowed completed date (current time in user's timezone)
const maxCompletedDate = computed(() => {
    return nowInManilaDateTime.value;
});

const openEditDialog = () => {
    editForm.scheduled_at = toManilaDateTimeLocalValue(props.batch.scheduled_at);
    editForm.completed_at = toManilaDateTimeLocalValue(props.batch.completed_at);
    editForm.scheduled_note = props.batch.scheduled_note || '';
    editDialogOpen.value = true;
};

const saveBatchDates = () => {
    if (isScheduleBeforeNow.value) {
        error('Invalid schedule time', 'Schedule must be current time or later.');
        return;
    }

    isSubmitting.value = true;

    const payload: any = {};

    if (editForm.scheduled_at) {
        payload.scheduled_at = editForm.scheduled_at;
    }
    if (editForm.completed_at) {
        payload.completed_at = editForm.completed_at;
    }
    if (editForm.scheduled_note !== props.batch.scheduled_note) {
        payload.scheduled_note = editForm.scheduled_note;
    }

    router.patch(updateBatch.url(props.batch.id), payload, {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors).flat().find(Boolean);
            error('Unable to save schedule', typeof firstError === 'string' ? firstError : 'Please review the form and try again.');
        },
        onSuccess: () => {
            success('Dates updated', 'Batch dates have been updated successfully.');
            editDialogOpen.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Disposal Batches',href: '/disposal-batches' },
    { title: props.batch.reference, href: showBatch.url(props.batch.id) },
];

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
        case 'rejected':
            return 'Rejected';
        case 'complete':
            return 'Complete';
        default:
            return status.charAt(0).toUpperCase() + status.slice(1);
    }
};

const statusBadgeClass = (status: string) => {
    switch (status) {
        case 'scheduled':
            return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
        case 'completed':
            return 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/40 dark:text-green-300';
        case 'request_letter_ready':
            return 'border-cyan-200 bg-cyan-50 text-cyan-700 dark:border-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300';
        case 'waiting_for_inspection':
            return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
        case 'inspected':
            return 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300';
        case 'approved':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'rejected':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300';
        case 'complete':
            return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300';
        case 'active':
            return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'archived':
            return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300';
        case 'ready_for_disposal':
            return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-900/40 dark:text-orange-300';
        case 'disposed':
            return 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300';
        default:
            return 'border-muted-foreground/40 text-muted-foreground';
    }
};

const getUrgencyBadgeClass = (urgency: string) => {
    const configs: Record<string, string> = {
        immediate: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/40 dark:text-red-300',
        soon: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        scheduled: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        perpetual: 'border-purple-200 bg-purple-50 text-purple-700 dark:border-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    };
    return configs[urgency] || 'border-muted-foreground/40 text-muted-foreground';
};

const getUrgencyLabel = (urgency: string) => {
    const labels: Record<string, string> = {
        immediate: 'Immediate',
        soon: 'Soon',
        scheduled: 'Scheduled',
        perpetual: 'Perpetual',
    };
    return labels[urgency] || urgency;
};

const getRetentionLabel = (duration: string) => {
    const labels: Record<string, string> = {
        perpetual: 'Perpetual',
        ten_years: '10 Years',
        five_years: '5 Years',
        three_years: '3 Years',
        one_year: '1 Year',
        six_months: '6 Months',
    };
    return labels[duration] || duration;
};

const formatPhysicalLocation = (locationData: any): string => {
    if (!locationData) return 'N/A';

    let parsed = locationData;

    // If it's a string, try to parse it as JSON
    if (typeof locationData === 'string') {
        try {
            parsed = JSON.parse(locationData);
        } catch {
            // If parsing fails, return the string as-is if it's not empty
            return locationData.trim() || 'N/A';
        }
    }

    // Now handle the parsed object
    const location = parsed?.location || '';
    const tags = parsed?.tags || [];

    if (!location && tags.length === 0) {
        return 'N/A';
    }

    if (location && tags.length === 0) {
        return location;
    }

    if (location && tags.length > 0) {
        const tagNames = tags.map((tag: { name: string }) => tag.name).join(', ');
        return `${location} (${tagNames})`;
    }

    return 'N/A';
};

const getPhysicalLocationTags = (locationData: any): { id: number; name: string }[] => {
    if (!locationData) return [];

    let parsed = locationData;

    // If it's a string, try to parse it as JSON
    if (typeof locationData === 'string') {
        try {
            parsed = JSON.parse(locationData);
        } catch {
            return [];
        }
    }

    return parsed?.tags || [];
};

const hasNapAuthorityDetails = computed(() =>
    Boolean(props.batch.actual_mode_of_disposal && props.batch.nap_authority_issued_at)
);

const canRecordNapAuthority = computed(() => props.batch.status === 'inspected');

const napAuthorityIssuedAtDisplay = computed(() =>
    props.batch.nap_authority_issued_at ? formatDateTime(props.batch.nap_authority_issued_at) : null
);

const napAuthorityConfirmedByName = computed(() => props.batch.nap_authority_confirmed_by?.name ?? null);

const actualModeOfDisposalLabel = computed(() => props.batch.actual_mode_of_disposal_label || 'Not recorded');

const hasCoaSubmission = computed(() => Boolean(props.batch.report_submitted_to_coa_at));

const hasEndorsementCompleted = computed(() =>
    Boolean(props.batch.approval_letter_path && props.batch.approval_letter_generated_at)
);

const coaSubmissionTimestamp = computed(() =>
    props.batch.report_submitted_to_coa_at ? formatDateTime(props.batch.report_submitted_to_coa_at) : null
);

const coaSubmissionRecordedByName = computed(() => props.batch.report_submitted_by?.name ?? null);

const canRecordCoaSubmission = computed(() =>
    props.batch.status === 'approved'
    && hasEndorsementCompleted.value
    && !hasCoaSubmission.value
);


// Get physical location: use snapshot for verified batches, live data for scheduled
const getItemPhysicalLocation = (item: DisposalBatchItem) => {
    // For verified batches, always use snapshot (physical location was cleared)
    if (['completed', 'request_letter_ready'].includes(props.batch.status)) {
        return item.physical_snapshot;
    }

    // For scheduled batches, use current file location tags (may have changed since scheduling)
    // Convert tags array to the same format as physical_snapshot
    if (item.file?.physical_location_tags && item.file.physical_location_tags.length > 0) {
        return {
            location: item.file.physical_location || '',
            tags: item.file.physical_location_tags
        };
    }

    // Fallback to snapshot if no tags
    return item.physical_snapshot;
};

const goBack = () => {
    router.visit('/disposal-batches');
};

const isBatchReadOnly = computed(() => props.batch.status === 'complete');

const downloadReport = (format = 'pdf') => {
    if (!props.batch.nap_report_path) {
        error('NAP Form 3 missing', 'Generate the NAP Form 3 before downloading.');
        return;
    }

    if (!props.batch.nap_report_file_exists) {
        error('Generated file missing', 'Generated file missing, please regenerate from Preview page.');
        return;
    }

    const url = napForm3Download.url(props.batch.id, {
        query: {
            format,
            t: Date.now().toString(),
        },
    });
    hasDownloadedNap.value = true;
    triggerFileDownload(url);
    markNapDownloadPending();
    notifyNapDownload();
    scheduleNapRefresh();
};

const generateNapForm3 = () => {
    if (isBatchReadOnly.value) {
        error('Batch is read-only', 'Completed batches cannot regenerate the NAP Form 3 preview. Download the finalized PDF instead.');
        return;
    }

    if (!hasNapForm1Progress.value) {
        error('Finalize NAP Form 1 first', 'Generate and download NAP Form 1 before working on NAP Form 3.');
        return;
    }

    router.visit(napForm3Preview.url(props.batch.id));
};

const generateNapForm1 = () => {
    if (isBatchReadOnly.value) {
        error('Batch is read-only', 'Completed batches cannot regenerate NAP Form 1. Download the finalized PDF instead.');
        return;
    }

    router.visit(napForm1Preview.url(props.batch.id));
};

const downloadNapForm1 = () => {
    if (!props.batch.nap_form_1_path) {
        error('NAP Form 1 missing', 'Generate the NAP Form 1 before downloading.');
        return;
    }

    const url = napForm1Download.url(props.batch.id, {
        query: {
            format: 'pdf',
            t: Date.now().toString(),
        },
    });

    hasDownloadedNapForm1.value = true;
    triggerFileDownload(url);
    markNapForm1DownloadPending();
    notifyNapForm1Download();
    scheduleNapRefresh();
};

const hasApprovalLetter = computed(() => Boolean(props.batch.approval_letter_path));
const hasMissingApprovalLetterFile = computed(() =>
    hasApprovalLetter.value && !props.batch.approval_letter_file_exists
);

const canPreviewNapForm3 = computed(() =>
    ['completed', 'request_letter_ready', 'inspected', 'approved', 'complete', 'rejected'].includes(props.batch.status)
    && props.batch.disposed_files_count > 0
    && hasNapForm1Progress.value
    && !hasDownloadedNap.value
);

const canPreviewNapForm1 = computed(() =>
    ['completed', 'request_letter_ready', 'inspected', 'approved', 'complete', 'rejected'].includes(props.batch.status)
    && props.batch.disposed_files_count > 0
    && !hasDownloadedNapForm1.value
);

const hasMissingNapReportFile = computed(() =>
    Boolean(props.batch.nap_report_path) && !props.batch.nap_report_file_exists
);

// Request Letter computed properties
const hasRequestLetterGenerated = computed(() => Boolean(props.batch.request_letter_generated_at));
const hasRequestLetterDownloaded = computed(() => Boolean(props.batch.request_letter_downloaded_at));
const requestLetterDateLabel = computed(() => props.batch.request_letter_details?.letter_date ?? null);
const requestLetterDateValue = computed<Date | null>(() => {
    if (!requestLetterDateLabel.value) {
        return null;
    }

    const parsed = new Date(requestLetterDateLabel.value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    parsed.setHours(0, 0, 0, 0);
    return parsed;
});
const requestLetterDateIsFuture = computed(() => {
    if (!requestLetterDateValue.value) {
        return false;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return requestLetterDateValue.value.getTime() > today.getTime();
});
const requestLetterConfirmationRequired = computed(() => hasRequestLetterDownloaded.value && requestLetterDateIsFuture.value);
const todayDisplay = computed(() => formatDate(new Date().toISOString()));
const needsRequestLetterGuidance = computed(() =>
    props.batch.status === 'completed'
    && (!hasRequestLetterGenerated.value || !hasRequestLetterDownloaded.value)
);
const requestLetterGuidanceLabel = computed(() => {
    if (!hasRequestLetterGenerated.value) {
        return 'Generate Request Letter';
    }

    if (!hasRequestLetterDownloaded.value) {
        return 'Download Request Letter';
    }

    return 'Request Letter Pending';
});
const requestLetterGuidanceDescription = computed(() => {
    if (!hasRequestLetterGenerated.value) {
        return 'Create the Request Letter for NAP before scheduling an inspection.';
    }

    if (!hasRequestLetterDownloaded.value) {
        return 'Download the generated Request Letter PDF with the attached NAP Form 3 before scheduling an inspection.';
    }

    return '';
});

const hasRequestLetterProgress = computed(() => {
    if (props.batch.status === 'scheduled') {
        return false;
    }

    if (props.batch.request_letter_downloaded_at) {
        return true;
    }
    return Boolean(props.batch.request_letter_generated_at);
});

const requestLetterBadge = computed(() => {
    if (props.batch.request_letter_downloaded_at) {
        return {
            label: 'Downloaded',
            classes:
                'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-800 dark:bg-teal-900/40 dark:text-teal-300 text-xs',
        };
    }

    if (props.batch.request_letter_generated_at) {
        return {
            label: 'Generated',
            classes:
                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/40 dark:text-amber-300 text-xs',
        };
    }

    return {
        label: 'Pending',
        classes: 'text-xs',
    };
});

const requestLetterTimestamp = computed(() => {
    if (props.batch.status === 'scheduled') {
        return null;
    }

    if (props.batch.request_letter_downloaded_at) {
        return props.batch.request_letter_downloaded_at;
    }
    return props.batch.request_letter_generated_at;
});

const showRequestLetterDetails = computed(() =>
    Boolean(props.batch.request_letter_generated_at)
);

const hasRequestLetter = computed(() => Boolean(props.batch.request_letter_path));

const hasMissingRequestLetterFile = computed(() =>
    hasRequestLetter.value && !props.batch.request_letter_file_exists
);

const canPreviewRequestLetter = computed(() => {
    // Request Letter can only be previewed if NAP Form 3 has real progress
    if (props.batch.nap_report_downloaded_at) {
        return true;
    }

    return ['completed', 'generated'].includes(napStage.value || '');
});

const openRequestLetterPreview = () => {
    if (isBatchReadOnly.value) {
        error('Batch is read-only', 'Completed batches cannot regenerate the request letter. Download the signed copy instead.');
        return;
    }

    router.visit(requestLetterPreview.url(props.batch.id));
};

const downloadRequestLetter = () => {
    if (!props.batch.request_letter_path) {
        error('Request Letter missing', 'Generate the Request Letter before downloading.');
        return;
    }

    if (!props.batch.request_letter_file_exists) {
        error('Generated file missing', 'Generated file missing, please regenerate from Preview page.');
        return;
    }

    const url = requestLetterDownload.url(props.batch.id, {
        query: {
            t: Date.now().toString(),
        },
    });
    triggerFileDownload(url);
    scheduleNapRefresh();
};

const openEndorsementFinalize = () => {
    if (!hasNapAuthorityDetails.value) {
        error('Record authority to dispose', 'Confirm the NAP-issued authority and actual disposal mode before finalizing.');
        return;
    }

    router.visit(endorsementFinalize.url(props.batch.id));
};

const downloadApprovalLetter = () => {
    if (!props.batch.approval_letter_path) {
        error('Endorsement Letter to COA missing', 'Generate the Endorsement Letter to COA before downloading.');
        return;
    }

    if (!props.batch.approval_letter_file_exists) {
        error('Generated file missing', 'Generated file missing, please regenerate from the Endorsement Letter to COA page.');
        return;
    }

    window.location.href = approvalLetterDownload.url(props.batch.id, { query: { format: 'pdf' } });
};

const previewApprovalLetter = () => {
    router.visit(endorsementFinalize.url(props.batch.id));
};

const openDocumentPreview = (url: string) => {
    const openedWindow = window.open(url, '_blank', 'noopener,noreferrer');

    // Fallback for browsers/devices that block popups from SPA handlers.
    if (!openedWindow) {
        window.location.href = url;
    }
};

const inspectionEligibleStatuses = ['completed', 'request_letter_ready', 'waiting_for_inspection'];
const isInspectionEligibleStatus = computed(() =>
    inspectionEligibleStatuses.includes(props.batch.status)
);

const canShowInspectButton = computed(() =>
    isInspectionEligibleStatus.value && hasDownloadedNap.value && hasDownloadedNapForm1.value
);

const canScheduleInspection = computed(() =>
    isInspectionEligibleStatus.value
    && hasDownloadedNap.value
    && hasDownloadedNapForm1.value
    && hasRequestLetterGenerated.value
    && hasRequestLetterDownloaded.value
    && !props.batch.inspected_at
);

const scheduleInspectionHelpText = computed(() => {
    if (!isInspectionEligibleStatus.value) {
        return 'Verify the batch before scheduling an inspection.';
    }

    if (!hasDownloadedNapForm1.value || !hasDownloadedNap.value) {
        return 'Download NAP Form 1 and NAP Form 3 before scheduling an inspection.';
    }

    if (!hasRequestLetterGenerated.value) {
        return 'Generate the Request Letter before scheduling an inspection.';
    }

    if (!hasRequestLetterDownloaded.value) {
        return 'Download the Request Letter before scheduling an inspection.';
    }

    return 'Schedule an inspection to continue';
});

const isInspectionScheduled = computed(() =>
    props.batch.status !== 'scheduled'
    && Boolean(props.batch.inspection_scheduled_at)
    && !props.batch.inspected_at
);

const isInspectionScheduleBeforeNow = computed(() => {
    if (!inspectionScheduledAt.value) {
        return false;
    }

    if (!minScheduledDate.value) {
        return false;
    }

    return inspectionScheduledAt.value < minScheduledDate.value;
});

const isInspectionDue = computed(() => {
    if (!props.batch.inspection_scheduled_at) {
        return false;
    }
    return new Date(props.batch.inspection_scheduled_at).getTime() <= Date.now();
});

const latestReturnToDraftEvent = computed(() => {
    return props.batch.audits
        .filter((audit) => audit.action === 'batch_returned_to_draft')
        .sort((a, b) => {
            return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
        })[0] ?? null;
});

const latestReturnToDraftReason = computed(() => {
    const reason = latestReturnToDraftEvent.value?.meta?.reopen_reason;

    return typeof reason === 'string' && reason.trim() !== '' ? reason : null;
});

const needsNapDownload = computed(() =>
    props.batch.status === 'completed'
    && (
        (Boolean(props.batch.nap_form_1_generated_at) && !hasDownloadedNapForm1.value)
        || (Boolean(props.batch.nap_report_generated_at) && !hasDownloadedNap.value)
    )
);

const openCompleteDialog = () => {
    completeDialogOpen.value = true;
};

const confirmComplete = () => {
    isSubmitting.value = true;

    router.post(`/disposal-batches/${props.batch.id}/complete`, {}, {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to verify batch', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Batch Verified', 'Batch has been verified successfully and is ready for disposal processing.');
            completeDialogOpen.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const selectedFileMetadata = computed(() => {
    if (!selectedFileId.value) return [];

    const item = props.batch.items.find(i => i.file?.id === selectedFileId.value);
    if (!item?.file) return [];

    const file = item.file;
    const physicalLocation = formatPhysicalLocation(
        getItemPhysicalLocation(item)
    );

    return [
        { label: 'Record ID', value: file.record_id },
        { label: 'File Name', value: file.file_name },
        { label: 'Author', value: file.author },
        { label: 'Physical Location', value: physicalLocation },
        { label: 'Retention Status', value: file.retention_status },
    ].map(({ label, value }) => ({
        label,
        value: value || 'N/A',
    }));
});

const selectedBatchFile = computed(() => props.batch.items.find(i => i.file?.id === selectedFileId.value)?.file ?? null);

const openInfoSheet = (fileId: string, fileName: string) => {
    selectedFileId.value = fileId;
    selectedFileName.value = fileName;
    infoSheetOpen.value = true;
};

const openFileViewer = (file: { id: string; file_path: string | null }) => {
    const hasFile = Boolean(file.file_path);

    if (!hasFile) {
        error('No file attached', 'This record does not have an electronic copy.');
        return;
    }

    router.visit(`/file/${file.id}/view`);
};

const openImagePreview = (document: ApprovalDocument) => {
    if (document.path) {
        previewImageUrl.value = document.path;
        previewImageName.value = document.name;
        imagePreviewOpen.value = true;
    }
};

const closeImagePreview = (value: boolean) => {
    imagePreviewOpen.value = value;
    if (!value) {
        previewImageUrl.value = '';
        previewImageName.value = '';
    }
};

const openAddFilesDialog = async () => {
    addFilesDialogOpen.value = true;
    selectedFilesToAdd.value = new Set();

    // Reset filters to default
    urgencyFilter.value = 'all';
    retentionDurationFilter.value = '';
    rootFolderFilter.value = '';
    departmentFilter.value = '';
    expiresFromFilter.value = '';
    expiresToFilter.value = '';
    releasedFromFilter.value = '';
    releasedToFilter.value = '';
    showFilters.value = false;
    searchQuery.value = '';
    currentPage.value = 1;
    filesPerPage.value = 20;

    await fetchAvailableFiles();
    await fetchFilterOptions();
};

const fetchAvailableFiles = async (resetPage = false) => {
    if (resetPage) {
        currentPage.value = 1;
    }

    isLoadingFiles.value = true;
    try {
        const params: Record<string, any> = {
            page: currentPage.value,
            per_page: filesPerPage.value,
        };

        if (searchQuery.value) params.search = searchQuery.value;
        if (urgencyFilter.value && urgencyFilter.value !== 'all') params.urgency = urgencyFilter.value;
        if (retentionDurationFilter.value) params.retention_duration = retentionDurationFilter.value;
        if (rootFolderFilter.value) params.root_folder_id = rootFolderFilter.value;
        if (departmentFilter.value) params.department_id = departmentFilter.value;
        if (expiresFromFilter.value) params.expires_from = expiresFromFilter.value;
        if (expiresToFilter.value) params.expires_to = expiresToFilter.value;
        if (releasedFromFilter.value) params.released_from = releasedFromFilter.value;
        if (releasedToFilter.value) params.released_to = releasedToFilter.value;

        const response = await axios.get(availableFilesRoute.url(props.batch.id), { params });
        availableFiles.value = response.data.files;
        pagination.value = response.data.pagination;
    } catch (err) {
        error('Failed to load files', 'Could not fetch available files.');
        availableFiles.value = [];
        pagination.value = {
            current_page: 1,
            per_page: 20,
            total: 0,
            last_page: 1,
            from: null,
            to: null,
        };
    } finally {
        isLoadingFiles.value = false;
    }
};

const goToPage = (page: number) => {
    currentPage.value = page;
    fetchAvailableFiles();
};

const changePerPage = (newPerPage: number) => {
    filesPerPage.value = newPerPage;
    currentPage.value = 1;
    fetchAvailableFiles();
};

const fetchFilterOptions = async () => {
    try {
        const response = await axios.get('/disposal-management/filter-options');
        filterOptions.value = response.data;
    } catch (err) {
        // Use default/empty options if fetch fails
        console.error('Failed to load filter options:', err);
    }
};

const clearFilters = () => {
    urgencyFilter.value = 'all';
    retentionDurationFilter.value = '';
    rootFolderFilter.value = '';
    departmentFilter.value = '';
    expiresFromFilter.value = '';
    expiresToFilter.value = '';
    releasedFromFilter.value = '';
    releasedToFilter.value = '';
    fetchAvailableFiles(true);
};

const toggleFileSelection = (fileId: string) => {
    if (selectedFilesToAdd.value.has(fileId)) {
        selectedFilesToAdd.value.delete(fileId);
    } else {
        selectedFilesToAdd.value.add(fileId);
    }
};

const selectAllFiles = () => {
    availableFiles.value.forEach(file => selectedFilesToAdd.value.add(file.id));
};

const deselectAllFiles = () => {
    selectedFilesToAdd.value.clear();
};

const addFilesToBatch = () => {
    if (selectedFilesToAdd.value.size === 0) {
        error('No files selected', 'Please select at least one file to add.');
        return;
    }

    isSubmitting.value = true;

    router.post(addFiles.url(props.batch.id), {
        file_ids: Array.from(selectedFilesToAdd.value),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to add files', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Files added', `${selectedFilesToAdd.value.size} file(s) added to batch successfully.`);
            addFilesDialogOpen.value = false;
            selectedFilesToAdd.value.clear();
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const openRemoveFileDialog = (fileId: string, fileName: string) => {
    fileToRemove.value = { id: fileId, name: fileName };
    removeFileDialogOpen.value = true;
};

const confirmRemoveFile = () => {
    if (!fileToRemove.value) return;

    isSubmitting.value = true;

    router.delete(removeFile.url({ batch: props.batch.id, fileId: fileToRemove.value.id }), {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to remove file', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('File removed', 'File has been removed from the batch successfully.');
            removeFileDialogOpen.value = false;
            fileToRemove.value = null;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const openDeleteBatchDialog = () => {
    deleteBatchDialogOpen.value = true;
};

const confirmDeleteBatch = () => {
    isSubmitting.value = true;

    router.delete(destroyBatch.url(props.batch.id), {
        onError: (errors) => {
            error('Failed to delete batch', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Batch deleted', 'The disposal batch has been deleted successfully.');
            router.visit('/disposal-batches');
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const showScheduleInspectionDialog = () => {
    inspectionScheduledAt.value = props.batch.inspection_scheduled_at
        ? toManilaDateTimeLocalValue(props.batch.inspection_scheduled_at)
        : '';

    if (!requestLetterConfirmationRequired.value) {
        requestLetterSentConfirmed.value = true;
    }

    scheduleInspectionDialogOpen.value = true;
};

const openScheduleInspectionDialog = () => {
    if (props.batch.disposed_files_count === 0) {
        return;
    }

    if (requestLetterConfirmationRequired.value) {
        requestLetterSentConfirmed.value = false;
        requestLetterWarningDialogOpen.value = true;
        return;
    }

    showScheduleInspectionDialog();
};

const confirmRequestLetterSent = () => {
    requestLetterSentConfirmed.value = true;
    requestLetterWarningDialogOpen.value = false;
    showScheduleInspectionDialog();
};

const cancelRequestLetterWarning = () => {
    requestLetterSentConfirmed.value = false;
    requestLetterWarningDialogOpen.value = false;
    error('Request Letter Pending', 'Send the Request Letter to NAP before scheduling an inspection.');
};

const confirmScheduleInspection = () => {
    if (!inspectionScheduledAt.value) {
        error('Date required', 'Please select a date and time for the inspection.');
        return;
    }

    if (requestLetterConfirmationRequired.value && !requestLetterSentConfirmed.value) {
        error('Confirmation required', 'Confirm that the Request Letter has already been sent to NAP before scheduling.');
        return;
    }

    if (isInspectionScheduleBeforeNow.value) {
        error('Invalid date', 'Inspection must be scheduled using the current Manila time or later.');
        return;
    }

    isSubmitting.value = true;

    router.post(scheduleInspection.url(props.batch.id), {
        inspection_scheduled_at: inspectionScheduledAt.value,
        request_letter_sent_to_nap: requestLetterSentConfirmed.value,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to schedule inspection', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Inspection scheduled', 'Inspection has been scheduled successfully.');
            scheduleInspectionDialogOpen.value = false;
            inspectionScheduledAt.value = '';
            requestLetterSentConfirmed.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const openInspectDialog = () => {
    inspectionNotes.value = '';
    inspectorName.value = '';
    inspectorNameError.value = '';
    inspectionResult.value = 'passed';
    inspectionRejectionReason.value = '';
    inspectDialogOpen.value = true;
};

const updateInspectionResult = (value: unknown) => {
    inspectionResult.value = value === 'rejected' ? 'rejected' : 'passed';

    if (inspectionResult.value === 'passed') {
        inspectionRejectionReason.value = '';
    }
};

const confirmMarkAsInspected = () => {
    applyInspectorTitleCase();
    applyInspectionNotesSentenceCase();

    if (!inspectorName.value.trim()) {
        inspectorNameError.value = 'Please enter who completed the inspection.';
        return;
    }

    inspectorNameError.value = '';

    if (inspectionResult.value === 'rejected') {
        applyInspectionRejectionReasonSentenceCase();

        if (!inspectionRejectionReason.value.trim()) {
            error('Rejection reason required', 'Please provide a rejection reason for a failed inspection.');
            return;
        }
    }

    isSubmitting.value = true;

    router.post(markAsInspected.url(props.batch.id), {
        inspection_notes: inspectionNotes.value || null,
        inspector_name: inspectorName.value.trim(),
        inspection_result: inspectionResult.value,
        rejection_reason: inspectionResult.value === 'rejected' ? inspectionRejectionReason.value.trim() : null,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to mark as inspected', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            if (inspectionResult.value === 'rejected') {
                success('Batch rejected', 'Batch has been rejected based on inspection result.');
            } else {
                success('Batch inspected', 'Batch has been marked as inspected successfully.');
            }

            inspectDialogOpen.value = false;
            inspectionNotes.value = '';
            inspectorName.value = '';
            inspectionResult.value = 'passed';
            inspectionRejectionReason.value = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const openReturnToDraftDialog = () => {
    returnToDraftReason.value = '';
    returnToDraftDialogOpen.value = true;
};

const confirmReturnToDraft = () => {
    applyReturnToDraftReasonSentenceCase();

    if (!returnToDraftReason.value.trim()) {
        error('Reason required', 'Please provide a reason before returning this batch to draft.');
        return;
    }

    isSubmitting.value = true;

    router.post(returnToDraft.url(props.batch.id), {
        reopen_reason: returnToDraftReason.value.trim(),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            error('Failed to return batch to draft', Object.values(errors).join(' '));
        },
        onSuccess: () => {
            success('Batch returned to draft', 'Batch is now editable again for file and schedule updates.');
            returnToDraftDialogOpen.value = false;
            returnToDraftReason.value = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Disposal Batch - ${batch.reference}`" />

        <div class="space-y-4 p-4 sm:space-y-6 sm:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex items-center gap-3">
                        <Button variant="ghost" size="icon" @click="goBack">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h1 class="text-xl md:text-2xl font-semibold text-foreground truncate">
                                    {{ batch.reference }}
                                </h1>
                                <Badge variant="outline" :class="statusBadgeClass(batch.status)" class="hidden sm:inline-block">
                                    {{ statusDisplayLabel(batch.status) }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground truncate">Disposal batch details and timeline</p>
                        </div>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center md:w-auto md:justify-end">
                    <Badge variant="outline" :class="statusBadgeClass(batch.status)" class="sm:hidden">
                        {{ statusDisplayLabel(batch.status) }}
                    </Badge>
                </div>


            </div>

            <Alert
                v-if="warningMessage"
                variant="default"
                class="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100"
            >
                <div class="flex gap-3">
                    <AlertCircle class="h-5 w-5 mt-0.5" />
                    <div class="space-y-1">
                        <AlertTitle>Submission requires confirmation</AlertTitle>
                        <AlertDescription>
                            {{ warningMessage }}
                        </AlertDescription>
                    </div>
                </div>
            </Alert>

            <!-- Batch Summary -->
            <Card>
                <CardContent class="py-4">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Reference:</span>
                            <span class="font-semibold">{{ batch.reference }}</span>
                        </div>
                        <div class="hidden sm:block h-4 w-px bg-border" />
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Sequence:</span>
                            <span class="font-medium">#{{ batch.sequence }}</span>
                        </div>
                        <div class="hidden sm:block h-4 w-px bg-border" />
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Files:</span>
                            <Badge variant="secondary">{{ batch.file_count }}</Badge>
                        </div>
                        <div class="hidden sm:block h-4 w-px bg-border" />
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Proposed Mode:</span>
                            <span class="font-medium">{{ batch.mode_of_disposal_label || 'Not set' }}</span>
                        </div>
                        <div class="hidden sm:block h-4 w-px bg-border" />
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Actual Mode:</span>
                            <span class="font-medium">{{ actualModeOfDisposalLabel }}</span>
                        </div>
                        <div class="hidden sm:block h-4 w-px bg-border" />
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Created:</span>
                            <span class="font-medium">{{ formatDate(batch.created_at) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Workflow Timeline -->
            <Card>
                <CardHeader class="pb-4">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ClipboardList class="h-4 w-4 text-muted-foreground" />
                        Disposal Workflow
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="relative">
                        <!-- Timeline connector line -->
                        <div class="absolute left-4 top-6 bottom-6 w-0.5 bg-border hidden md:block" />

                        <div class="space-y-6">
                            <!-- Step 1: Scheduled -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="batch.scheduled_at
                                        ? 'border-blue-500 bg-blue-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <Clock class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Scheduled
                                            <CheckCircle2 v-if="batch.scheduled_at" class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        </h4>
                                        <span v-if="scheduledSetAt" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(scheduledSetAt) }}
                                        </span>
                                    </div>
                                    <div v-if="batch.scheduled_at" class="flex flex-col gap-2 text-sm">
                                        <div v-if="batch.scheduled_by" class="flex gap-2">
                                            <span class="text-muted-foreground">Scheduled by:</span>
                                            <span class="font-medium">{{ batch.scheduled_by.name }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Scheduled for:</span>
                                            <span class="font-medium">{{ formatDateTime(batch.scheduled_at) }}</span>
                                        </div>
                                        <div v-if="batch.scheduled_note" class="break-words md:col-span-2 lg:col-span-3">
                                            <span class="text-muted-foreground">Note:</span>
                                            <span class="ml-2 font-medium">{{ batch.scheduled_note }}</span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="isOverdue"
                                        class="mt-2 flex items-center gap-2 rounded-md border border-orange-200 bg-orange-50 p-2 dark:border-orange-800 dark:bg-orange-900/30"
                                    >
                                        <AlertCircle class="h-4 w-4 text-orange-600 dark:text-orange-400 flex-shrink-0" />
                                        <span class="text-xs text-orange-700 dark:text-orange-300">
                                            This disposal is overdue and should be completed.
                                        </span>
                                    </div>
                                    <div
                                        v-if="latestReturnToDraftEvent"
                                        class="mt-2 rounded-md border border-sky-200 bg-sky-50 p-3 dark:border-sky-800 dark:bg-sky-900/30"
                                    >
                                        <p class="text-xs font-semibold text-sky-800 dark:text-sky-200">
                                            Reopened From Rejection
                                        </p>
                                        <p class="mt-1 text-xs text-sky-700 dark:text-sky-300">
                                            Returned to draft on {{ formatDateTime(latestReturnToDraftEvent.created_at) }}.
                                        </p>
                                        <p v-if="latestReturnToDraftReason" class="mt-1 text-xs text-sky-700 dark:text-sky-300">
                                            Reason: {{ latestReturnToDraftReason }}
                                        </p>
                                    </div>

                                    <!-- Actions for Scheduled status -->
                                    <div v-if="batch.status === 'scheduled'" class="pt-3 flex flex-wrap gap-2">
                                        <Button
                                            variant="default"
                                            size="sm"
                                            @click="openCompleteDialog"
                                            class="cursor-pointer h-7 text-xs"
                                        >
                                            <CheckCircle2 class="mr-1.5 h-3 w-3" />
                                            Verify Batch
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            @click="openEditDialog"
                                            class="cursor-pointer h-7 text-xs"
                                        >
                                            <Edit class="mr-1.5 h-3 w-3" />
                                            Edit Schedule
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="cursor-pointer h-7 text-xs text-destructive hover:text-destructive"
                                            @click="openDeleteBatchDialog"
                                        >
                                            <Trash2 class="mr-1.5 h-3 w-3" />
                                            Delete Batch
                                        </Button>
                                    </div>

                                    <p v-if="!batch.scheduled_at" class="text-sm text-muted-foreground">Not scheduled yet</p>
                                </div>
                            </div>

                            <!-- Step 2: Verified -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="batch.completed_at
                                        ? 'border-green-500 bg-green-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <CheckCircle2 class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Verified
                                            <CheckCircle2 v-if="batch.completed_at" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        </h4>
                                        <span v-if="batch.completed_at" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(batch.completed_at) }}
                                        </span>
                                    </div>
                                    <div v-if="batch.completed_at" class="grid gap-2 text-sm">
                                        <div v-if="batch.completed_by" class="flex gap-2">
                                            <span class="text-muted-foreground">Verified by:</span>
                                            <span class="font-medium">{{ batch.completed_by.name }}</span>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-muted-foreground">
                                        {{ batch.status === 'scheduled' ? 'Review and verify the batch files are ready for disposal' : 'Not verified yet' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Step 3: NAP Form 1 -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasNapForm1Progress
                                        ? 'border-purple-500 bg-purple-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <FileText class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            NAP Form 1 - Records Inventory and Appraisal
                                            <CheckCircle2 v-if="hasNapForm1Progress" class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        </h4>
                                        <span v-if="napForm1Timestamp" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(napForm1Timestamp) }}
                                        </span>
                                    </div>

                                    <div v-if="showNapForm1Details" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-col gap-1 text-sm">
                                                <div v-if="batch.nap_form_1_details?.date_prepared" class="flex gap-2">
                                                    <span class="text-muted-foreground">Date Prepared:</span>
                                                    <span class="font-medium">{{ batch.nap_form_1_details.date_prepared }}</span>
                                                </div>
                                                <div v-if="batch.nap_form_1_downloaded_at" class="flex gap-2">
                                                    <span class="text-muted-foreground">Last Download:</span>
                                                    <span class="font-medium">{{ formatDateTime(batch.nap_form_1_downloaded_at) }}</span>
                                                </div>
                                                <div v-if="batch.nap_form_1_generated_by" class="flex gap-2">
                                                    <span class="text-muted-foreground">Generated by:</span>
                                                    <span class="font-medium">{{ batch.nap_form_1_generated_by.name }}</span>
                                                </div>
                                            </div>
                                            <div class="pt-1 flex flex-wrap gap-2">
                                                <Button
                                                    v-if="batch.inspected_at"
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.nap_form_1_path"
                                                    @click="openPDFViewer('NAP Form 1', napForm1Download, Boolean(batch.nap_form_1_path))"
                                                >
                                                    <Eye class="mr-1.5 h-3 w-3" />
                                                    View Document
                                                </Button>
                                                <Button
                                                    v-else
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="isBatchReadOnly"
                                                    @click="generateNapForm1"
                                                >
                                                    <FileText class="mr-1.5 h-3 w-3" />
                                                    Preview
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.nap_form_1_path"
                                                    @click="downloadNapForm1"
                                                >
                                                    <Download class="mr-1.5 h-3 w-3" />
                                                    Download
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                    <template v-if="!hasNapForm1Progress">
                                        <p class="mt-2 text-sm text-muted-foreground">
                                            {{ batch.completed_at ? 'Generate NAP Form 1 to continue' : 'Verify batch first' }}
                                        </p>
                                        <div v-if="canPreviewNapForm1" class="pt-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                class="cursor-pointer h-7 text-xs"
                                                :disabled="isBatchReadOnly"
                                                @click="generateNapForm1"
                                            >
                                                <FileText class="mr-1.5 h-3 w-3" />
                                                Preview NAP Form 1
                                            </Button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Step 4: NAP Form 3 -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasNapForm3Progress
                                        ? 'border-purple-500 bg-purple-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <FileText class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            NAP Form 3 - Request for Authority to Dispose of Records
                                            <CheckCircle2 v-if="hasNapForm3Progress" class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        </h4>
                                        <span v-if="napForm3Timestamp" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(napForm3Timestamp) }}
                                        </span>
                                    </div>

                                    <div v-if="showNapStageDetails" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-col gap-1 text-sm">
                                                <div v-if="batch.nap_certificate_details?.certificate_date" class="flex gap-2">
                                                    <span class="text-muted-foreground">Form Date:</span>
                                                    <span class="font-medium">{{ batch.nap_certificate_details.certificate_date }}</span>
                                                </div>
                                                <div v-if="batch.nap_report_downloaded_at" class="flex gap-2">
                                                    <span class="text-muted-foreground">Last Download:</span>
                                                    <span class="font-medium">{{ formatDateTime(batch.nap_report_downloaded_at) }}</span>
                                                </div>
                                                <div v-if="batch.nap_report_generated_by" class="flex gap-2">
                                                    <span class="text-muted-foreground">Generated by:</span>
                                                    <span class="font-medium">{{ batch.nap_report_generated_by.name }}</span>
                                                </div>
                                            </div>
                                            <div class="pt-1 flex flex-wrap gap-2">
                                                <Button
                                                    v-if="batch.inspected_at"
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.nap_report_path || !batch.nap_report_file_exists"
                                                    @click="openPDFViewer('NAP Form 3', napForm3Download, Boolean(batch.nap_report_path && batch.nap_report_file_exists))"
                                                >
                                                    <Eye class="mr-1.5 h-3 w-3" />
                                                    View Document
                                                </Button>
                                                <Button
                                                    v-else
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="isBatchReadOnly"
                                                    @click="generateNapForm3"
                                                >
                                                    <FileText class="mr-1.5 h-3 w-3" />
                                                    Preview
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.nap_report_path || !batch.nap_report_file_exists"
                                                    @click="downloadReport('pdf')"
                                                >
                                                    <Download class="mr-1.5 h-3 w-3" />
                                                    Download
                                                </Button>
                                            </div>
                                            <p v-if="hasMissingNapReportFile" class="pt-1 text-xs text-amber-700 dark:text-amber-300">
                                                Generated file missing, please regenerate from Preview page.
                                            </p>
                                        </div>
                                    </div>
                                    <template v-if="!hasNapForm3Progress">
                                        <p class="mt-2 text-sm text-muted-foreground">
                                            <span v-if="!hasNapForm1Progress">
                                                Finalize NAP Form 1 before generating this form.
                                            </span>
                                            <span v-else>
                                                {{ batch.completed_at ? 'Generate NAP Form 3 to continue' : 'Verify batch first' }}
                                            </span>
                                        </p>
                                        <div v-if="canPreviewNapForm3" class="pt-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                class="cursor-pointer h-7 text-xs"
                                                :disabled="isBatchReadOnly"
                                                @click="generateNapForm3"
                                            >
                                                <FileText class="mr-1.5 h-3 w-3" />
                                                Preview NAP Form 3
                                            </Button>
                                        </div>

                                    </template>
                                </div>
                            </div>

                            <!-- Step 5: Request Letter -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasRequestLetterProgress
                                        ? 'border-teal-500 bg-teal-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <Mail class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Request Letter for NAP
                                            <CheckCircle2 v-if="hasRequestLetterProgress" class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                        </h4>
                                        <span v-if="requestLetterTimestamp" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(requestLetterTimestamp) }}
                                        </span>
                                    </div>

                                    <div v-if="showRequestLetterDetails" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-col gap-1 text-sm">
                                                <div v-if="batch.request_letter_details?.letter_date" class="flex gap-2">
                                                    <span class="text-muted-foreground">Letter Date:</span>
                                                    <span class="font-medium">{{ batch.request_letter_details.letter_date }}</span>
                                                </div>
                                                <div v-if="batch.request_letter_downloaded_at" class="flex gap-2">
                                                    <span class="text-muted-foreground">Last Download:</span>
                                                    <span class="font-medium">{{ formatDateTime(batch.request_letter_downloaded_at) }}</span>
                                                </div>
                                                <div v-if="batch.request_letter_generated_by" class="flex gap-2">
                                                    <span class="text-muted-foreground">Generated by:</span>
                                                    <span class="font-medium">{{ batch.request_letter_generated_by.name }}</span>
                                                </div>
                                            </div>
                                            <div class="pt-1 flex flex-wrap gap-2">
                                                <Button
                                                    v-if="batch.inspected_at"
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.request_letter_path || !batch.request_letter_file_exists"
                                                    @click="openPDFViewer('Request Letter for NAP', requestLetterDownload, Boolean(batch.request_letter_path && batch.request_letter_file_exists))"
                                                >
                                                    <Eye class="mr-1.5 h-3 w-3" />
                                                    View Document
                                                </Button>
                                                <Button
                                                    v-else-if="canPreviewRequestLetter"
                                                    size="sm"
                                                    variant="secondary"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="isBatchReadOnly"
                                                    @click="openRequestLetterPreview"
                                                >
                                                    <FileText class="mr-1.5 h-3 w-3" />
                                                    Preview
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    class="cursor-pointer h-7 text-xs"
                                                    :disabled="!batch.request_letter_path || !batch.request_letter_file_exists"
                                                    @click="downloadRequestLetter"
                                                >
                                                    <Download class="mr-1.5 h-3 w-3" />
                                                    Download
                                                </Button>
                                            </div>
                                            <p v-if="hasMissingRequestLetterFile" class="pt-1 text-xs text-amber-700 dark:text-amber-300">
                                                Generated file missing, please regenerate from Preview page.
                                            </p>
                                        </div>
                                    </div>
                                    <template v-else>
                                        <p v-if="canPreviewRequestLetter" class="mt-2 text-sm text-muted-foreground">
                                            Request Letter not generated yet
                                        </p>
                                        <p v-else class="mt-2 text-sm text-muted-foreground">
                                            Generate NAP Form 3 first
                                        </p>
                                        <div v-if="canPreviewRequestLetter" class="pt-2">
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                class="cursor-pointer h-7 text-xs"
                                                :disabled="isBatchReadOnly"
                                                @click="openRequestLetterPreview"
                                            >
                                                <FileText class="mr-1.5 h-3 w-3" />
                                                Preview
                                            </Button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Step 6: Inspection -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="batch.inspected_at
                                        ? 'border-indigo-500 bg-indigo-500 text-white'
                                        : isInspectionScheduled
                                            ? 'border-blue-500 bg-blue-500 text-white'
                                            : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <Eye class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Inspection
                                            <AlertCircle v-if="batch.status === 'rejected'" class="h-4 w-4 text-red-600 dark:text-red-400" />
                                            <CheckCircle2 v-if="batch.inspected_at" class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                            <CheckCircle2 v-else-if="isInspectionScheduled" class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        </h4>
                                        <span v-if="batch.inspected_at" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(batch.inspected_at) }}
                                        </span>
                                        <span v-else-if="batch.inspection_scheduled_at" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(batch.inspection_scheduled_at) }}
                                        </span>
                                    </div>

                                    <!-- Inspected state -->
                                    <div v-if="batch.inspected_at" class="flex flex-col gap-2 text-sm">
                                        <div v-if="batch.inspection_scheduled_at" class="flex gap-2">
                                            <span class="text-muted-foreground">Scheduled for:</span>
                                            <span class="font-medium">{{ formatDateTime(batch.inspection_scheduled_at) }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Actual Inspection:</span>
                                            <span class="font-medium">{{ formatDateTime(batch.inspected_at) }}</span>
                                        </div>
                                        <div v-if="batch.inspector_name" class="flex gap-2">
                                            <span class="text-muted-foreground">Inspector:</span>
                                            <span class="font-medium">{{ batch.inspector_name }}</span>
                                        </div>
                                        <div v-if="batch.inspected_by" class="flex gap-2">
                                            <span class="text-muted-foreground">Logged by:</span>
                                            <span class="font-medium">{{ batch.inspected_by.name }}</span>
                                        </div>
                                        <div v-if="batch.inspection_notes">
                                            <span class="text-muted-foreground">Notes:</span>
                                            <span class="ml-2 font-medium">{{ batch.inspection_notes }}</span>
                                        </div>
                                        <div v-if="batch.status === 'rejected' && batch.rejected_by" class="flex gap-2">
                                            <span class="text-muted-foreground">Rejected by:</span>
                                            <span class="font-medium">{{ batch.rejected_by.name }}</span>
                                        </div>
                                        <div v-if="batch.status === 'rejected' && batch.rejection_reason" class="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                            <p class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">Rejection Reason</p>
                                            <p class="text-sm text-red-800 dark:text-red-200">{{ batch.rejection_reason }}</p>
                                        </div>
                                        <div v-if="batch.status === 'rejected'" class="pt-2">
                                            <Button
                                                size="sm"
                                                class="cursor-pointer h-7 text-xs"
                                                @click="openReturnToDraftDialog"
                                            >
                                                <RotateCcw class="mr-1.5 h-3 w-3" />
                                                Return to Draft
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Scheduled but not yet inspected -->
                                    <div v-else-if="isInspectionScheduled" class="flex flex-col gap-2 text-sm">
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Scheduled for:</span>
                                            <span class="font-medium">{{ formatDateTime(batch.inspection_scheduled_at!) }}</span>
                                        </div>
                                        <div v-if="batch.inspection_scheduled_by" class="flex gap-2">
                                            <span class="text-muted-foreground">Scheduled by:</span>
                                            <span class="font-medium">{{ batch.inspection_scheduled_by.name }}</span>
                                        </div>
                                        <div
                                            v-if="isInspectionDue"
                                            class="mt-2 flex items-center gap-2 rounded-md border border-orange-200 bg-orange-50 p-2 dark:border-orange-800 dark:bg-orange-900/30"
                                        >
                                            <AlertCircle class="h-4 w-4 text-orange-600 dark:text-orange-400 flex-shrink-0" />
                                            <span class="text-xs text-orange-700 dark:text-orange-300">
                                                This inspection is due and should be completed.
                                            </span>
                                        </div>
                                        <div class="pt-2 flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                class="cursor-pointer h-7 text-xs"
                                                :disabled="batch.disposed_files_count === 0"
                                                @click="openInspectDialog"
                                            >
                                                <CheckCircle2 class="mr-1.5 h-3 w-3" />
                                                Mark as Inspected
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                class="cursor-pointer h-7 text-xs"
                                                @click="openScheduleInspectionDialog"
                                            >
                                                <CalendarClock class="mr-1.5 h-3 w-3" />
                                                Reschedule
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Not scheduled, not inspected -->
                                    <template v-else>
                                        <TooltipProvider v-if="isInspectionEligibleStatus && batch.disposed_files_count === 0" :delay-duration="200">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="text-sm text-amber-600 dark:text-amber-400 flex items-center gap-2">
                                                        <AlertCircle class="h-4 w-4 flex-shrink-0" />
                                                        <span>Cannot schedule: this batch has no files</span>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="text-sm">Add files to this batch before scheduling inspection.</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <p v-else class="text-sm text-muted-foreground">
                                            {{ scheduleInspectionHelpText }}
                                        </p>
                                        <div v-if="canScheduleInspection" class="pt-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                class="cursor-pointer h-7 text-xs"
                                                :disabled="batch.disposed_files_count === 0"
                                                @click="openScheduleInspectionDialog"
                                            >
                                                <CalendarClock class="mr-1.5 h-3 w-3" />
                                                Schedule Inspection
                                            </Button>
                                        </div>
                                        <TooltipProvider v-else-if="needsNapDownload" :delay-duration="200">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="pt-2 inline-flex">
                                                        <Badge variant="outline" class="text-xs">
                                                            <Download class="mr-1 h-3 w-3" />
                                                            Download required NAP forms
                                                        </Badge>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-xs">
                                                    <p class="text-sm">
                                                        Schedule Inspection is enabled once both NAP Form 1 and NAP Form 3 PDFs are downloaded.
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <TooltipProvider v-else-if="needsRequestLetterGuidance" :delay-duration="200">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <div class="pt-2 inline-flex">
                                                        <Badge variant="outline" class="text-xs">
                                                            <Mail class="mr-1 h-3 w-3" />
                                                            {{ requestLetterGuidanceLabel }}
                                                        </Badge>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-xs">
                                                    <p class="text-sm">
                                                        {{ requestLetterGuidanceDescription }}
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </template>
                                </div>
                            </div>

                            <!-- Step 7: Authority Confirmation -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasNapAuthorityDetails
                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <ShieldCheck class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Authority to Dispose (NAP)
                                            <CheckCircle2 v-if="hasNapAuthorityDetails" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        </h4>
                                        <span v-if="napAuthorityIssuedAtDisplay" class="text-xs text-muted-foreground sm:text-right">
                                            {{ napAuthorityIssuedAtDisplay }}
                                        </span>
                                    </div>

                                    <div v-if="hasNapAuthorityDetails" class="space-y-2 text-sm">
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Actual Mode:</span>
                                            <span class="font-medium">{{ actualModeOfDisposalLabel }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Authority Issued:</span>
                                            <span class="font-medium">{{ napAuthorityIssuedAtDisplay }}</span>
                                        </div>
                                        <div v-if="napAuthorityConfirmedByName" class="flex gap-2">
                                            <span class="text-muted-foreground">Confirmed by:</span>
                                            <span class="font-medium">{{ napAuthorityConfirmedByName }}</span>
                                        </div>
                                        <div class="pt-2">
                                            <Button
                                                v-if="canRecordNapAuthority"
                                                size="sm"
                                                variant="secondary"
                                                class="cursor-pointer h-7 text-xs"
                                                @click="openNapAuthorityDialog"
                                            >
                                                Update Details
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-else class="text-sm text-muted-foreground">
                                        <p>Record the issued authority and actual disposal mode once inspection has been logged.</p>
                                        <div class="pt-2" v-if="canRecordNapAuthority">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                class="cursor-pointer h-7 text-xs"
                                                @click="openNapAuthorityDialog"
                                            >
                                                Record Authority Details
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 8: Endorsement Letter -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasEndorsementCompleted
                                            ? 'border-emerald-500 bg-emerald-500 text-white'
                                            : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <CheckCircle2 class="h-4 w-4" />
                                </div>
                                <div class="flex-1 pb-4 border-b border-border">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Endorsement Letter to COA
                                            <CheckCircle2 v-if="hasEndorsementCompleted" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                        </h4>
                                        <span v-if="batch.approval_letter_generated_at" class="text-xs text-muted-foreground sm:text-right">
                                            {{ formatDateTime(batch.approval_letter_generated_at) }}
                                        </span>
                                    </div>

                                    <!-- Pending endorsement actions -->
                                    <div v-if="!hasEndorsementCompleted" class="flex flex-col gap-2 text-sm">
                                        <div v-if="hasNapAuthorityDetails" class="flex flex-wrap gap-2">
                                            <Button
                                                variant="default"
                                                size="sm"
                                                class="cursor-pointer h-7 text-xs"
                                                @click="openEndorsementFinalize"
                                            >
                                                <CheckCircle2 class="mr-1.5 h-3 w-3" />
                                                Finalize Endorsement Letter
                                            </Button>
                                        </div>
                                        <p v-else class="mt-2 text-muted-foreground">
                                            Record the NAP-issued authority and actual disposal mode to unlock endorsement actions.
                                        </p>
                                    </div>

                                    <!-- Endorsed state -->
                                    <div v-else-if="hasEndorsementCompleted" class="space-y-4">
                                        <div class="grid gap-2 text-sm md:grid-cols-2 lg:grid-cols-3">
                                            <div v-if="batch.approved_by" class="flex gap-2">
                                                <span class="text-muted-foreground">Endorsed by:</span>
                                                <span class="font-medium">{{ batch.approved_by.name }}</span>
                                            </div>
                                            <div v-if="batch.approval_notes" class="break-words md:col-span-2 lg:col-span-3">
                                                <span class="text-muted-foreground">Endorsement Notes:</span>
                                                <span class="ml-2 font-medium">{{ batch.approval_notes }}</span>
                                            </div>
                                        </div>

                                        <!-- COA Contact & Letter Details -->
                                        <div v-if="batch.approval_review_details || batch.coa || batch.approval_letter_details" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 pt-3 border-t border-border">
                                            <!-- COA Contact -->
                                            <div v-if="batch.approval_review_details?.coa_name || batch.coa?.name" class="space-y-1">
                                                <p class="text-xs font-semibold text-muted-foreground uppercase">COA Contact</p>
                                                <div class="text-sm space-y-0.5">
                                                    <p class="font-medium">{{ batch.approval_review_details?.coa_name || batch.coa?.name || 'Not captured' }}</p>
                                                    <p class="text-muted-foreground text-xs">
                                                        {{ batch.approval_review_details?.coa_position || batch.coa?.position || '' }}
                                                        <span v-if="(batch.approval_review_details?.coa_position || batch.coa?.position) && (batch.approval_review_details?.coa_designation || batch.coa?.designation)"> • </span>
                                                        {{ batch.approval_review_details?.coa_designation || batch.coa?.designation || '' }}
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Endorsement Letter to COA -->
                                            <div v-if="batch.approval_letter_details?.letter_date || batch.approval_letter_generated_at" class="space-y-1">
                                                <p class="text-xs font-semibold text-muted-foreground uppercase">Endorsement Letter to COA</p>
                                                <div class="flex flex-col gap-1 text-sm">
                                                    <div v-if="batch.approval_letter_details?.letter_date" class="flex gap-2">
                                                        <span class="text-muted-foreground">Letter Date:</span>
                                                        <span class="font-medium">{{ batch.approval_letter_details.letter_date }}</span>
                                                    </div>
                                                    <div v-if="batch.approval_letter_details?.downloaded_at" class="flex gap-2">
                                                        <span class="text-muted-foreground">Last Download:</span>
                                                        <span class="font-medium">{{ formatDateTime(batch.approval_letter_details.downloaded_at) }}</span>
                                                    </div>
                                                    <div v-if="batch.approval_letter_generated_by" class="flex gap-2">
                                                        <span class="text-muted-foreground">Generated by:</span>
                                                        <span class="font-medium">{{ batch.approval_letter_generated_by.name }}</span>
                                                    </div>
                                                </div>
                                                <div class="pt-1 flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        class="cursor-pointer h-7 text-xs"
                                                        @click="previewApprovalLetter"
                                                    >
                                                        <Eye class="mr-1.5 h-3 w-3" />
                                                        Preview
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        class="cursor-pointer h-7 text-xs"
                                                        :disabled="!hasApprovalLetter || !batch.approval_letter_file_exists"
                                                        @click="downloadApprovalLetter"
                                                    >
                                                        <Download class="mr-1.5 h-3 w-3" />
                                                        Download
                                                    </Button>
                                                </div>
                                                <p v-if="hasMissingApprovalLetterFile" class="pt-1 text-xs text-amber-700 dark:text-amber-300">
                                                    Generated file missing, please regenerate from Preview page.
                                                </p>
                                            </div>

                                            <!-- Approval Documents -->
                                            <div v-if="batch.approval_documents.length" class="space-y-1">
                                                <p class="text-xs font-semibold text-muted-foreground uppercase">Attachments</p>
                                                <div class="space-y-1">
                                                    <button
                                                        v-for="(document, index) in batch.approval_documents"
                                                        :key="`${document.name}-${index}`"
                                                        type="button"
                                                        class="w-full rounded-md border p-2 text-left transition-colors hover:bg-muted/40"
                                                        @click="openImagePreview(document)"
                                                    >
                                                        <div class="flex items-center justify-between gap-2 min-w-0">
                                                            <div class="min-w-0">
                                                                <p class="text-sm font-medium">Attachment {{ index + 1 }}</p>
                                                                <p class="break-all text-xs text-muted-foreground" :title="document.name">{{ document.name }}</p>
                                                            </div>
                                                            <div class="flex shrink-0 items-center gap-1">
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    type="button"
                                                                    class="h-7 w-7"
                                                                    @click.stop="openImagePreview(document)"
                                                                >
                                                                    <Eye class="h-3.5 w-3.5" />
                                                                </Button>
                                                                <a
                                                                    v-if="document.path"
                                                                    :href="document.path"
                                                                    :download="document.name"
                                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                                    @click.stop
                                                                >
                                                                    <Download class="h-3.5 w-3.5" />
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pending state -->
                                    <p v-else class="text-sm text-muted-foreground">Pending endorsement</p>
                                </div>
                            </div>

                            <!-- Step 9: Submission to COA -->
                            <div class="relative flex gap-4">
                                <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2"
                                    :class="hasCoaSubmission
                                        ? 'border-sky-500 bg-sky-500 text-white'
                                        : 'border-muted-foreground/30 bg-background text-muted-foreground'">
                                    <Send class="h-4 w-4" />
                                </div>
                                <div class="flex-1">
                                    <div class="mb-2 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-2">
                                        <h4 class="font-semibold text-sm flex items-center gap-2">
                                            Submission to COA
                                            <CheckCircle2 v-if="hasCoaSubmission" class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                        </h4>
                                        <span v-if="coaSubmissionTimestamp" class="text-xs text-muted-foreground sm:text-right">
                                            {{ coaSubmissionTimestamp }}
                                        </span>
                                    </div>

                                    <div v-if="hasCoaSubmission" class="space-y-2 text-sm">
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground">Submitted on:</span>
                                            <span class="font-medium">{{ coaSubmissionTimestamp }}</span>
                                        </div>
                                        <div v-if="coaSubmissionRecordedByName" class="flex gap-2">
                                            <span class="text-muted-foreground">Recorded by:</span>
                                            <span class="font-medium">{{ coaSubmissionRecordedByName }}</span>
                                        </div>
                                        <div class="rounded-md border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-200">
                                            Batch marked complete after confirming submission to COA.
                                        </div>
                                    </div>

                                    <div v-else-if="canRecordCoaSubmission" class="space-y-3 text-sm">
                                        <p class="mt-2 text-muted-foreground">
                                            Record when the endorsement package was delivered to COA to finish the workflow.
                                        </p>
                                        <Button
                                            size="sm"
                                            variant="default"
                                            class="cursor-pointer h-7 text-xs"
                                            @click="openCoaSubmissionDialog"
                                        >
                                            <Send class="mr-1.5 h-3 w-3" />
                                            Record Submission
                                        </Button>
                                    </div>

                                    <p v-else class="mt-2 text-sm text-muted-foreground">
                                        Submission tracking unlocks after the endorsement letter is finalized.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Files in Batch -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-muted-foreground" />
                            Files in Batch
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="batch.status === 'scheduled'"
                                variant="secondary"
                                size="sm"
                                @click="openAddFilesDialog"
                                class="cursor-pointer h-7 text-xs"
                            >
                                <Plus class="mr-1.5 h-3 w-3" />
                                Add Files
                            </Button>
                            <Badge variant="secondary">
                                {{ batch.items.length }} files
                            </Badge>
                        </div>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="hidden md:block overflow-x-auto">
                        <Table class="w-full">
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[5%]">
                                        <button
                                            @click="toggleSort('record_id')"
                                            class="flex items-center gap-1 hover:text-foreground transition-colors group"
                                        >
                                            Record ID
                                            <component
                                                :is="getSortIcon('record_id')"
                                                :class="[
                                                    'h-4 w-4 transition-all',
                                                    batchFilesSortColumn === 'record_id' ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                                                ]"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="w-[12%]">
                                        <button
                                            @click="toggleSort('file_name')"
                                            class="flex items-center gap-1 hover:text-foreground transition-colors group"
                                        >
                                            File Name
                                            <component
                                                :is="getSortIcon('file_name')"
                                                :class="[
                                                    'h-4 w-4 transition-all',
                                                    batchFilesSortColumn === 'file_name' ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                                                ]"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="w-[10%]">
                                        <button
                                            @click="toggleSort('author')"
                                            class="flex items-center gap-1 hover:text-foreground transition-colors group"
                                        >
                                            Author
                                            <component
                                                :is="getSortIcon('author')"
                                                :class="[
                                                    'h-4 w-4 transition-all',
                                                    batchFilesSortColumn === 'author' ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                                                ]"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="w-[12%]">Physical Location</TableHead>
                                    <TableHead class="w-[8%]">
                                        <button
                                            @click="toggleSort('status')"
                                            class="flex items-center gap-1 hover:text-foreground transition-colors group"
                                        >
                                            Status
                                            <component
                                                :is="getSortIcon('status')"
                                                :class="[
                                                    'h-4 w-4 transition-all',
                                                    batchFilesSortColumn === 'status' ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                                                ]"
                                            />
                                        </button>
                                    </TableHead>
                                    <TableHead class="w-[4%]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in paginatedBatchItems" :key="item.id">
                                    <TableCell class="min-w-0">
                                        <TooltipProvider :delay-duration="300">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="text-sm block truncate cursor-default">
                                                        {{ item.file?.record_id ?? 'N/A' }}
                                                    </span>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="text-sm">{{ item.file?.record_id ?? 'N/A' }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="min-w-0">
                                        <TooltipProvider :delay-duration="300">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <button
                                                        v-if="item.file"
                                                        @click="openFileViewer(item.file)"
                                                        class="max-w-full truncate text-left hover:text-primary hover:underline focus:text-primary focus:underline cursor-pointer transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded"
                                                        title="Click to preview"
                                                    >
                                                        {{ item.file.file_name }}
                                                    </button>
                                                    <p v-else class="max-w-full truncate cursor-default text-muted-foreground">
                                                        File not found
                                                    </p>
                                                </TooltipTrigger>
                                                <TooltipContent class="max-w-md">
                                                    <p class="text-sm">{{ item.file?.file_name ?? 'File not found' }}</p>
                                                    <p v-if="item.file" class="text-xs text-muted-foreground mt-1">Click to preview</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="min-w-0">
                                        <TooltipProvider :delay-duration="300">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <span class="truncate block cursor-default">{{ item.file?.author ?? 'N/A' }}</span>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="text-sm">{{ item.file?.author ?? 'N/A' }}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </TableCell>
                                    <TableCell class="min-w-0">
                                        <div class="space-y-1">
                                            <div v-if="getPhysicalLocationTags(getItemPhysicalLocation(item)).length" class="flex flex-wrap gap-1">
                                                <template v-if="getPhysicalLocationTags(getItemPhysicalLocation(item)).length > 3">
                                                    <Badge
                                                        v-for="tag in getPhysicalLocationTags(getItemPhysicalLocation(item)).slice(0, 3)"
                                                        :key="tag.id"
                                                        variant="outline"
                                                        class="text-xs"
                                                    >
                                                        {{ tag.name }}
                                                    </Badge>
                                                    <TooltipProvider>
                                                        <Tooltip>
                                                            <TooltipTrigger as-child>
                                                                <Badge variant="secondary" class="text-xs cursor-pointer">
                                                                    +{{ getPhysicalLocationTags(getItemPhysicalLocation(item)).length - 3 }} more
                                                                </Badge>
                                                            </TooltipTrigger>
                                                            <TooltipContent class="bg-popover text-popover-foreground">
                                                                <div class="flex flex-wrap gap-1 max-w-xs">
                                                                    <Badge
                                                                        v-for="tag in getPhysicalLocationTags(getItemPhysicalLocation(item))"
                                                                        :key="tag.id"
                                                                        variant="outline"
                                                                        class="text-xs"
                                                                    >
                                                                        {{ tag.name }}
                                                                    </Badge>
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </template>
                                                <template v-else>
                                                    <Badge
                                                        v-for="tag in getPhysicalLocationTags(getItemPhysicalLocation(item))"
                                                        :key="tag.id"
                                                        variant="outline"
                                                        class="text-xs"
                                                    >
                                                        {{ tag.name }}
                                                    </Badge>
                                                </template>
                                            </div>
                                            <p v-else class="text-sm text-muted-foreground truncate">N/A</p>
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <Badge
                                                variant="outline"
                                                :class="statusBadgeClass(item.file?.retention_status ?? 'unknown')"
                                                class="capitalize"
                                            >
                                                {{ item.file?.retention_status ?? 'Unknown' }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell class="min-w-0">
                                        <div class="flex items-center gap-1">
                                            <Button
                                                v-if="item.file"
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openFileViewer(item.file)"
                                                title="Preview file"
                                            >
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="item.file"
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8"
                                                @click="openInfoSheet(item.file.id, item.file.file_name)"
                                                title="View details"
                                            >
                                                <Info class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                v-if="batch.status === 'scheduled'"
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 text-destructive hover:text-destructive"
                                                @click="openRemoveFileDialog(item.file_id, item.file?.file_name ?? 'Unknown file')"
                                                title="Remove from batch"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Mobile: Cards for Files in Batch -->
                    <div v-if="paginatedBatchItems.length > 0" class="md:hidden flex flex-col gap-3">
                        <div v-for="item in paginatedBatchItems" :key="item.id" class="rounded-lg border bg-card p-4 shadow-sm hover:bg-muted/50 transition-colors">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <button
                                        v-if="item.file"
                                        @click="openFileViewer(item.file)"
                                        class="font-medium text-sm truncate hover:text-primary hover:underline focus:text-primary focus:underline cursor-pointer transition-colors text-left w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded"
                                        title="Click to preview"
                                    >
                                        {{ item.file.file_name }}
                                    </button>
                                    <p v-else class="font-medium text-sm truncate text-muted-foreground">File not found</p>
                                    <p class="text-xs text-muted-foreground truncate">{{ item.file?.record_id ?? 'N/A' }}</p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <Badge variant="outline" class="text-xs">{{ item.file?.retention_status ?? 'Unknown' }}</Badge>
                                </div>
                            </div>

                            <div class="space-y-2 text-xs text-muted-foreground">
                                <div class="flex justify-between gap-2">
                                    <span class="text-muted-foreground">Author</span>
                                    <span class="font-medium truncate">{{ item.file?.author ?? 'N/A' }}</span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-muted-foreground">Physical Location</span>
                                    <span class="font-medium truncate">{{ formatPhysicalLocation(getItemPhysicalLocation(item)) }}</span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-muted-foreground">Status</span>
                                    <div class="flex items-center gap-2">
                                        <Badge variant="outline" :class="statusBadgeClass(item.file?.retention_status ?? 'unknown')" class="capitalize text-xs">{{ item.file?.retention_status ?? 'Unknown' }}</Badge>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 pt-2">
                                    <Button
                                        v-if="item.file"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="openFileViewer(item.file)"
                                        title="Preview file"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="item.file"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="openInfoSheet(item.file.id, item.file.file_name)"
                                        title="View details"
                                    >
                                        <Info class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        v-if="batch.status === 'scheduled'"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        @click="openRemoveFileDialog(item.file_id, item.file?.file_name ?? 'Unknown file')"
                                        title="Remove from batch"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination for Files in Batch -->
                    <div
                        v-if="batch.items.length > 0"
                        class="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3 md:flex-row md:items-center md:justify-between md:rounded-none md:border-none md:bg-transparent md:px-0 md:py-0"
                    >
                        <div class="text-sm text-muted-foreground">
                            Showing {{ batchFilesFrom }}–{{ batchFilesTo }} of {{ batch.items.length }} files
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex items-center gap-2">
                                <Label class="text-xs text-muted-foreground">Show</Label>
                                <Select
                                    :model-value="String(batchFilesPerPage)"
                                    @update:model-value="(val) => changeBatchFilesPerPage(Number(val))"
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
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="goToBatchFilesPage(batchFilesPage - 1)"
                                    :disabled="batchFilesPage === 1"
                                    class="h-8 px-2"
                                >
                                    Previous
                                </Button>
                                <div class="px-3 text-xs text-muted-foreground">
                                    Page {{ batchFilesPage }} of {{ batchFilesTotalPages }}
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="goToBatchFilesPage(batchFilesPage + 1)"
                                    :disabled="batchFilesPage === batchFilesTotalPages"
                                    class="h-8 px-2"
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Audit Trail -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Archive class="h-5 w-5 text-muted-foreground" />
                        Audit Trail
                        <Badge variant="secondary" class="ml-auto">
                            {{ batch.audits.length }} events
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="batch.audits.length > 0" class="space-y-4">
                        <div
                            v-for="audit in batch.audits"
                            :key="audit.id"
                            class="flex gap-3 border-l-2 border-muted pl-4"
                        >
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="capitalize">
                                        {{ audit.action }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatDateTime(audit.created_at) }}
                                    </span>
                                </div>
                                <p v-if="audit.description" class="text-sm text-muted-foreground">
                                    {{ audit.description }}
                                </p>
                                <div v-if="audit.user" class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <User class="h-3 w-3" />
                                    {{ audit.user.name }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground">
                        No audit trail available
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Dates Dialog -->
        <Dialog v-model:open="editDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Set schedule of disposal of valueless records</DialogTitle>
                    <DialogDescription>
                        Set the disposal schedule and related details for this batch. Scheduled date and time must be current time or later.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="edit-scheduled-at">Scheduled Date & Time</Label>
                        <Input
                            id="edit-scheduled-at"
                            v-model="editForm.scheduled_at"
                            type="datetime-local"
                            :min="minScheduledDate"
                        />
                        <p class="text-xs text-muted-foreground">
                            Current: {{ batch.scheduled_at ? formatDateTime(batch.scheduled_at) : 'Not set' }}
                        </p>
                        <p v-if="isScheduleBeforeNow" class="text-xs text-destructive">
                            Scheduled date and time cannot be earlier than the current time.
                        </p>
                    </div>

                    <div v-if="isInspectionEligibleStatus" class="space-y-2">
                        <Label for="edit-completed-at">Verified Date & Time</Label>
                        <Input
                            id="edit-completed-at"
                            v-model="editForm.completed_at"
                            type="datetime-local"
                            :max="maxCompletedDate"
                        />
                        <p class="text-xs text-muted-foreground">
                            Current: {{ batch.completed_at ? formatDateTime(batch.completed_at) : 'Not set' }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-note">Note</Label>
                        <Textarea
                            id="edit-note"
                            v-model="editForm.scheduled_note"
                            placeholder="Add any notes about this disposal batch..."
                            rows="3"
                            maxlength="1000"
                        />
                        <p class="text-xs text-muted-foreground">
                            {{ editForm.scheduled_note.length }}/1000 characters
                        </p>
                    </div>

                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <div class="flex items-start gap-2">
                            <Clock class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Date Validation
                                </p>
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    Scheduled dates must be in the future (or now). Completion dates cannot be in the future and must be after the scheduled date.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false" :disabled="isSubmitting">
                        Cancel

                    </Button>
                    <Button @click="saveBatchDates" :disabled="!canSaveScheduleChanges">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        Save Changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- NAP Authority Dialog -->
        <Dialog v-model:open="napAuthorityDialogOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Record Authority to Dispose</DialogTitle>
                    <DialogDescription>
                        Confirm when the authority to dispose was issued by NAP and capture the actual mode used during disposal.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="actual-mode">Actual Mode of Disposal</Label>
                        <Select
                            :model-value="napAuthorityForm.actual_mode_of_disposal"
                            @update:model-value="(value) => { napAuthorityForm.actual_mode_of_disposal = value || ''; }"
                        >
                            <SelectTrigger id="actual-mode">
                                <SelectValue placeholder="Select actual mode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="option in modeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-3 rounded-lg border border-dashed border-emerald-200 bg-emerald-50/40 p-3 dark:border-emerald-900/60 dark:bg-emerald-900/10">
                        <div class="flex items-start gap-3">
                            <Checkbox id="nap-form5-confirm" v-model="napAuthorityForm.has_form5" class="mt-1" />
                            <div class="space-y-1">
                                <Label for="nap-form5-confirm" class="text-sm font-medium">
                                    Has NAP issued the Form 5 authority?
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Check this once the signed NAP Form 5 &mdash; Authority to Dispose of Records has been received.
                                </p>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            The scanned Form 5 will be uploaded alongside the endorsement documents after this step.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="napAuthorityDialogOpen = false" :disabled="isNapAuthoritySubmitting">
                        Cancel
                    </Button>
                    <Button @click="submitNapAuthority" :disabled="isNapAuthoritySubmitting || !napAuthorityForm.has_form5">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        Save Details
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- COA Submission Dialog -->
        <Dialog v-model:open="coaSubmissionDialogOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Confirm Submission to COA</DialogTitle>
                    <DialogDescription>
                        Record that the endorsement package was delivered to COA to close out this disposal batch.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-900/20 dark:text-sky-100">
                        <p class="font-medium">Final Workflow Step</p>
                        <p class="mt-1 text-xs text-sky-700 dark:text-sky-300">
                            Once recorded, this batch will be marked as <strong>Complete</strong> and locked for audit purposes.
                        </p>
                    </div>
                    <div class="flex items-start gap-3">
                        <Checkbox id="coa-submission-confirm" v-model="coaSubmissionConfirmed" class="mt-1" />
                        <div class="space-y-1">
                            <Label for="coa-submission-confirm" class="text-sm font-medium">
                                The endorsement package has been submitted to COA
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Include signed letters, NAP forms, and any supporting files in the package before confirming.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="coaSubmissionDialogOpen = false" :disabled="isCoaSubmissionSubmitting">
                        Cancel
                    </Button>
                    <Button
                        @click="submitCoaSubmission"
                        :disabled="isCoaSubmissionSubmitting || !coaSubmissionConfirmed"
                    >
                        <Send class="mr-2 h-4 w-4" />
                        Confirm Submission
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Verify Batch Dialog -->
        <Dialog v-model:open="completeDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Verify Batch</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to verify this disposal batch? This confirms that the files listed are correct and ready for disposal processing. This action will:
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/30">
                        <ul class="space-y-2 text-sm text-amber-800 dark:text-amber-200">
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                <span>Change batch status to "Verified"</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                <span>Record verification date and time</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 flex-shrink-0" />
                                <span>Create an audit log entry</span>
                            </li>
                        </ul>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        <strong>Batch:</strong> {{ batch.reference }}<br>
                        <strong>Files:</strong> {{ batch.file_count }} file{{ batch.file_count === 1 ? '' : 's' }}
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="completeDialogOpen = false" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button @click="confirmComplete" :disabled="isSubmitting">
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        Confirm Verification
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Files Dialog -->
        <Dialog v-model:open="addFilesDialogOpen">
            <DialogContent class="sm:max-w-[900px] max-h-[90vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Add Files to Batch</DialogTitle>
                    <DialogDescription>
                        Select files that are ready for disposal but not yet in a batch.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4 flex-1 overflow-hidden flex flex-col">
                    <!-- Search & Filters -->
                    <div class="flex flex-col gap-3 md:flex-row md:items-start">
                        <div class="flex-1">
                            <DisposalFilterComponent
                                :search-query="searchQuery"
                                :search-placeholder="'Search by record ID, author, file name, folder, or department...'"
                                :show-filters="showFilters"
                                :retention-duration-filter="retentionDurationFilter"
                                :root-folder-filter="rootFolderFilter"
                                :department-filter="departmentFilter"
                                :expires-from-filter="expiresFromFilter"
                                :expires-to-filter="expiresToFilter"
                                :released-from-filter="releasedFromFilter"
                                :released-to-filter="releasedToFilter"
                                :retention-durations="filterOptions.retention_durations"
                                :root-folders="filterOptions.root_folders"
                                :departments="filterOptions.departments"
                                @update:search-query="searchQuery = $event; fetchAvailableFiles(true)"
                                @update:show-filters="showFilters = $event"
                                @update:retention-duration-filter="retentionDurationFilter = $event; fetchAvailableFiles(true)"
                                @update:root-folder-filter="rootFolderFilter = $event; fetchAvailableFiles(true)"
                                @update:department-filter="departmentFilter = $event; fetchAvailableFiles(true)"
                                @update:expires-from-filter="expiresFromFilter = $event; fetchAvailableFiles(true)"
                                @update:expires-to-filter="expiresToFilter = $event; fetchAvailableFiles(true)"
                                @update:released-from-filter="releasedFromFilter = $event; fetchAvailableFiles(true)"
                                @update:released-to-filter="releasedToFilter = $event; fetchAvailableFiles(true)"
                                @clear-filters="clearFilters"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <Label class="text-sm text-muted-foreground whitespace-nowrap">Urgency</Label>
                            <Select
                                :model-value="urgencyFilter"
                                @update:model-value="(val) => { urgencyFilter = val || 'all'; fetchAvailableFiles(true); }"
                            >
                                <SelectTrigger class="w-40">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="immediate">Immediate</SelectItem>
                                    <SelectItem value="soon">Soon</SelectItem>
                                    <SelectItem value="scheduled">Scheduled</SelectItem>
                                    <SelectItem value="perpetual">Perpetual</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- File List -->
                    <div class="border rounded-lg flex-1 overflow-y-auto">
                        <div v-if="isLoadingFiles" class="p-8 text-center text-muted-foreground">
                            Loading available files...
                        </div>
                        <div v-else-if="availableFiles.length === 0" class="p-8 text-center text-muted-foreground">
                            No available files found.
                        </div>
                        <div v-else class="divide-y">
                            <div
                                v-for="file in availableFiles"
                                :key="file.id"
                                class="flex items-start gap-3 p-3 hover:bg-muted/50 cursor-pointer transition-colors"
                                @click="toggleFileSelection(file.id)"
                            >
                                <input
                                    type="checkbox"
                                    :checked="selectedFilesToAdd.has(file.id)"
                                    class="mt-1"
                                    @change="toggleFileSelection(file.id)"
                                    @click.stop
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2 flex-wrap">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-sm truncate">{{ file.file_name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ file.record_id }}</p>
                                        </div>
                                        <div class="flex gap-1.5 flex-wrap items-center">
                                            <Badge
                                                v-if="file.urgency"
                                                :class="getUrgencyBadgeClass(file.urgency)"
                                                class="text-[10px] px-1.5 py-0.5"
                                            >
                                                {{ getUrgencyLabel(file.urgency) }}
                                            </Badge>
                                            <Badge
                                                v-if="file.retention.duration"
                                                variant="outline"
                                                class="text-[10px] px-1.5 py-0.5"
                                            >
                                                {{ getRetentionLabel(file.retention.duration) }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span v-if="file.author">{{ file.author }}</span>
                                        <span v-if="file.retention.expires_at">
                                            Expires: {{ formatDate(file.retention.expires_at) }}
                                        </span>
                                        <span v-if="file.department">
                                            {{ file.department.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination & Selection Actions -->
                    <div class="space-y-3 border-t pt-3">
                        <!-- Pagination Info & Controls -->
                        <div v-if="pagination.total > 0" class="flex items-center justify-between text-sm">
                            <div class="text-muted-foreground">
                                Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }}
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Per Page Selector -->
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs text-muted-foreground">Show</Label>
                                    <Select
                                        :model-value="String(filesPerPage)"
                                        @update:model-value="(val) => changePerPage(Number(val))"
                                    >
                                        <SelectTrigger class="h-8 w-16 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in perPageOptions"
                                                :key="option"
                                                :value="String(option)"
                                            >
                                                {{ option }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Page Navigation -->
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="goToPage(currentPage - 1)"
                                        :disabled="currentPage === 1"
                                        class="h-8 px-2"
                                    >
                                        Previous
                                    </Button>
                                    <div class="px-3 text-xs text-muted-foreground">
                                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="goToPage(currentPage + 1)"
                                        :disabled="currentPage === pagination.last_page"
                                        class="h-8 px-2"
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Selection Actions -->
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">
                                {{ selectedFilesToAdd.size }} file{{ selectedFilesToAdd.size === 1 ? '' : 's' }} selected
                            </span>
                            <div class="flex gap-2">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="selectAllFiles"
                                    :disabled="availableFiles.length === 0"
                                >
                                    Select All on Page
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="deselectAllFiles"
                                    :disabled="selectedFilesToAdd.size === 0"
                                >
                                    Deselect All
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="addFilesDialogOpen = false" :disabled="isSubmitting" class="cursor-pointer">
                        Cancel
                    </Button>
                    <Button
                        @click="addFilesToBatch"
                        :disabled="isSubmitting || selectedFilesToAdd.size === 0"
                        class="cursor-pointer"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Add {{ selectedFilesToAdd.size }} File{{ selectedFilesToAdd.size === 1 ? '' : 's' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove File Confirmation Dialog -->
        <Dialog v-model:open="removeFileDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Remove File from Batch</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to remove this file from the disposal batch?
                    </DialogDescription>
                </DialogHeader>

                <div v-if="fileToRemove" class="py-4">
                    <div class="rounded-lg bg-muted p-4 space-y-2">
                        <p class="text-sm font-medium">{{ fileToRemove.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            This action will update the batch file count and create an audit log entry.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="removeFileDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmRemoveFile"
                        :disabled="isSubmitting"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Remove File
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Batch Confirmation Dialog -->
        <Dialog v-model:open="deleteBatchDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Delete Disposal Batch</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this disposal batch? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4">
                    <div class="rounded-lg bg-destructive/10 border border-destructive/20 p-4 space-y-3">
                        <div class="space-y-2">
                            <p class="text-sm font-semibold">{{ batch.reference }}</p>
                            <div class="text-xs text-muted-foreground space-y-1">
                                <p><span class="font-medium">Files in batch:</span> {{ batch.file_count }}</p>
                                <p><span class="font-medium">Status:</span> {{ batch.status }}</p>
                                <p v-if="batch.scheduled_at">
                                    <span class="font-medium">Scheduled:</span> {{ formatDateTime(batch.scheduled_at) }}
                                </p>
                                <p v-if="batch.scheduled_note" class="mt-2">
                                    <span class="font-medium">Note:</span> {{ batch.scheduled_note }}
                                </p>
                            </div>
                        </div>
                        <div class="border-t border-destructive/20 pt-3">
                            <p class="text-sm text-destructive font-semibold flex items-center gap-2">
                                <AlertCircle class="h-4 w-4" />
                                Warning
                            </p>
                            <p class="text-xs text-muted-foreground mt-1">
                                All {{ batch.file_count }} file(s) will be removed from this batch and the batch will be permanently deleted. The files will become available for other disposal batches.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteBatchDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmDeleteBatch"
                        :disabled="isSubmitting"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete Batch
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Mark as Inspected Dialog -->
        <Dialog v-model:open="inspectDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Mark Batch as Inspected</DialogTitle>
                    <DialogDescription>
                        Record that the physical inspection of this disposal has been completed.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 p-4">
                        <div class="flex items-start gap-3">
                            <Info class="h-5 w-5 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1 text-sm">
                                <p class="font-medium text-purple-900 dark:text-purple-100">
                                    Face-to-Face Inspection Completed
                                </p>
                                <p class="text-purple-700 dark:text-purple-300">
                                    By marking this batch as inspected, you are recording that a physical inspection of the disposal process has been completed.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="inspector-name">Inspector <span class="text-red-500">*</span></Label>
                        <Input
                            id="inspector-name"
                            v-model="inspectorName"
                            @blur="applyInspectorTitleCase"
                            @input="inspectorNameError = ''"
                            @keydown.enter.prevent="applyInspectorTitleCase"
                            placeholder="Enter the name of the inspector"
                            :aria-invalid="inspectorNameError ? 'true' : 'false'"
                            :aria-describedby="inspectorNameError ? 'inspector-name-error' : undefined"
                            :class="inspectorNameError ? 'border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40' : ''"
                        />
                        <p v-if="inspectorNameError" id="inspector-name-error" class="text-xs text-destructive">
                            {{ inspectorNameError }}
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            This person will be recorded as the official inspector for the batch.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="inspection-result">Inspection Result <span class="text-red-500">*</span></Label>
                        <Select
                            :model-value="inspectionResult"
                            @update:model-value="updateInspectionResult"
                        >
                            <SelectTrigger id="inspection-result">
                                <SelectValue placeholder="Select inspection result" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="passed">Passed inspection</SelectItem>
                                    <SelectItem value="rejected">Rejected during inspection</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="inspection-notes">Inspection Notes (Optional)</Label>
                        <Textarea
                            id="inspection-notes"
                            v-model="inspectionNotes"
                            @keyup.enter="applyInspectionNotesSentenceCase"
                            placeholder="Add any notes about the inspection..."
                            rows="3"
                        />
                    </div>

                    <div v-if="inspectionResult === 'rejected'" class="space-y-2">
                        <Label for="inspection-rejection-reason" class="text-red-700 dark:text-red-300">
                            Rejection Reason <span class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="inspection-rejection-reason"
                            v-model="inspectionRejectionReason"
                            @keyup.enter="applyInspectionRejectionReasonSentenceCase"
                            placeholder="Explain why this batch is being rejected..."
                            rows="3"
                        />
                        <p class="text-xs text-muted-foreground">
                            This reason will be recorded in the audit log and visible to all users.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="inspectDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmMarkAsInspected"
                        :disabled="isSubmitting"
                    >
                        <component :is="inspectionResult === 'rejected' ? AlertCircle : CheckCircle2" class="mr-2 h-4 w-4" />
                        {{ inspectionResult === 'rejected' ? 'Confirm Rejection' : 'Confirm Inspection' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Request Letter Warning Dialog -->
        <Dialog v-model:open="requestLetterWarningDialogOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Confirm Request Letter Delivery</DialogTitle>
                    <DialogDescription>
                        The Request Letter for NAP is dated
                        <span class="font-semibold">
                            {{ requestLetterDateLabel || 'Not specified' }}
                        </span>
                        which is later than today ({{ todayDisplay }}). Confirm it has already been sent to NAP before scheduling an inspection.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/30">
                        <div class="flex items-start gap-3">
                            <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1 text-sm">
                                <p class="font-medium text-amber-900 dark:text-amber-100">
                                    Send the Request Letter first
                                </p>
                                <p class="text-amber-800 dark:text-amber-200">
                                    Scheduling inspections ahead of the Request Letter date is only allowed if the letter has already been delivered to NAP.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Have you already sent the Request Letter to NAP?
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="cancelRequestLetterWarning"
                        :disabled="isSubmitting"
                    >
                        Not yet
                    </Button>
                    <Button
                        @click="confirmRequestLetterSent"
                        :disabled="isSubmitting"
                    >
                        Yes, it has been sent
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Schedule Inspection Dialog -->
        <Dialog v-model:open="scheduleInspectionDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Schedule Inspection</DialogTitle>
                    <DialogDescription>
                        Set the date and time for the physical inspection of this disposal batch.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-4">
                        <div class="flex items-start gap-3">
                            <CalendarClock class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1 text-sm">
                                <p class="font-medium text-blue-900 dark:text-blue-100">
                                    Inspection Scheduling
                                </p>
                                <p class="text-blue-700 dark:text-blue-300">
                                    Schedule when the physical inspection of the disposal process will take place. You can mark the inspection as completed before or after the scheduled time.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="inspection-scheduled-at">Inspection Date & Time <span class="text-red-500">*</span></Label>
                        <Input
                            id="inspection-scheduled-at"
                            v-model="inspectionScheduledAt"
                            type="datetime-local"
                            :min="minScheduledDate"
                        />
                        <p v-if="isInspectionScheduleBeforeNow" class="text-xs text-destructive">
                            Inspection date and time cannot be earlier than the current Manila time.
                        </p>
                        <p v-if="batch.inspection_scheduled_at" class="text-xs text-muted-foreground">
                            Currently scheduled: {{ formatDateTime(batch.inspection_scheduled_at) }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="scheduleInspectionDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmScheduleInspection"
                        :disabled="isSubmitting"
                    >
                        <CalendarClock class="mr-2 h-4 w-4" />
                        {{ batch.inspection_scheduled_at ? 'Reschedule Inspection' : 'Schedule Inspection' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Return To Draft Dialog -->
        <Dialog v-model:open="returnToDraftDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Return Batch to Draft</DialogTitle>
                    <DialogDescription>
                        Return this rejected batch to scheduled state so files, dates, and notes can be updated.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <div class="rounded-lg bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 p-4">
                        <div class="flex items-start gap-3">
                            <RotateCcw class="h-5 w-5 text-sky-600 dark:text-sky-400 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1 text-sm">
                                <p class="font-medium text-sky-900 dark:text-sky-100">
                                    Second-Chance Workflow
                                </p>
                                <p class="text-sky-700 dark:text-sky-300">
                                    This action resets approval workflow fields and makes the batch editable again. The original rejection and this return action are both preserved in the audit trail.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="return-to-draft-reason" class="text-sky-700 dark:text-sky-300">
                            Return Reason <span class="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="return-to-draft-reason"
                            v-model="returnToDraftReason"
                            @keyup.enter="applyReturnToDraftReasonSentenceCase"
                            placeholder="Explain why this batch is being returned to draft..."
                            rows="4"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            This reason is required and will be recorded in the audit log.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="returnToDraftDialogOpen = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="confirmReturnToDraft"
                        :disabled="isSubmitting || !returnToDraftReason.trim()"
                    >
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Return to Draft
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- File Info Sheet -->
        <ItemMetadataSheet
            v-model:open="infoSheetOpen"
            :item-name="selectedFileName"
            :has-metadata="selectedFileMetadata.length > 0"
            :metadata-entries="selectedFileMetadata"
            :file-id="selectedFileId"
            :file-name="selectedFileName"
            :has-file="true"
            :uploaded-at="selectedBatchFile?.created_at ?? null"
            :modified-at="selectedBatchFile?.updated_at ?? null"
            :disable-version-actions="true"
        />

        <!-- Image Preview Dialog -->
        <ImagePreviewDialog
            :open="imagePreviewOpen"
            :image-url="previewImageUrl"
            :image-name="previewImageName"
            @update:open="closeImagePreview"
        />

        <!-- PDF Viewer -->
        <FileViewer
            v-if="viewerState"
            :file="viewerState.file"
            :preview-url="viewerState.previewUrl"
            :download-url="viewerState.downloadUrl"
            :preview-available="viewerState.previewAvailable"
            :preview-unavailable-reason="viewerState.previewUnavailableReason"
            :on-close="closePreview"
        />
    </AppLayout>
</template>
