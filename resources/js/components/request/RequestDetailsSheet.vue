<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { apiClient } from '@/lib/axios';
import requestsApi from '@/routes/api/requests';
import filesApi from '@/routes/api/files';
import { useToast } from '@/composables/useToast';
import {
    Paperclip,
    Upload,
    X,
    FileText,
    Calendar,
    User,
    Building2,
    FileType,
    Hash,
    Clock,
    Eye,
    MoreVertical,
    Info,
    AlertCircle,
    CheckCircle,
    Flag,
    Tag as TagIcon,
    Loader2,
    Check,
} from 'lucide-vue-next';
import AttachFileDialog from './AttachFileDialog.vue';
import UploadScanDialog from './UploadScanDialog.vue';
import type { RequestRecord, File as FileSchema, Department } from '@/types/schema';
import { formatDate, formatFileSize, formatMonthYear } from '@/utils/format';
import { openFile } from '@/utils/itemNavigation';
import ItemMetadataSheet from '@/components/records/ItemMetadataSheet.vue';

const props = defineProps<{
    request: RequestRecord | null;
    showAttachedFiles?: boolean;
    showStatusActions?: boolean;
    showScannedDocs?: boolean;
}>();

// When false, hide the scanned uploads UI (used by My Requests page)
const showScannedDocs = computed(() => props.showScannedDocs ?? true);

const emit = defineEmits<{
    requestUpdated: [request: RequestRecord];
}>();

const showAttachedFiles = computed(() => props.showAttachedFiles ?? true);
const showStatusActions = computed(() => props.showStatusActions ?? true);

const open = defineModel<boolean>('open', { default: false });

const { success, error } = useToast();

const showAttachDialog = ref(false);
const showUploadDialog = ref(false);
const updatingStatus = ref(false);
const statusDraft = ref(props.request?.status ?? '');
const showRejectDialog = ref(false);
const rejectionNotes = ref('');
const pendingStatusChange = ref<string | null>(null);
const submittingRejection = ref(false);
const showStatusConfirmDialog = ref(false);
type StatusConfirmMessage = {
    title: string;
    description: string;
    impact: 'high' | 'medium' | 'low';
    consequences: string[];
    canProceed?: boolean;
};

const statusConfirmMessage = ref<StatusConfirmMessage>({
    title: '',
    description: '',
    impact: 'low',
    consequences: [],
    canProceed: true,
});

// Local request state that updates independently
const localRequest = ref<RequestRecord | null>(props.request);

// Local status state so UI updates immediately after backend change
const status = ref(props.request?.status ?? '');
const requiresScan = computed(() => Boolean(localRequest.value?.requires_scan));
const hasApprovedScan = computed(() => localRequest.value?.scan_review_status === 'Approved');
const disableMutableFields = computed(() => hasApprovedScan.value);

// Separate linked files from uploaded scans
const linkedFiles = computed(() => {
    if (!localRequest.value?.files) return [];
    return localRequest.value.files.filter(file =>
        !file.record_id?.startsWith('SCAN-')
    );
});

const uploadedScans = computed(() => {
    if (!localRequest.value?.files) return [];
    return localRequest.value.files.filter(file =>
        file.record_id?.startsWith('SCAN-')
    );
});

const draftScans = computed(() => {
    return uploadedScans.value.filter(file => file.status === 'draft');
});

const submittedScans = computed(() => {
    return uploadedScans.value.filter(file => file.status !== 'draft');
});

const hasDraftScans = computed(() => draftScans.value.length > 0);
const showSubmitConfirmDialog = ref(false);
const submittingScans = ref(false);

const linkedFileIds = computed(() => linkedFiles.value.map(file => file.id));
const hasLinkedFile = computed(() => linkedFiles.value.length > 0);
const canLinkFile = computed(() => !disableMutableFields.value && linkedFiles.value.length === 0);
const linkDisabledMessage = computed(() => {
    if (hasApprovedScan.value) {
        return 'A scan has already been approved for this request. Linked records cannot be changed.';
    }

    if (hasLinkedFile.value) {
        return 'Only one linked file is allowed per request.';
    }

    return null;
});

const pendingScanTargetIds = computed(() => {
    const targets = new Set<string>();

    uploadedScans.value.forEach((file) => {
        if (file.status === 'draft' || file.status === 'pending') {
            const targetId = file.pending_scan_metadata?.target_file_id;
            if (targetId) {
                targets.add(targetId);
            }
        }
    });

    return targets;
});

const availableUploadTargets = computed(() => linkedFiles.value.filter(file => !pendingScanTargetIds.value.has(file.id)));
// Track which linked files currently have an uploaded scan targeting them.
const linkedFileBlockedByScans = computed(() => {
    const set = new Set<string>();

    uploadedScans.value.forEach((file) => {
        const targetId = file.pending_scan_metadata?.target_file_id;
        if (targetId) {
            set.add(String(targetId));
        }
    });

    return set;
});
const hasUploadedScan = computed(() => uploadedScans.value.length > 0);
const uploadLimitReached = computed(() => hasUploadedScan.value || (linkedFiles.value.length > 0 && availableUploadTargets.value.length === 0));
const canUploadScan = computed(() => requiresScan.value && !hasApprovedScan.value && !hasUploadedScan.value && availableUploadTargets.value.length > 0);
const uploadDisabledMessage = computed(() => {
    if (!requiresScan.value) {
        return 'Scanned uploads are not required for this request.';
    }

    if (hasApprovedScan.value) {
        return 'An admin already approved the scan for this request. Uploads are disabled.';
    }

    if (linkedFiles.value.length === 0) {
        return 'Link at least one record before uploading scans.';
    }

    if (hasUploadedScan.value) {
        return 'Only one scanned document is allowed per request. Remove the existing scan before uploading again.';
    }

    if (uploadLimitReached.value) {
        return 'A pending scan already exists for this request. Wait for admin review or remove the existing scan before uploading again.';
    }

    return null;
});

const hydrateRequestIfNeeded = async (request: RequestRecord | null = localRequest.value): Promise<void> => {
    if (!request || hydratingRequest.value) {
        return;
    }

    if (hasRequesterRelation(request)) {
        return;
    }

    hydratingRequest.value = true;
    try {
        const { data } = await apiClient.get<RequestRecord>(requestsApi.show.url(request.id));
        localRequest.value = data;
        emit('requestUpdated', data);
    } catch {
        // Leave stale UI data; sheet will still function read-only.
    } finally {
        hydratingRequest.value = false;
    }
};

type MetadataEntry = {
    label: string;
    value: string;
};

const page = usePage();
const departments = computed<Department[]>(() => (page.props.departments as Department[] | undefined) ?? []);
const currentUser = computed(() => page.props.auth?.user as { id: string } | undefined);
const userHasManageRequestsPermission = computed(() => {
    const can = page.props.auth?.can as Record<string, boolean> | undefined;
    return can?.manageRequests ?? false;
});

const resolveRequesterId = (request: RequestRecord | null): string | null => {
    if (!request) {
        return null;
    }

    const requestedBy = request.requested_by;

    if (typeof requestedBy === 'string') {
        return requestedBy;
    }

    if (requestedBy && typeof requestedBy === 'object' && 'id' in requestedBy && requestedBy.id) {
        return requestedBy.id;
    }

    if (request.requested_by_relation?.id) {
        return request.requested_by_relation.id;
    }

    return null;
};

const hasRequesterRelation = (request: RequestRecord | null): boolean => {
    const requestedBy = request?.requested_by;
    return Boolean(requestedBy && typeof requestedBy === 'object' && 'id' in requestedBy);
};

// Is the current user the requester/owner of this request?
const isOwner = computed(() => {
    if (!currentUser.value) {
        return false;
    }

    const requesterId = resolveRequesterId(localRequest.value);
    return Boolean(requesterId && requesterId === currentUser.value.id);
});

// If this sheet is opened from the My Requests page treat the viewer as the requester
// NOTE: intentionally only checks the URL path (no permission/role checks)
const viewerIsRequester = computed(() => {
    try {
        const url = String(page.url || '');
        return url.includes('/requests/myrequests');
    } catch {
        return false;
    }
});

const requesterName = computed(() => {
    const requestedBy = localRequest.value?.requested_by;

    if (requestedBy && typeof requestedBy === 'object' && 'name' in requestedBy) {
        return requestedBy.name ?? null;
    }

    return localRequest.value?.requested_by_relation?.name ?? null;
});

const requesterEmail = computed(() => {
    const requestedBy = localRequest.value?.requested_by;

    if (requestedBy && typeof requestedBy === 'object' && 'email' in requestedBy) {
        return requestedBy.email ?? null;
    }

    return localRequest.value?.requested_by_relation?.email ?? null;
});

// "Will Return Updated Copy" toggle state
const updatingRequiresScan = ref(false);
const showWillReturnCopyConfirmDialog = ref(false);
const pendingRequiresScanValue = ref<boolean | null>(null);
const requiresScanJustUpdated = ref(false);
const undoPreviousValue = ref<boolean | null>(null);
const undoTimer = ref<number | null>(null);
const hydratingRequest = ref(false);

// Determine if user can edit requires_scan field
const canEditRequiresScan = computed(() => {
    if (!localRequest.value || !currentUser.value) return false;

    // Check if user owns the request or has manage-requests permission
    if (!isOwner.value && !userHasManageRequestsPermission.value) return false;

    // Check if request is in a terminal state
    const terminalStates = ['Complete', 'Rejected'];
    if (terminalStates.includes(localRequest.value.status)) return false;

    // Check if request has uploaded scans
    if (hasUploadedScan.value) return false;

    return true;
});

const showMetadataSheet = ref(false);
const metadataItemName = ref('');
const selectedMetadataEntries = ref<MetadataEntry[]>([]);
const hasMetadataEntries = computed(() => selectedMetadataEntries.value.length > 0);
const selectedUploadedAt = ref<string | null>(null);
const selectedModifiedAt = ref<string | null>(null);
const metadataTags = computed(() => localRequest.value?.metadata_tags ?? []);

const priorityLabel = computed(() => {
    const priority = localRequest.value?.priority;
    if (priority === 'urgent') {
        return 'Urgent';
    }
    return 'Standard';
});

const isUrgent = computed(() => localRequest.value?.priority === 'urgent');

const formattedDateRange = computed(() => {
    const start = formatMonthYear(localRequest.value?.start_date);
    const end = formatMonthYear(localRequest.value?.end_date);
    return `${start} � ${end}`;
});

const normalizeMetadataValue = (value: unknown): string => {
    if (value === undefined || value === null || value === '') {
        return '�';
    }

    return String(value);
};

const buildMetadataEntries = (file: FileSchema): MetadataEntry[] => {
    const resolvedDepartment = departments.value.find((dept) => String(dept.id) === String(file.department_id))?.name;

    return [
        { label: 'File name', value: file.file_name },
        { label: 'File size', value: file.file_size ? formatFileSize(file.file_size) : undefined },
        { label: 'Record ID', value: file.record_id },
        { label: 'Author', value: file.author },
        { label: 'Division', value: resolvedDepartment ?? file.department_id },
        { label: 'Physical location', value: file.physical_location },
        { label: 'Folder', value: file.folder?.name ?? file.folder_id },
    ].map(({ label, value }) => ({
        label,
        value: normalizeMetadataValue(value),
    }));
};

const openMetadataSheet = async (file: FileSchema): Promise<void> => {
    metadataItemName.value = file.file_name || 'Linked record';
    showMetadataSheet.value = true;
    selectedMetadataEntries.value = [];
    selectedUploadedAt.value = file.created_at ?? null;
    selectedModifiedAt.value = file.updated_at ?? null;

    try {
        const { data } = await apiClient.get<FileSchema>(filesApi.show.url(file.id));
        selectedMetadataEntries.value = buildMetadataEntries(data);
        selectedUploadedAt.value = data.created_at ?? selectedUploadedAt.value;
        selectedModifiedAt.value = data.updated_at ?? selectedModifiedAt.value;
    } catch {
        selectedMetadataEntries.value = buildMetadataEntries(file);
        error('Unable to load file details', 'Showing limited metadata.');
    }
};

// NOTE: removed native click fallback; checkbox now relies on `update:checked` event only

watch(() => props.request, (r) => {
    localRequest.value = r;
    status.value = r?.status ?? '';
    statusDraft.value = status.value;
    if (r && open.value) {
        void hydrateRequestIfNeeded(r);
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        void hydrateRequestIfNeeded(localRequest.value);
    }
});

watch(showRejectDialog, (isOpen, wasOpen) => {
    if (!isOpen && wasOpen) {
        pendingStatusChange.value = null;
        rejectionNotes.value = '';
        statusDraft.value = status.value;
    }
});

watch(showStatusConfirmDialog, (isOpen, wasOpen) => {
    if (!isOpen && wasOpen) {
        pendingStatusChange.value = null;
        statusDraft.value = status.value;
    }
});

const statusOptions = computed(() => {
    const options = [
        'Pending',
        'Claimed',
        'Awaiting Update',
        'In Review',
        'Complete',
        'Rejected',
    ];

    return requiresScan.value
        ? options
        : options.filter((option) => option !== 'Awaiting Update');
});

const statusVariant = computed(() => {
    if (!localRequest.value) return 'default';
    switch (status.value) {
        case 'Complete': return 'default';
        case 'Rejected': return 'destructive';
        case 'Pending': return 'secondary';
        case 'Claimed': return 'outline';
        case 'Awaiting Update': return 'outline';
        case 'In Review': return 'outline';
        default: return 'secondary';
    }
});

const scanStatusLabel = computed<string>(() => {
    if (!requiresScan.value) {
        return 'Not Required';
    }

    const reviewStatus = localRequest.value?.scan_review_status;

    if (reviewStatus) {
        return reviewStatus;
    }

    if (localRequest.value?.status === 'Awaiting Update') {
        return 'Awaiting Update';
    }

    return 'Pending';
});

const scanStatusBadgeClass = computed(() => {
    if (!requiresScan.value) {
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800';
    }

    if (scanStatusLabel.value === 'Approved') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-300 dark:border-emerald-800';
    }

    if (scanStatusLabel.value === 'Rejected') {
        return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800';
    }

    if (scanStatusLabel.value === 'In Review') {
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 border-purple-300 dark:border-purple-800';
    }

    return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-300 dark:border-yellow-800';
});


const getStatusConfirmationMessage = (newStatus: string) => {
    const messages: Record<string, { title: string; description: string; impact: 'high' | 'medium' | 'low'; consequences: string[]; canProceed?: boolean }> = {
        'Claimed': {
            title: 'Claim this request?',
            description: 'You\'re taking responsibility for fulfilling this request.',
            impact: 'medium',
            consequences: requiresScan.value
                ? linkedFiles.value.length === 0
                    ? ['?? No files linked yet - you\'ll need to link records from the system', 'The status will automatically change to "Awaiting Update"', 'You\'ll need to upload scanned documents', 'The requester will be notified']
                    : ['The status will automatically change to "Awaiting Update"', 'You\'ll need to upload scanned documents', 'The requester will be notified']
                : linkedFiles.value.length === 0
                    ? ['?? You must link at least one file from records before claiming', '?? You need to document which records were provided', 'Cannot proceed without linked files']
                    : ['The request will automatically be marked as "Complete"', 'The requester will be notified', 'No further action needed'],
            canProceed: requiresScan.value ? true : linkedFiles.value.length > 0,
        },
        'In Review': {
            title: 'Move to "In Review"?',
            description: 'This indicates that you\'re actively reviewing the uploaded scans.',
            impact: 'low',
            consequences: ['The status will change to "In Review"', 'The requester can see you\'re working on it'],
            canProceed: true,
        },
        'Complete': {
            title: linkedFiles.value.length === 0 ? 'Cannot mark as complete' : 'Mark as complete?',
            description: linkedFiles.value.length === 0
                ? 'You must link at least one file from records before completing this request.'
                : 'This will close the request and notify the requester that it\'s ready.',
            impact: 'high',
            consequences: linkedFiles.value.length === 0
                ? ['?? No files have been linked to this request', '?? You need to document which records were provided', '?? Please link at least one file from records first']
                : requiresScan.value && !hasApprovedScan.value
                    ? ['?? No scans have been approved yet', 'The request will be marked as complete', 'The requester will be notified', 'This action finalizes the request']
                    : ['The request will be marked as complete', 'The requester will be notified', 'This action finalizes the request'],
            canProceed: linkedFiles.value.length > 0,
        },
        'Awaiting Update': {
            title: 'Request an update?',
            description: 'Ask the person handling this request to upload or revise scanned documents.',
            impact: 'medium',
            consequences: ['The status will change to "Awaiting Update"', 'They\'ll be notified to take action'],
            canProceed: true,
        },
        'Pending': {
            title: 'Return to pending?',
            description: 'This will move the request back to the queue as if it hasn\'t been claimed yet.',
            impact: 'medium',
            consequences: ['The status will change to "Pending"', 'Anyone can claim it again', 'Previous work is not lost'],
            canProceed: true,
        },
    };

    return messages[newStatus] || {
        title: `Change status to ${newStatus}?`,
        description: 'Are you sure you want to change the status of this request?',
        impact: 'low' as const,
        consequences: [`The status will change to "${newStatus}"`],
        canProceed: true,
    };
};

const handleStatusSelection = (newStatus: unknown) => {
    if (typeof newStatus !== 'string' || newStatus === '') {
        return;
    }

    statusDraft.value = newStatus;

    // Rejected needs special dialog for notes
    if (newStatus === 'Rejected') {
        pendingStatusChange.value = newStatus;
        showRejectDialog.value = true;
        return;
    }

    // Show confirmation for status changes
    const confirmData = getStatusConfirmationMessage(newStatus);
    statusConfirmMessage.value = confirmData;
    pendingStatusChange.value = newStatus;
    showStatusConfirmDialog.value = true;
};

const handleStatusChange = async (newStatus: string, notes?: string) => {
    if (!props.request) {
        return;
    }

    updatingStatus.value = true;
    try {
        await apiClient.patch(requestsApi.status.url(props.request.id), {
            status: newStatus,
            ...(notes !== undefined ? { notes } : {}),
        });
        status.value = newStatus;
        statusDraft.value = newStatus;
        success(`Status updated to ${newStatus}`);
        await refreshRequest();
    } catch (e: unknown) {
        statusDraft.value = status.value;
        const err = e as { response?: { data?: { message?: string } } };
        error(err.response?.data?.message || 'Failed to update status');
    } finally {
        updatingStatus.value = false;
    }
};

const confirmStatusChange = async (): Promise<void> => {
    if (!pendingStatusChange.value) {
        showStatusConfirmDialog.value = false;
        return;
    }

    await handleStatusChange(pendingStatusChange.value);
    showStatusConfirmDialog.value = false;
};

const submitRejection = async (): Promise<void> => {
    if (!pendingStatusChange.value) {
        showRejectDialog.value = false;
        return;
    }

    const reason = rejectionNotes.value.trim();

    if (reason === '') {
        error('Please provide a reason for rejecting this request.');
        return;
    }

    submittingRejection.value = true;
    try {
        await handleStatusChange(pendingStatusChange.value, reason);
        showRejectDialog.value = false;
    } finally {
        submittingRejection.value = false;
    }
};

const detachFile = async (fileId: string) => {
    if (!props.request) return;

    try {
        await apiClient.delete(requestsApi.detachFile.url([props.request.id, fileId]));
        success('File unlinked successfully');
        await refreshRequest();
    } catch {
        error('Failed to unlink file');
    }
};

// Handle "Will Return Updated Copy" toggle change
const handleRequiresScanToggle = (checked: boolean | 'indeterminate') => {
    if (checked === 'indeterminate') return;

    // Prevent race condition - don't allow toggle while updating
    if (updatingRequiresScan.value) return;

    // Debug: log invocation and value

    console.log('handleRequiresScanToggle called', { checked });

    // Show confirmation dialog
    pendingRequiresScanValue.value = checked;
    showWillReturnCopyConfirmDialog.value = true;
};

const confirmRequiresScanChange = async (): Promise<void> => {
    if (!localRequest.value || pendingRequiresScanValue.value === null) {
        showWillReturnCopyConfirmDialog.value = false;
        return;
    }

    const previousValue = localRequest.value.requires_scan;
    updatingRequiresScan.value = true;
    try {
        const { data } = await apiClient.patch<RequestRecord>(
            requestsApi.update.url(localRequest.value.id),
            { requires_scan: pendingRequiresScanValue.value }
        );

        localRequest.value = data;
        if (viewerIsRequester.value) {
            success(`"Will Return Updated Copy" updated successfully. A Records Officer has been notified.`);
        } else {
            success(`"Will Return Updated Copy" updated successfully. Requestor has been notified.`);
        }

        // Prepare undo: store previous value and show success animation with undo affordance
        undoPreviousValue.value = previousValue;
        requiresScanJustUpdated.value = true;
        // clear any existing timer
        if (undoTimer.value) {
            window.clearTimeout(undoTimer.value);
            undoTimer.value = null;
        }
        // Give user 5 seconds to undo
        undoTimer.value = window.setTimeout(() => {
            requiresScanJustUpdated.value = false;
            undoPreviousValue.value = null;
            undoTimer.value = null;
        }, 5000) as unknown as number;

        await refreshRequest();
    } catch (e: unknown) {
        // Rollback optimistic update on error
        if (localRequest.value) {
            localRequest.value.requires_scan = previousValue;
        }
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const message = err.response?.data?.message || 'Failed to update request';
        error(message);
    } finally {
        updatingRequiresScan.value = false;
        showWillReturnCopyConfirmDialog.value = false;
        pendingRequiresScanValue.value = null;
    }
};

const undoRequiresScanChange = async (): Promise<void> => {
    if (!localRequest.value || undoPreviousValue.value === null) return;

    updatingRequiresScan.value = true;
    try {
        const { data } = await apiClient.patch<RequestRecord>(
            requestsApi.update.url(localRequest.value.id),
            { requires_scan: undoPreviousValue.value }
        );
        localRequest.value = data;
        success('Change reverted');
        // clear undo state
        requiresScanJustUpdated.value = false;
        if (undoTimer.value) {
            window.clearTimeout(undoTimer.value);
            undoTimer.value = null;
        }
        undoPreviousValue.value = null;
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error(err.response?.data?.message || 'Failed to revert change');
    } finally {
        updatingRequiresScan.value = false;
    }
};

const deleteFile = async (fileId: string) => {
    if (!props.request) return;

    try {
        await apiClient.delete(filesApi.destroy.url(fileId));
        success('File deleted successfully');
        await refreshRequest();
    } catch {
        error('Failed to delete file');
    }
};

const removeScan = async (file: FileSchema) => {
    if (!props.request) return;

    const recordId = String(file.record_id ?? '');
    const isDraftScan = recordId.startsWith('SCAN-') && (file.status ?? 'draft') === 'draft';

    if (isDraftScan) {
        try {
            await apiClient.delete(requestsApi.deleteScan.url([props.request.id, file.id]));
            success('Draft upload removed');
            await refreshRequest();
        } catch (e: unknown) {
            const err = e as { response?: { data?: { message?: string } } };
            error(err.response?.data?.message || 'Failed to delete draft scan');
        }

        return;
    }

    await deleteFile(file.id);
};

const previewFile = (file: FileSchema) => {
    // Open file viewer in a new tab/window so the current sheet stays open
    const hasFile = file ? Boolean(file.file_path) : true;
    if (!hasFile) {
        error('No file attached', 'Attach a file to this record before viewing.');
        return;
    }

    const url = `/file/${file.id}/view`;
    try {
        window.open(url, '_blank', 'noopener');
    } catch {
        // Fallback to in-app navigation if window.open is blocked
        openFile(file.id, file, error);
    }
};

const refreshRequest = async () => {
    if (!props.request) return;

    try {
        const response = await apiClient.get<RequestRecord>(requestsApi.show.url(props.request.id));
        localRequest.value = response.data;
        emit('requestUpdated', response.data);
        router.reload({ only: ['requests'] });
    } catch {
        // Silently fail - the UI will still show the old data
        router.reload();
    }
};

const submitScansForReview = async () => {
    if (!props.request || !hasDraftScans.value) {
        showSubmitConfirmDialog.value = false;
        return;
    }

    submittingScans.value = true;

    try {
        await apiClient.post(requestsApi.submitScans.url(props.request.id));
        success('Scans submitted for review', 'Your uploads are now in Import Records awaiting admin approval.');
        showSubmitConfirmDialog.value = false;
        await refreshRequest();
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error(err.response?.data?.message || 'Failed to submit scans for review');
    } finally {
        submittingScans.value = false;
    }
};

const onAttached = async () => {
    await refreshRequest();
};

const onUploaded = async () => {
    await refreshRequest();
};

</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full sm:max-w-105 overflow-y-auto p-0">
            <div v-if="localRequest" class="flex flex-col h-full">
                <!-- Header -->
                <SheetHeader class="px-4 sm:px-6 py-3 sm:py-4 border-b">
                    <SheetTitle class="flex flex-wrap gap-2 text-sm sm:text-base">
                        <div class="text-xs sm:text-sm">REQ-{{ localRequest.request_id?.slice(0, 8) }}</div>
                        <Badge :variant="statusVariant" class="text-[10px] sm:text-xs">{{ status }}</Badge>

                    </SheetTitle>
                </SheetHeader>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4 sm:py-6">
                    <!-- Title Section -->
                    <div class="mb-3">
                        <h2 class="text-base sm:text-lg lg:text-xl font-normal mb-1 break-words">Title: {{ localRequest.request_title }}</h2>
                    </div>

                    <!-- Purpose -->
                    <div class="mb-4 sm:mb-6">
                        <div class="text-xs font-medium text-muted-foreground mb-2">Purpose</div>
                        <p class="text-xs sm:text-sm leading-relaxed break-words">{{ localRequest.purpose }}</p>
                    </div>

                    <!-- Requested by -->
                    <div v-if="requesterName || requesterEmail" class="mb-3">
                        <div class="text-sm font-medium text-muted-foreground mb-1">Requested by</div>
                        <div v-if="requesterName" class="text-xs sm:text-sm text-black dark:text-white break-words">
                            {{ requesterName }}
                        </div>
                        <a v-if="requesterEmail" :href="`mailto:${requesterEmail}`" class="text-xs sm:text-sm text-primary break-words">
                            {{ requesterEmail }}
                        </a>
                    </div>

                    <h1 class="border-t mt-3 pt-3 text-sm font-medium">Other Details</h1>

                    <!-- Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6 mt-3">


                        <!-- <div class="flex items-start gap-3">
                            <Hash class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-muted-foreground mb-0.5">Request ID</div>
                                <div class="text-sm">REQ-{{ localRequest.request_id?.slice(0, 8) }}</div>
                            </div>
                        </div> -->

                        <!-- <div v-if="localRequest.requested_by" class="flex items-start gap-3">
                            <User class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-muted-foreground mb-0.5">Requester</div>
                                <div class="text-sm">{{ localRequest.requested_by.name }}</div>
                            </div>
                        </div> -->

                        <div class="flex items-start gap-2 sm:gap-3">
                            <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Document</div>
                                <div class="text-xs sm:text-sm leading-tight break-words">{{ localRequest.document_type }} # {{
                                    localRequest.file_number || 'N/A' }}</div>
                            </div>
                        </div>

                        <!-- <div class="flex items-start gap-3">
                            <Hash class="h-4 w-4 text-muted-foreground mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-muted-foreground mb-0.5">File Number</div>
                                <div class="text-sm">{{ localRequest.file_number }}</div>
                            </div>
                        </div> -->

                        <div v-if="localRequest.department" class="flex items-start gap-2 sm:gap-3">
                            <Building2 class="h-3.5 w-4.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Division</div>
                                <div class="text-xs sm:text-sm break-words">{{ localRequest.department.name }}</div>
                            </div>
                        </div>

                        <div v-if="localRequest.required_format" class="flex items-start gap-2 sm:gap-3">
                            <FileType class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Required Format</div>
                                <div class="text-xs sm:text-sm break-words">{{ localRequest.required_format }}</div>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <Flag class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Priority</div>
                                <Badge :variant="isUrgent ? 'destructive' : 'default'" class="text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5">
                                    {{ priorityLabel }}
                                </Badge>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <TagIcon class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Categories & Keywords</div>
                                <div v-if="metadataTags.length" class="flex flex-wrap gap-1 sm:gap-1.5">
                                    <Badge v-for="tag in metadataTags" :key="tag.id" variant="outline"
                                        class="text-[10px] sm:text-[11px] border-muted-foreground/40">
                                        {{ tag.name }}
                                    </Badge>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">No tags selected</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <Calendar class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Date Range</div>
                                <div class="text-xs sm:text-sm break-words">{{ formattedDateRange }}</div>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <Clock class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Requested At</div>
                                <div class="text-xs sm:text-sm break-words">{{ formatDate(localRequest.created_at) }}</div>
                            </div>
                        </div>

                        <!-- Will Return Updated Copy Toggle -->
                        <div class="flex items-start gap-2 sm:gap-3 sm:col-span-2 transition-all duration-300"
                            :class="requiresScanJustUpdated ? 'bg-green-50 dark:bg-green-950/20 -mx-3 px-3 py-2 rounded-md' : ''">
                            <!-- Icon changes based on state -->
                            <Loader2 v-if="updatingRequiresScan" class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0 animate-spin text-blue-600 dark:text-blue-400" />
                            <Check v-else-if="requiresScanJustUpdated" class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0 text-green-600 dark:text-green-400 animate-in fade-in zoom-in duration-300" />
                            <CheckCircle v-else class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0"
                                :class="requiresScan ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'" />

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="text-xs font-bold">Will Return Updated Copy</div>
                                    <div class="flex items-center gap-2">
                                        <Badge v-if="requiresScanJustUpdated"
                                            variant="outline"
                                            class="text-[10px] px-1.5 py-0 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-green-300 dark:border-green-700">
                                            Just updated
                                        </Badge>
                                        <Button v-if="requiresScanJustUpdated" size="sm" variant="ghost" class="text-amber-700" :disabled="updatingRequiresScan" @click="undoRequiresScanChange">Undo</Button>
                                    </div>
                                    <Badge v-if="!requiresScanJustUpdated && !canEditRequiresScan && !hasUploadedScan"
                                        variant="outline"
                                        class="text-[10px] px-1.5 py-0">
                                        Read-only
                                    </Badge>
                                </div>
                                <div v-if="canEditRequiresScan" class="flex items-center gap-2">
                                    <div class="relative">
                                        <Checkbox
                                            :id="`requires-scan-${localRequest.id}`"
                                            :model-value="requiresScan"
                                            :disabled="updatingRequiresScan"
                                            @update:modelValue="handleRequiresScanToggle"
                                            :aria-describedby="localRequest ? `requires-scan-help-${localRequest.id}` : undefined"
                                            class="h-4 w-4"
                                            :class="updatingRequiresScan ? 'opacity-50' : ''" />
                                        <Loader2 v-if="updatingRequiresScan" class="absolute right-0 top-0 h-3 w-3 animate-spin text-blue-600" />
                                    </div>
                                    <Label
                                        :for="`requires-scan-${localRequest.id}`"
                                        class="text-xs sm:text-sm"
                                        :class="updatingRequiresScan ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                                        <span v-if="updatingRequiresScan" class="flex items-center gap-2">
                                            <Loader2 class="h-3 w-3 animate-spin" />
                                            Updating...
                                        </span>
                                        <span v-else>
                                            Return Copy
                                        </span>
                                    </Label>
                                </div>
                                <div v-else class="text-xs sm:text-sm">
                                    Return Copy
                                </div>
                                <p v-if="localRequest" :id="`requires-scan-help-${localRequest.id}`" class="text-[11px] text-muted-foreground mt-1">
                                    <template v-if="viewerIsRequester">
                                        Check to say you'll return a copy to the Records Officer.
                                    </template>
                                    <template v-else>
                                        Check to require a returned copy; the requester will be notified.
                                    </template>
                                </p>
                                <p v-if="!canEditRequiresScan && hasUploadedScan"
                                    class="text-[11px] text-muted-foreground mt-1">
                                    Cannot be changed after scans are uploaded
                                </p>
                                <p v-else-if="!canEditRequiresScan && ['Complete', 'Rejected'].includes(localRequest.status)"
                                    class="text-[11px] text-muted-foreground mt-1">
                                    Cannot be changed after request is {{ localRequest.status.toLowerCase() }}
                                </p>
                            </div>
                        </div>
                    </div>



                    <!-- Status Update (if allowed) -->
                    <div v-if="showStatusActions" class="mb-4 sm:mb-6">
                        <label class="text-xs sm:text-sm font-medium mb-2 block">
                            Update Status
                        </label>

                        <!-- Warning: No linked files -->
                        <div v-if="linkedFiles.length === 0 && !requiresScan"
                            class="mb-3 rounded-md bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-900 p-2.5 sm:p-3 flex items-start gap-2">
                            <AlertCircle class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-orange-600 dark:text-orange-400 shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] sm:text-xs font-medium text-orange-800 dark:text-orange-300">Link a file before
                                    completing</p>
                                <p class="text-[11px] sm:text-xs text-orange-700 dark:text-orange-400 mt-0.5">
                                    This request needs at least one linked file from records to show what was provided.
                                </p>
                            </div>
                        </div>

                        <div v-if="linkedFiles.length === 0 && requiresScan"
                            class="mb-3 rounded-md bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900 p-2.5 sm:p-3 flex items-start gap-2">
                            <Info class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] sm:text-xs font-medium text-blue-800 dark:text-blue-300">Reminder</p>
                                <p class="text-[11px] sm:text-xs text-blue-700 dark:text-blue-400 mt-0.5">
                                    Link files from records so scanned uploads know which record to update.
                                </p>
                            </div>
                        </div>

                        <div v-if="linkedFiles.length > 0"
                            class="mb-3 rounded-md bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900 p-2.5 sm:p-3 flex items-start gap-2">
                            <Info class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] sm:text-xs font-medium text-blue-800 dark:text-blue-300">Linked File Limit
                                    Reached</p>
                                <p class="text-[11px] sm:text-xs text-blue-700 dark:text-blue-400 mt-0.5">
                                    Only one linked file is allowed per request.
                                </p>
                            </div>
                        </div>

                        <Select :model-value="statusDraft" @update:model-value="handleStatusSelection">
                            <SelectTrigger class="w-full" :disabled="updatingStatus || disableMutableFields">
                                <SelectValue placeholder="Change status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in statusOptions" :key="option" :value="option">
                                    {{ option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Notes -->
                    <div v-if="localRequest.notes" class="mb-4 sm:mb-6">
                        <div class="text-xs font-medium text-muted-foreground mb-2">Notes</div>
                        <p class="text-xs sm:text-sm leading-relaxed break-words">{{ localRequest.notes }}</p>
                    </div>

                    <!-- Linked Files from Records -->
                    <div v-if="showAttachedFiles" class="border-t pt-4 sm:pt-6">
                        <!-- Header -->
                        <div class="flex flex-col sm:flex-row items-start justify-between gap-3 sm:gap-4 mb-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-xs sm:text-sm font-medium mb-1">Linked Record from CHED ONSE KMIS</h3>
                                <p class="text-[11px] sm:text-xs text-muted-foreground">
                                    Records from the existing CHED ONSE KMIS system
                                </p>
                            </div>
                            <Button size="sm" variant="outline" class="h-8 gap-1.5 shrink-0 w-full sm:w-auto cursor-pointer" :disabled="!canLinkFile"
                                :title="canLinkFile ? undefined : linkDisabledMessage || undefined"
                                @click="showAttachDialog = true">
                                <Paperclip class="h-3.5 w-3.5" />
                                <span class="text-xs sm:text-sm">Link Record</span>
                            </Button>
                        </div>

                        <!-- Status Message when no files linked -->
                        <div v-if="linkedFiles.length === 0"
                            class="mb-4 rounded-lg px-2.5 sm:px-3 py-2 sm:py-2.5 text-[11px] sm:text-xs bg-amber-50 dark:bg-amber-950/20 text-amber-900 dark:text-amber-100">
                            <div class="flex items-start gap-2">
                                <AlertCircle class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <div class="min-w-0">
                                    <p class="font-medium break-words">
                                        {{ requiresScan
                                            ? 'Link a record so scans know which record to update'
                                            : 'Link a record to document what was provided'
                                        }}
                                    </p>
                                    <p class="mt-0.5 opacity-90 break-words">
                                        {{ requiresScan
                                            ? 'Each scan needs to be associated with an existing record.'
                                            : 'Track which records were provided to fulfill this request.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Linked Files List -->
                        <div v-if="linkedFiles.length > 0" class="space-y-2">
                            <div v-for="file in linkedFiles" :key="file.id"
                                class="group relative rounded-lg border p-2.5 sm:p-3 transition-all hover:border-muted-foreground/30 hover:shadow-sm">
                                <div class="flex items-start gap-2 sm:gap-3">
                                    <div class="mt-0.5 rounded-md bg-muted p-1 sm:p-1.5">
                                        <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-primary" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs sm:text-sm font-medium truncate">{{ file.file_name }}</p>
                                        <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                            {{ file.record_id }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-0.5 sm:gap-1">
                                        <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0 cursor-pointer"
                                            :title="file.file_path ? 'Preview' : 'No preview available'"
                                            :disabled="!file.file_path"
                                            @click="file.file_path ? previewFile(file) : undefined">
                                            <Eye class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        </Button>
                                        <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0 cursor-pointer"
                                            @click="openMetadataSheet(file)">
                                            <Info class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        </Button>
                                        <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0 cursor-pointer"
                                            :disabled="disableMutableFields || linkedFileBlockedByScans.has(String(file.id))"
                                            :title="disableMutableFields ? 'Changes disabled � approved scan exists' : (linkedFileBlockedByScans.has(String(file.id)) ? 'Remove uploaded scans first to unlink this record' : undefined)"
                                            @click="(disableMutableFields || linkedFileBlockedByScans.has(String(file.id))) ? undefined : detachFile(file.id)">
                                            <X class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="linkedFiles.length === 0"
                            class="rounded-lg border border-dashed py-6 sm:py-8 text-center mt-4">
                            <div class="mx-auto w-fit rounded-full bg-muted/50 p-2.5 sm:p-3 mb-2">
                                <Paperclip class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground/60" />
                            </div>
                            <p class="text-xs sm:text-sm text-muted-foreground">No Record Linked Yet</p>
                        </div>
                    </div>

                    <!-- Uploaded Scanned Documents -->
                    <div v-if="requiresScan && showScannedDocs" class="border-t pt-4 sm:pt-6">
                        <!-- Header -->
                        <div class="flex flex-col sm:flex-row items-start justify-between gap-3 sm:gap-4 mb-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="text-xs sm:text-sm font-medium">Scanned Record</h3>
                                    <Badge :class="`${scanStatusBadgeClass} text-[10px] px-1.5 py-0`">
                                        {{ scanStatusLabel }}
                                    </Badge>
                                </div>
                                <p class="text-[11px] sm:text-xs text-muted-foreground">
                                    {{ hasDraftScans
                                        ? 'Review and submit when ready'
                                        : 'Required for Request Completion'
                                    }}
                                </p>
                            </div>
                            <Button size="sm" variant="outline" class="h-8 gap-1.5 shrink-0 w-full sm:w-auto" :disabled="!canUploadScan"
                                :title="canUploadScan ? undefined : uploadDisabledMessage || undefined"
                                @click="showUploadDialog = true">
                                <Upload class="h-3.5 w-3.5" />
                                <span class="text-xs sm:text-sm">Upload</span>
                            </Button>
                        </div>

                        <!-- Status Message -->
                        <div v-if="localRequest.scan_review_notes || (requiresScan && linkedFiles.length === 0) || (requiresScan && hasUploadedScan) || (requiresScan && uploadLimitReached) || hasApprovedScan"
                            class="mb-4 rounded-lg px-2.5 sm:px-3 py-2 sm:py-2.5 text-[11px] sm:text-xs" :class="{
                                'bg-destructive/10 text-destructive': localRequest.scan_review_notes,
                                'bg-amber-50 dark:bg-amber-950/20 text-amber-900 dark:text-amber-100': !localRequest.scan_review_notes && ((requiresScan && linkedFiles.length === 0) || (requiresScan && hasUploadedScan) || (requiresScan && uploadLimitReached)),
                                'bg-muted/50 text-muted-foreground': hasApprovedScan && !localRequest.scan_review_notes
                            }">
                            <div v-if="localRequest.scan_review_notes" class="flex items-start gap-2">
                                <AlertCircle class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <div class="min-w-0 break-words">
                                    <span class="font-medium">Feedback: </span>
                                    <span>{{ localRequest.scan_review_notes }}</span>
                                </div>
                            </div>
                            <div v-else-if="requiresScan && linkedFiles.length === 0" class="flex items-start gap-2">
                                <Info class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <span class="break-words">Link at least one record before uploading scans</span>
                            </div>
                            <div v-else-if="requiresScan && hasUploadedScan" class="flex items-start gap-2">
                                <Info class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <span class="break-words">Only one scan allowed per request</span>
                            </div>
                            <div v-else-if="requiresScan && uploadLimitReached" class="flex items-start gap-2">
                                <Info class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <span class="break-words">Pending scan awaiting review</span>
                            </div>
                            <div v-else-if="hasApprovedScan" class="flex items-start gap-2">
                                <Info class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0 mt-0.5" />
                                <span class="break-words">Scan approved - uploads disabled</span>
                            </div>
                        </div>

                        <!-- Draft Uploads -->
                        <div v-if="draftScans.length > 0" class="mb-4">
                            <div class="space-y-3 sm:space-y-4">
                                <div v-for="file in draftScans" :key="file.id"
                                    class="group relative rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/20 p-2.5 sm:p-3 transition-all hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-sm">
                                    <div class="flex items-start gap-2 sm:gap-3">
                                        <div class="mt-0.5 rounded-md bg-blue-100 dark:bg-blue-900/40 p-1 sm:p-1.5">
                                            <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs sm:text-sm font-medium truncate">{{ file.file_name }}</p>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                {{ file.record_id }}
                                            </p>
                                            <p v-if="file.pending_scan_metadata"
                                                class="text-[11px] sm:text-xs text-muted-foreground mt-1 break-words">
                                                 Updates <span class="font-medium">{{
                                                    file.pending_scan_metadata.target_file_name || 'linked record'
                                                    }}</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-0.5 sm:gap-1">
                                            <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0 cursor-pointer"
                                                :title="file.file_path ? 'Preview' : 'No preview available'"
                                                :disabled="!file.file_path" @click="previewFile(file)">
                                                <Eye class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                            </Button>
                                            <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0 cursor-pointer"
                                                @click="removeScan(file)">
                                                <X class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <Button size="lg" variant="default" class="text-xs sm:text-sm w-full gap-1.5"
                                    @click="showSubmitConfirmDialog = true">
                                    Submit for Review
                                </Button>
                            </div>
                        </div>

                        <!-- Submitted Scans -->
                        <div v-if="submittedScans.length > 0" class="mb-4">
                            <span v-if="draftScans.length > 0"
                                class="block text-[10px] sm:text-[11px] font-medium text-muted-foreground uppercase tracking-wide mb-2">Submitted</span>
                            <div class="space-y-2">
                                <div v-for="file in submittedScans" :key="file.id"
                                    class="group relative rounded-lg border p-2.5 sm:p-3 transition-all hover:border-muted-foreground/30 hover:shadow-sm"
                                    :class="{
                                        'border-destructive/30 bg-destructive/5': file.status === 'rejected',
                                        'border-border bg-background': file.status !== 'rejected'
                                    }">
                                    <div class="flex items-start gap-2 sm:gap-3">
                                        <div class="mt-0.5 rounded-md p-1 sm:p-1.5" :class="{
                                            'bg-destructive/10': file.status === 'rejected',
                                            'bg-muted': file.status !== 'rejected'
                                        }">
                                            <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4" :class="{
                                                'text-destructive': file.status === 'rejected',
                                                'text-primary': file.status !== 'rejected'
                                            }" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                                <p class="text-xs sm:text-sm font-medium truncate">{{ file.file_name }}</p>
                                                <Badge v-if="file.status === 'pending'" variant="secondary"
                                                    class="text-[10px] px-1.5 py-0">
                                                    Pending
                                                </Badge>
                                                <Badge v-else-if="file.status === 'rejected'" variant="destructive"
                                                    class="text-[10px] px-1.5 py-0">
                                                    Rejected
                                                </Badge>
                                            </div>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                {{ file.record_id }}
                                            </p>
                                            <p v-if="file.pending_scan_metadata && file.status === 'pending'"
                                                class="text-[11px] sm:text-xs text-muted-foreground mt-1">
                                                In Pending & Unorganized awaiting approval
                                            </p>
                                            <p v-else-if="file.pending_scan_metadata"
                                                class="text-[11px] sm:text-xs text-muted-foreground mt-1 break-words">
                                                ? Updates <span class="font-medium">{{
                                                    file.pending_scan_metadata.target_file_name || 'linked record'
                                                    }}</span>
                                            </p>
                                            <p v-if="file.scan_review_notes"
                                                class="text-[11px] sm:text-xs text-destructive mt-2 flex items-start gap-1">
                                                <AlertCircle class="h-3 w-3 shrink-0 mt-0.5" />
                                                <span class="break-words">{{ file.scan_review_notes }}</span>
                                            </p>
                                        </div>
                                        <div
                                            class="flex items-center gap-0.5 sm:gap-1 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                            <Button size="sm" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8 p-0"
                                                :title="file.file_path ? 'Preview' : 'No preview available'"
                                                :disabled="!file.file_path" @click="previewFile(file)">
                                                <Eye class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                            </Button>
                                            <Button v-if="file.status === 'rejected'" size="sm" variant="ghost"
                                                class="h-7 w-7 sm:h-8 sm:w-8 p-0" @click="removeScan(file)">
                                                <X class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="uploadedScans.length === 0 && !hasApprovedScan"
                            class="rounded-lg border border-dashed py-6 sm:py-8 text-center">
                            <div class="mx-auto w-fit rounded-full bg-muted/50 p-2.5 sm:p-3 mb-2">
                                <Upload class="h-4 w-4 sm:h-5 sm:w-5 text-muted-foreground/60" />
                            </div>
                            <p class="text-xs sm:text-sm text-muted-foreground">No Scans Uploaded Yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <ItemMetadataSheet v-model:open="showMetadataSheet" :item-name="metadataItemName" :has-metadata="hasMetadataEntries"
        :metadata-entries="selectedMetadataEntries" :uploaded-at="selectedUploadedAt"
        :modified-at="selectedModifiedAt" />

    <AttachFileDialog v-if="localRequest" v-model:open="showAttachDialog" :request-id="localRequest.id"
        :blocked-file-ids="linkedFileIds" @attached="onAttached" />

    <UploadScanDialog v-if="localRequest" v-model:open="showUploadDialog" :request-id="localRequest.id"
        :available-records="availableUploadTargets" :blocked-reason="uploadDisabledMessage || undefined"
        @uploaded="onUploaded" />

    <Dialog v-model:open="showRejectDialog">
        <DialogContent :aria-describedby="localRequest ? `reka-dialog-description-${localRequest.id}` : undefined" class="w-[calc(100vw-2rem)] max-w-md mx-4 sm:mx-auto">
            <DialogHeader>
                <DialogTitle class="text-base sm:text-lg">Reject request</DialogTitle>
                <DialogDescription class="text-xs sm:text-sm">
                    Provide a short explanation so the requester knows what to fix.
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-2">
                <Label for="rejection-notes" class="text-xs font-medium text-muted-foreground">
                    Reason for rejection
                </Label>
                <Textarea id="rejection-notes" v-model="rejectionNotes" rows="4"
                    placeholder="Describe why this request cannot proceed." class="text-xs sm:text-sm" />
            </div>
            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button type="button" variant="ghost" class="w-full sm:w-auto" :disabled="submittingRejection" @click="showRejectDialog = false">
                    Cancel
                </Button>
                <Button type="button" variant="destructive" class="w-full sm:w-auto" :disabled="submittingRejection || updatingStatus"
                    @click="submitRejection">
                    {{ submittingRejection ? 'Submitting...' : 'Reject Request' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showSubmitConfirmDialog">
        <DialogContent :aria-describedby="localRequest ? `reka-dialog-description-${localRequest.id}` : undefined" class="w-[calc(100vw-2rem)] max-w-md mx-4 sm:mx-auto">
            <DialogHeader>
                <DialogTitle class="text-base sm:text-lg">Submit Scans for Review?</DialogTitle>
                <DialogDescription :id="localRequest ? `reka-dialog-description-${localRequest.id}` : undefined" class="text-xs sm:text-sm text-pretty break-words">
                    Your uploads will be sent to Import Records for Admin review. Double-check file names, handwriting
                    clarity, and required stamps or seals before submitting.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button type="button" variant="ghost" class="w-full sm:w-auto" :disabled="submittingScans"
                    @click="showSubmitConfirmDialog = false">
                    Back
                </Button>
                <Button type="button" variant="default" class="w-full sm:w-auto" :disabled="submittingScans" @click="submitScansForReview">
                    {{ submittingScans ? 'Submitting...' : 'Submit for Review' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Status Change Confirmation Dialog -->
    <Dialog v-model:open="showStatusConfirmDialog">
        <DialogContent class="w-[calc(100vw-2rem)] max-w-md mx-4 sm:mx-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base sm:text-lg">
                    <AlertCircle v-if="statusConfirmMessage.impact === 'high'" class="h-4 w-4 sm:h-5 sm:w-5 text-orange-500 shrink-0" />
                    <Info v-else-if="statusConfirmMessage.impact === 'medium'" class="h-4 w-4 sm:h-5 sm:w-5 text-blue-500 shrink-0" />
                    <CheckCircle v-else class="h-4 w-4 sm:h-5 sm:w-5 text-gray-500 shrink-0" />
                    <span class="break-words">{{ statusConfirmMessage.title }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs sm:text-sm text-pretty break-words">
                    {{ statusConfirmMessage.description }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 sm:space-y-3">
                <div v-if="statusConfirmMessage.consequences.length > 0" class="rounded-md bg-muted/50 p-2.5 sm:p-3">
                    <p class="text-[11px] sm:text-xs font-medium text-muted-foreground mb-2">What happens next:</p>
                    <ul class="space-y-1 sm:space-y-1.5">
                        <li v-for="(consequence, index) in statusConfirmMessage.consequences" :key="index"
                            class="text-[11px] sm:text-xs flex items-start gap-2">
                            <span class="text-muted-foreground mt-0.5 shrink-0">�</span>
                            <span class="break-words"
                                :class="consequence.startsWith('??') ? 'text-orange-600 dark:text-orange-400 font-medium' : ''">
                                {{ consequence }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button type="button" variant="ghost" class="w-full sm:w-auto" :disabled="updatingStatus"
                    @click="showStatusConfirmDialog = false">
                    {{ statusConfirmMessage.canProceed === false ? 'Go Back' : 'Cancel' }}
                </Button>
                <Button v-if="statusConfirmMessage.canProceed !== false" type="button"
                    :variant="statusConfirmMessage.impact === 'high' ? 'default' : 'default'" class="w-full sm:w-auto" :disabled="updatingStatus"
                    @click="confirmStatusChange">
                    {{ updatingStatus ? 'Updating...' : 'Confirm' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Will Return Updated Copy Confirmation Dialog -->
    <Dialog v-model:open="showWillReturnCopyConfirmDialog">
        <DialogContent class="w-[calc(100vw-2rem)] max-w-md mx-4 sm:mx-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base sm:text-lg">
                    <AlertCircle class="h-4 w-4 sm:h-5 sm:w-5 text-amber-500 shrink-0" />
                    <span class="break-words">{{ pendingRequiresScanValue ? 'Require Scanned Copy?' : 'Remove Scan Requirement?' }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs sm:text-sm text-pretty break-words">
                    <template v-if="viewerIsRequester">
                        {{ pendingRequiresScanValue
                            ? 'This will notify a Records Officer.'
                            : 'This will remove the scan requirement.' }}
                    </template>
                    <template v-else>
                        {{ pendingRequiresScanValue
                            ? 'This will notify the requestor and require scanned documents to be uploaded.'
                            : 'This will remove the scan requirement.' }}
                    </template>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 sm:space-y-3">
                <!-- Current vs New Value -->
                <div class="grid grid-cols-2 gap-2 text-[11px] sm:text-xs">
                    <div class="rounded-md bg-muted/50 p-2">
                        <p class="font-medium text-muted-foreground mb-1">Current:</p>
                        <p class="font-semibold">{{ requiresScan ? 'Will return copy' : 'No copy needed' }}</p>
                    </div>
                    <div class="rounded-md bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 p-2">
                        <p class="font-medium text-amber-700 dark:text-amber-400 mb-1">Changing to:</p>
                        <p class="font-semibold text-amber-900 dark:text-amber-300">{{ pendingRequiresScanValue ? 'Will return copy' : 'No copy needed' }}</p>
                    </div>
                </div>

                <!-- Consequences -->
                <div class="rounded-md bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 p-2.5 sm:p-3">
                    <p class="text-[11px] sm:text-xs font-medium text-amber-800 dark:text-amber-300 mb-2">?? What happens next:</p>
                    <ul class="space-y-1 sm:space-y-1.5">
                        <template v-if="viewerIsRequester">
                            <li class="text-[11px] sm:text-xs flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                <span class="mt-0.5 shrink-0">�</span>
                                <span class="break-words font-medium">A Records Officer will be notified</span>
                            </li>
                        </template>
                        <template v-else>
                            <li class="text-[11px] sm:text-xs flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                <span class="mt-0.5 shrink-0">�</span>
                                <span class="break-words font-medium">Requestor will be immediately notified</span>
                            </li>
                            <li class="text-[11px] sm:text-xs flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                <span class="mt-0.5 shrink-0">�</span>
                                <span class="break-words">
                                    {{ pendingRequiresScanValue
                                        ? 'Scanned documents must be uploaded after linking files'
                                        : 'Scan upload will be disabled for this request' }}
                                </span>
                            </li>
                            <li class="text-[11px] sm:text-xs flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                <span class="mt-0.5 shrink-0">�</span>
                                <span class="break-words">The change will be recorded in the audit trail</span>
                            </li>
                            <li class="text-[11px] sm:text-xs flex items-start gap-2 text-amber-700 dark:text-amber-400">
                                <span class="mt-0.5 shrink-0">�</span>
                                <span class="break-words font-medium">Cannot be changed once scans are uploaded</span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            <DialogFooter class="flex-col sm:flex-row gap-2">
                <Button type="button" variant="ghost" class="w-full sm:w-auto" :disabled="updatingRequiresScan"
                    @click="showWillReturnCopyConfirmDialog = false; pendingRequiresScanValue = null;">
                    Cancel
                </Button>
                <Button type="button" class="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-700" :disabled="updatingRequiresScan"
                    @click="confirmRequiresScanChange">
                    <Loader2 v-if="updatingRequiresScan" class="mr-2 h-4 w-4 animate-spin" />
                    {{ updatingRequiresScan ? 'Updating...' : 'Confirm Change' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
#   T r i g g e r   r e l o a d 
 
