<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    ChevronsUpDownIcon,
    Search,
    Tags,
    Upload,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import DatePicker from '@/components/common/DatePicker.vue';
import TagManagementModal from '@/components/common/TagManagementModal.vue';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxTrigger,
} from '@/components/ui/combobox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { useDuplicateFileResolver } from '@/composables/useDuplicateFileResolver';
import { useRecordModalGuard } from '@/composables/useRecordModalGuard';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import {
    CHUNK_SIZE_BYTES,
    CHUNK_THRESHOLD_BYTES,
    MAX_FILE_BYTES,
} from '@/lib/upload-file-dialog/constants';
import {
    isReleasedBeforeCreated,
    parseShortDateInput,
    toShortDateInput,
} from '@/lib/upload-file-dialog/dateHelpers';
import {
    normalizeFileNameInput,
    resolveUploadMode,
    selectedFileBaseName,
} from '@/lib/upload-file-dialog/fileHelpers';
import { buildEmptyUploadFormData } from '@/lib/upload-file-dialog/formHelpers';
import { mapTagsByIds } from '@/lib/upload-file-dialog/tagHelpers';
import {
    type ComboboxListItem,
    type DateValidationPayload,
    type DepartmentOption,
    type FileUploadFormData,
    type RetentionDuration,
    type RetentionStatus,
    type Tag,
    type UserOption,
} from '@/lib/upload-file-dialog/types';
import { store, update as updateFile } from '@/routes/api/files';
import { type File as FileRecord } from '@/types/schema';
import { formatDate } from '@/utils/format';
import {
    calculateDynamicRetentionStatus,
    calculateRetentionExpiresAt,
    type RetentionDuration as RetentionDurationType,
} from '@/utils/retention';
import { ensureFolderUploadTarget, getDefaultAuthor } from '@/utils/upload';

const { confirm: showConfirm } = useConfirm();
const {
    error: showError,
    success: showSuccess,
    show,
    update,
    remove,
} = useToast();
const { isCreateRecordModalOpen, markOpen, markClosed } = useRecordModalGuard();
const progressToastId = ref<number | null>(null);
const ownsModalGuard = ref(false);

const retentionOptions: Array<{ value: RetentionDuration; label: string }> = [
    {
        value: 'one_year',
        label: '1 Year — Certifications, Permits, Scholarship Applications, Board Exam results',
    },
    {
        value: 'ten_years',
        label: '10 Years — Accounting and finance documents',
    },
    {
        value: 'perpetual',
        label: 'Perpetual — HR files and Outgoing Communications',
    },
];

const props = withDefaults(
    defineProps<{
        folderId?: string | null;
        departments?: DepartmentOption[];
        mode?: 'record' | 'fileOnly';
    }>(),
    { mode: 'record' },
);

const open = defineModel<boolean>('open', { default: false });
const fileInput = ref<HTMLInputElement | null>(null);
const dropZone = ref<HTMLDivElement | null>(null);
const isDragging = ref(false);
const processing = ref(false);
const isUploading = ref(false);
const errors = ref<Record<string, string[]>>({});
const loadingUsers = ref(false);
const internalUsers = ref<UserOption[]>([]);
const selectedFile = ref<File | null>(null);
const allowNavigation = ref(false);
const uploadAbortController = ref<AbortController | null>(null);
const isSettingFileNameProgrammatically = ref(false);
const isSettingRecordIdProgrammatically = ref(false);
const uploadProgress = ref(0);
const uploadStatusText = ref('');
const uploadToken = ref<string | null>(null);
const uploadSessionId = ref<string | null>(null);
const effectiveMode = computed<'record' | 'fileOnly'>(() => {
    return resolveUploadMode(selectedFile.value, props.mode);
});

const acquireModalGuard = (): boolean => {
    if (ownsModalGuard.value) {
        return true;
    }

    if (isCreateRecordModalOpen.value) {
        showError(
            'Create New Record already open',
            'Close or finish the existing Create New Record modal first.',
        );
        return false;
    }

    markOpen();
    ownsModalGuard.value = true;
    return true;
};

const releaseModalGuard = (): void => {
    if (!ownsModalGuard.value) {
        return;
    }

    ownsModalGuard.value = false;
    markClosed();
};

const clearDateValidationErrors = () => {
    dateValidationErrors.value.date_created = '';
    dateValidationErrors.value.date_released = '';
};

const handleDateValidation = (
    field: 'date_created' | 'date_released',
    payload: DateValidationPayload,
) => {
    dateValidationErrors.value[field] = payload.isValid
        ? ''
        : (payload.message ?? 'Enter a valid date.');
};

const resetFormState = () => {
    formData.value = buildEmptyUploadFormData(props.folderId ?? null);
    selectedFile.value = null;
    uploadProgress.value = 0;
    uploadStatusText.value = '';
    uploadToken.value = null;
    uploadSessionId.value = null;
    clearDateValidationErrors();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const formData = ref<FileUploadFormData>(
    buildEmptyUploadFormData(props.folderId ?? null),
);
const dateValidationErrors = ref({ date_created: '', date_released: '' });
const showMetadataTagsModal = ref(false);
const showPhysicalLocationTagsModal = ref(false);
const calculatedRetentionStatus = ref<RetentionStatus>('active');

const allTags = ref<Tag[]>([]);

const loadAllTags = async () => {
    try {
        const response = await apiClient.get<Tag[]>('/api/tags');
        allTags.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error('Failed to load tags:', err);
    }
};

const metadataTags = computed(() => {
    return mapTagsByIds(formData.value.metadata_tag_ids, allTags.value);
});

const physicalLocationTags = computed(() => {
    return mapTagsByIds(
        formData.value.physical_location_tag_ids,
        allTags.value,
    );
});

const chosenFileName = computed(
    () => selectedFile.value?.name ?? formData.value.file_name ?? '',
);

const removeMetadataTag = (tagId: number) => {
    formData.value.metadata_tag_ids = formData.value.metadata_tag_ids.filter(
        (id) => id !== tagId,
    );
};

const removePhysicalLocationTag = (tagId: number) => {
    formData.value.physical_location_tag_ids =
        formData.value.physical_location_tag_ids.filter((id) => id !== tagId);
};

const handleTagCreated = (tag: Tag) => {
    // Add the newly created tag to our local allTags list
    allTags.value.unshift(tag);
};

const submitDisabled = computed(() => {
    if (processing.value) return true;
    if (!formData.value.file_name || !formData.value.author) return true;
    if (effectiveMode.value === 'fileOnly') {
        return selectedFile.value === null;
    }
    return (
        !formData.value.department_id ||
        !formData.value.record_id ||
        !formData.value.date_created ||
        !formData.value.date_released ||
        !formData.value.retention_duration ||
        formData.value.physical_location_tag_ids.length === 0 ||
        Boolean(dateValidationErrors.value.date_created) ||
        Boolean(dateValidationErrors.value.date_released)
    );
});

const page = usePage();
const defaultAuthor = computed(() => getDefaultAuthor(page.props as any));
const availableDepartments = computed<DepartmentOption[]>(() => {
    const fromProps = props.departments ?? [];
    const fromPage =
        ((page.props as any)?.departments as DepartmentOption[] | undefined) ??
        [];

    return fromProps.length > 0 ? fromProps : fromPage;
});

// Convert MM/DD/YY string to DateValue for min date validation
const minDateReleased = computed(() => {
    return parseShortDateInput(formData.value.date_created);
});

// Convert MM/DD/YY string to DateValue for max date validation
const maxDateCreated = computed(() => {
    return parseShortDateInput(formData.value.date_released);
});

const updateRetentionStatus = () => {
    if (
        effectiveMode.value === 'fileOnly' ||
        !formData.value.date_released ||
        !formData.value.retention_duration
    ) {
        calculatedRetentionStatus.value = 'active';
        formData.value.retention_status = 'active';
        return;
    }

    const newStatus = calculateDynamicRetentionStatus(
        formData.value.date_released,
        formData.value.retention_duration as RetentionDurationType,
    );

    calculatedRetentionStatus.value = newStatus;
    formData.value.retention_status = newStatus;
};

const calculatedExpiryDate = computed(() => {
    if (!formData.value.date_released || !formData.value.retention_duration) {
        return null;
    }
    return calculateRetentionExpiresAt(
        formData.value.date_released,
        formData.value.retention_duration as RetentionDurationType,
    );
});

watch(
    () => formData.value.date_created,
    (value) => {
        if (!value) {
            dateValidationErrors.value.date_created = '';
        }
        updateRetentionStatus();
    },
);

watch(
    () => formData.value.date_released,
    (value) => {
        if (!value) {
            dateValidationErrors.value.date_released = '';
        }
        updateRetentionStatus();
    },
);

watch(
    () => formData.value.retention_duration,
    () => {
        updateRetentionStatus();
    },
);

// Validate that date_released is not before date_created
watch(
    [() => formData.value.date_created, () => formData.value.date_released],
    ([dateCreated, dateReleased]) => {
        // Clear any previous date validation errors when dates change
        if (errors.value.date_released?.some((err) => err.includes('before'))) {
            const filtered = errors.value.date_released.filter(
                (err) => !err.includes('before'),
            );
            if (filtered.length === 0) {
                delete errors.value.date_released;
            } else {
                errors.value.date_released = filtered;
            }
        }

        // Only validate if both dates are fully entered (MM/DD/YY = 8 characters)
        if (isReleasedBeforeCreated(dateCreated, dateReleased)) {
            errors.value.date_released = [
                ...(errors.value.date_released || []),
                'Date Released cannot be before Date Created',
            ];
        }
    },
);

watch(
    () => formData.value.record_id,
    (value) => {
        if (isSettingRecordIdProgrammatically.value) {
            return;
        }

        if (!value) {
            return;
        }

        const uppercased = value.toUpperCase();
        if (uppercased === value) {
            return;
        }

        isSettingRecordIdProgrammatically.value = true;
        formData.value.record_id = uppercased;
        isSettingRecordIdProgrammatically.value = false;
    },
);

watch(
    () => formData.value.file_name,
    (value) => {
        if (isSettingFileNameProgrammatically.value) {
            return;
        }

        const normalized = normalizeFileNameInput(value);
        const shouldUppercase = effectiveMode.value === 'record';
        const transformed = shouldUppercase
            ? normalized.toUpperCase()
            : normalized;

        if (transformed === value) {
            return;
        }

        isSettingFileNameProgrammatically.value = true;
        formData.value.file_name = transformed;
        isSettingFileNameProgrammatically.value = false;
    },
);

const uploadReplacementVersion = async (
    file: File,
    existingFile: FileRecord,
): Promise<void> => {
    const payload = new FormData();
    payload.append('_method', 'patch');
    payload.append('file', file);

    try {
        await apiClient.post(updateFile(existingFile.id).url, payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        showSuccess(
            'Version uploaded',
            `"${file.name}" replaced the previous copy.`,
        );
        resetFormState();
        open.value = false;
        router.reload();
    } catch (error: any) {
        const message =
            error.response?.data?.message ||
            error.message ||
            'Failed to replace the existing file.';
        showError('Replace failed', message);
        throw error;
    }
};

const { checkDuplicateFileName } = useDuplicateFileResolver({
    showConfirm,
    showError,
    onReplace: uploadReplacementVersion,
});

const applyFile = async (file: File): Promise<boolean> => {
    delete errors.value.file;

    if (file.size > MAX_FILE_BYTES) {
        showError('File too large', 'Maximum file size is 100MB.');
        errors.value = { file: ['File exceeds the 100MB limit.'] };
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return false;
    }

    const targetFolderId = formData.value.folder_id ?? props.folderId ?? null;
    let effectiveFile = file;

    try {
        const resolution = await checkDuplicateFileName(file, targetFolderId);

        if (resolution?.handled) {
            return false;
        }

        effectiveFile = resolution?.file ?? file;
    } catch {
        return false;
    }

    selectedFile.value = effectiveFile;
    formData.value.mime_type = effectiveFile.type;
    formData.value.file_size = effectiveFile.size;

    isSettingFileNameProgrammatically.value = true;
    formData.value.file_name = normalizeFileNameInput(effectiveFile.name);
    isSettingFileNameProgrammatically.value = false;

    if (!formData.value.author) {
        formData.value.author = defaultAuthor.value;
    }

    return true;
};

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        await applyFile(file);
    }
};

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = true;
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
};

const handleDrop = async (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;

    const file = e.dataTransfer?.files?.[0];
    if (file) {
        await applyFile(file);
    }
};

const filterUsers = (list: ComboboxListItem[], term: string) => {
    if (!term) return list;

    const query = term.toLowerCase();
    return list.filter((item) => {
        const user = internalUsers.value.find((u) => u.name === item.value);
        return user ? user.name.toLowerCase().includes(query) : false;
    });
};

const updateProgressToast = (message: string, progress?: number) => {
    const title =
        progress !== undefined ? `${message} ${progress}% complete` : message;

    if (progressToastId.value !== null) {
        // Update existing toast instead of removing and recreating
        // Use duration 0 to disable auto-dismiss for progress toasts
        update(progressToastId.value, {
            title,
            duration: 0, // Disable auto-dismiss - will be cleared manually
        });
    } else {
        // Create new toast if it doesn't exist
        // Use duration 0 to disable auto-dismiss for progress toasts
        progressToastId.value = show({
            title,
            duration: 0, // Disable auto-dismiss - will be cleared manually
        });
    }
};

const clearProgressToast = () => {
    if (progressToastId.value !== null) {
        remove(progressToastId.value);
        progressToastId.value = null;
    }
};

const abortActiveUpload = (reason?: string) => {
    if (!isUploading.value && !processing.value) {
        return;
    }

    uploadAbortController.value?.abort();
    uploadAbortController.value = null;

    isUploading.value = false;
    processing.value = false;
    allowNavigation.value = true;
    uploadToken.value = null;
    uploadSessionId.value = null;
    uploadProgress.value = 0;
    uploadStatusText.value = '';
    clearProgressToast();

    if (reason) {
        showError('Upload cancelled', reason);
    }
};

type UploadResponse = {
    message?: string;
    record_created?: boolean;
    data?: {
        id?: string;
        is_record?: boolean;
        assembly_status?: 'processing' | 'completed' | 'failed' | null;
        ocr_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    } | null;
};

const uploadFile = async () => {
    if (submitDisabled.value) return;

    const currentPath = window.location.pathname;
    const targetFolderId =
        props.folderId ??
        (currentPath === '/records/pending-unorganized'
            ? null
            : ensureFolderUploadTarget(
                  usePage().props as any,
                  currentPath,
                  showError,
              ));

    if (targetFolderId === undefined) {
        processing.value = false;
        return;
    }

    allowNavigation.value = false;
    isUploading.value = true;
    uploadAbortController.value = new AbortController();
    processing.value = true;
    errors.value = {};
    uploadProgress.value = 0;
    uploadStatusText.value = '';
    clearProgressToast();

    const hasFile = selectedFile.value !== null;

    // Save form data and file reference before closing modal
    const formDataSnapshot = { ...formData.value };
    const fileSnapshot = selectedFile.value;

    console.log('[UploadFileDialog] Upload started', {
        hasFile,
        fileName: fileSnapshot?.name ?? null,
        fileSizeBytes: fileSnapshot?.size ?? null,
        targetFolderId,
        mode: effectiveMode.value,
    });

    // Close modal immediately (optimistic UI) - upload continues in background
    open.value = false;

    // Reset form UI immediately (but keep snapshots for upload)
    resetFormState();

    try {
        // Temporarily restore form data for upload
        const originalFormData = { ...formData.value };
        formData.value = formDataSnapshot;
        selectedFile.value = fileSnapshot;

        // Route to chunked upload for large files
        let uploadResult: UploadResponse | undefined;
        let recordCreated: boolean | undefined;
        let wasChunked = false;
        if (
            hasFile &&
            fileSnapshot &&
            fileSnapshot.size >= CHUNK_THRESHOLD_BYTES
        ) {
            console.log('[UploadFileDialog] Chunked path selected', {
                fileSizeBytes: fileSnapshot.size,
                chunkThresholdBytes: CHUNK_THRESHOLD_BYTES,
            });
            uploadResult = await uploadChunkedFile(
                targetFolderId,
                fileSnapshot,
                uploadAbortController.value?.signal,
            );
            recordCreated = uploadResult?.record_created;
            wasChunked = true;
        } else {
            console.log('[UploadFileDialog] Direct upload path selected', {
                fileSizeBytes: fileSnapshot?.size ?? null,
            });
            uploadResult = await uploadDirectFile(
                targetFolderId,
                hasFile,
                uploadAbortController.value?.signal,
            );
            recordCreated = uploadResult?.record_created;
        }

        // Restore empty form
        formData.value = originalFormData;
        selectedFile.value = null;

        clearProgressToast();
        processing.value = false;
        isUploading.value = false;
        allowNavigation.value = true;
        uploadAbortController.value = null;

        if (hasFile && recordCreated === false) {
            showSuccess(
                'File stored',
                'File stored. No record created (only PDFs and images become records).',
            );
        } else if (wasChunked) {
            showSuccess(
                'Upload queued',
                'Your file will appear shortly while it finishes processing in the background.',
            );
        } else {
            showSuccess(
                hasFile ? 'Upload complete' : 'Record saved',
                hasFile
                    ? 'Your file has been uploaded successfully.'
                    : 'Record created. You can attach a file later.',
            );
        }

        router.reload();
    } catch (error: any) {
        if (error?.code === 'ERR_CANCELED') {
            clearProgressToast();
            processing.value = false;
            isUploading.value = false;
            allowNavigation.value = true;
            uploadAbortController.value = null;
            return;
        }
        const responsePayload = error?.response?.data;
        const validationErrors = responsePayload?.errors as
            | Record<string, string[] | string>
            | undefined;

        console.error('[UploadFileDialog] Upload failed', {
            code: error?.code,
            status: error?.response?.status,
            message: error?.message,
            responseData: responsePayload,
            validationErrors,
        });

        if (responsePayload) {
            console.error(
                '[UploadFileDialog] Upload failed payload JSON',
                JSON.stringify(responsePayload),
            );
        }

        // Restore empty form on error too
        resetFormState();

        clearProgressToast();
        processing.value = false;
        isUploading.value = false;
        allowNavigation.value = true;
        uploadAbortController.value = null;

        let errorMessage = 'Upload failed';
        if (error.response?.status === 413) {
            errorMessage =
                'File is too large for the server. Maximum size is 100MB.';
        } else if (error.code === 'ECONNABORTED') {
            errorMessage =
                'Upload timed out. Please try again or use a smaller file.';
        } else if (error.response?.status === 422 && validationErrors) {
            const firstEntry = Object.entries(validationErrors)[0];
            if (firstEntry) {
                const [field, messages] = firstEntry;
                const firstMessage = Array.isArray(messages)
                    ? messages[0]
                    : messages;
                errorMessage = `${field}: ${firstMessage}`;
            } else {
                errorMessage =
                    error.response?.data?.message ??
                    'Validation failed during upload.';
            }
        } else if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.message) {
            errorMessage = error.message;
        } else if (!error.response || error.code === 'ERR_NETWORK') {
            errorMessage =
                'Upload failed before reaching the server. Check PHP/proxy upload limits and try again.';
        }

        showError('Upload failed', errorMessage);
        router.reload();
    }
};

const uploadDirectFile = async (
    targetFolderId: string | null,
    hasFile: boolean,
    signal?: AbortSignal,
): Promise<UploadResponse> => {
    const payload = new FormData();
    const isFileOnly = effectiveMode.value === 'fileOnly';

    if (hasFile && selectedFile.value) {
        payload.append('file', selectedFile.value);
    }

    if (targetFolderId) {
        payload.append('folder_id', targetFolderId);
    }

    payload.append(
        'author',
        formData.value.author || defaultAuthor.value || '',
    );
    payload.append('file_name', formData.value.file_name);
    payload.append('visibility', formData.value.visibility);
    payload.append('status', formData.value.status);
    payload.append('retention_status', formData.value.retention_status);

    if (isFileOnly) {
        payload.append('upload_mode', 'file_only');
    } else {
        payload.append('department_id', formData.value.department_id);
        payload.append('record_id', formData.value.record_id);
        payload.append('retention_duration', formData.value.retention_duration);
        if (formData.value.date_created) {
            payload.append('date_created', formData.value.date_created);
        }
        if (formData.value.date_released) {
            payload.append('date_released', formData.value.date_released);
        }
    }

    formData.value.metadata_tag_ids.forEach((id) => {
        payload.append('metadata_tag_ids[]', String(id));
    });

    if (!isFileOnly) {
        formData.value.physical_location_tag_ids.forEach((id) => {
            payload.append('physical_location_tag_ids[]', String(id));
        });
    }

    const response = await apiClient.post<UploadResponse>(
        store().url,
        payload,
        {
            headers: { 'Content-Type': 'multipart/form-data' },
            signal,
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    const progressPercent = Math.round(
                        (progressEvent.loaded * 100) / progressEvent.total,
                    );
                    uploadProgress.value = progressPercent;
                    updateProgressToast(
                        'Uploading your file...',
                        progressPercent,
                    );
                }
            },
        },
    );
    return response.data;
};

const uploadChunkedFile = async (
    targetFolderId: string | null,
    fileToUpload: File,
    signal?: AbortSignal,
) => {
    // Snapshot reactive values synchronously before the first await — the open
    // watcher fires resetFormState() after the first yield and would wipe them.
    const isFileOnly = effectiveMode.value === 'fileOnly';
    const snap = { ...formData.value };
    const resolvedFileName =
        snap.file_name || selectedFileBaseName(fileToUpload) || '';
    const allowedRetentionStatuses = new Set([
        'active',
        'ready_for_disposal',
        'disposed',
    ]);
    const normalizedRetentionStatus = allowedRetentionStatuses.has(
        snap.retention_status,
    )
        ? snap.retention_status
        : null;

    updateProgressToast('Getting ready to upload your file...');

    // Get upload token
    const tokenResponse = await apiClient.post<{
        token: string;
        expires_at: string;
    }>('/api/upload/token', undefined, { signal });
    uploadToken.value = tokenResponse.data.token;

    updateProgressToast('Preparing your file for upload...');

    // Calculate total chunks
    const totalChunks = Math.ceil(fileToUpload.size / CHUNK_SIZE_BYTES);

    console.log('[UploadFileDialog] Chunk plan', {
        fileName: fileToUpload.name,
        fileSizeBytes: fileToUpload.size,
        chunkSizeBytes: CHUNK_SIZE_BYTES,
        totalChunks,
    });

    const startPayload: Record<string, unknown> = {
        file_name: resolvedFileName,
        file_size: fileToUpload.size,
        total_chunks: totalChunks,
        author: snap.author || defaultAuthor.value || '',
        visibility: snap.visibility,
        status: snap.status,
        metadata_tag_ids: snap.metadata_tag_ids,
        retention_duration: isFileOnly ? null : snap.retention_duration,
    };

    if (normalizedRetentionStatus !== null) {
        startPayload.retention_status = normalizedRetentionStatus;
    }

    if (targetFolderId) {
        startPayload.folder_id = targetFolderId;
    }

    if (isFileOnly) {
        startPayload.upload_mode = 'file_only';
    } else {
        startPayload.department_id = snap.department_id;
        startPayload.date_created = snap.date_created || null;
        startPayload.date_released = snap.date_released || null;
        startPayload.record_id = snap.record_id;
        startPayload.physical_location_tag_ids = snap.physical_location_tag_ids;
    }

    console.log('[UploadFileDialog] Chunk start payload', startPayload);

    const startResponse = await apiClient.post<{
        upload_session_id: string;
        message: string;
    }>('/api/files/chunked/start', startPayload, {
        headers: {
            Authorization: `Bearer ${uploadToken.value}`,
        },
        signal,
    });

    uploadSessionId.value = startResponse.data.upload_session_id;
    console.log('[UploadFileDialog] Upload session initialized', {
        uploadSessionId: uploadSessionId.value,
        totalChunks,
    });

    // Upload chunks natively
    await uploadChunks(totalChunks, fileToUpload, signal);

    // Complete the upload — the backend pre-creates the File record immediately
    // and returns it in the 202 response. No polling needed.
    updateProgressToast('Finalizing upload...');
    const completeResponse = await apiClient.post<
        UploadResponse & {
            upload_session_id: string;
            data: {
                id: string;
                file_name: string;
                assembly_status: 'processing' | 'completed' | 'failed' | null;
                is_record?: boolean;
            };
        }
    >(
        '/api/files/chunked/complete',
        { upload_session_id: uploadSessionId.value },
        {
            headers: {
                Authorization: `Bearer ${uploadToken.value}`,
            },
            signal,
        },
    );
    clearProgressToast();

    console.log(
        '[UploadFileDialog] Chunk completion accepted — record visible immediately',
        {
            fileId: completeResponse.data.data?.id,
            uploadSessionId: uploadSessionId.value,
        },
    );

    return completeResponse.data;
};

const uploadChunks = async (
    totalChunks: number,
    fileToUpload: File,
    signal?: AbortSignal,
): Promise<void> => {
    if (!uploadToken.value || !uploadSessionId.value) {
        throw new Error('Missing required upload parameters');
    }

    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
        const start = chunkIndex * CHUNK_SIZE_BYTES;
        const end = Math.min(start + CHUNK_SIZE_BYTES, fileToUpload.size);
        const chunkFormData = new FormData();
        chunkFormData.append(
            'file',
            fileToUpload.slice(start, end),
            fileToUpload.name,
        );
        chunkFormData.append('upload_session_id', uploadSessionId.value);
        chunkFormData.append('chunk_index', String(chunkIndex));
        chunkFormData.append('total_chunks', String(totalChunks));

        const maxAttempts = 3;
        let lastErr: unknown;
        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            try {
                await apiClient.post(
                    '/api/files/chunked/upload',
                    chunkFormData,
                    {
                        headers: {
                            Authorization: `Bearer ${uploadToken.value}`,
                        },
                        signal,
                        onUploadProgress: (progressEvent) => {
                            const chunkFraction = progressEvent.total
                                ? progressEvent.loaded / progressEvent.total
                                : 1;
                            const overall = Math.round(
                                ((chunkIndex + chunkFraction) / totalChunks) *
                                    100,
                            );
                            uploadProgress.value = overall;
                            updateProgressToast(
                                'Uploading your file...',
                                overall,
                            );
                        },
                    },
                );
                lastErr = undefined;
                break;
            } catch (err) {
                lastErr = err;
                if (signal?.aborted) {
                    break;
                }
                if (attempt < maxAttempts - 1) {
                    await new Promise((r) =>
                        setTimeout(r, 1000 * (attempt + 1)),
                    );
                }
            }
        }

        if (lastErr) throw lastErr;
    }

    updateProgressToast('Uploading your file...', 100);
};

const prefillFile = async (
    file: File,
    options?: { folderId?: string | null },
) => {
    if (!acquireModalGuard()) {
        return;
    }

    if (options && Object.prototype.hasOwnProperty.call(options, 'folderId')) {
        formData.value.folder_id = options.folderId ?? null;
    }

    const applied = await applyFile(file);

    if (!applied) {
        releaseModalGuard();
        return;
    }

    open.value = true;

    await nextTick();
};

const prefillMetadata = async (
    data: Partial<FileUploadFormData> & { folder_id?: string | null },
) => {
    if (!acquireModalGuard()) {
        return;
    }

    isSettingFileNameProgrammatically.value = true;
    formData.value = {
        ...formData.value,
        ...data,
        folder_id:
            data.folder_id ??
            formData.value.folder_id ??
            props.folderId ??
            null,
        date_created: toShortDateInput(data.date_created ?? ''),
        date_released: toShortDateInput(data.date_released ?? ''),
        metadata_tag_ids: data.metadata_tag_ids ?? [],
        physical_location_tag_ids: data.physical_location_tag_ids ?? [],
        retention_duration:
            (data.retention_duration as RetentionDuration | undefined) ?? '',
    } as FileUploadFormData;
    isSettingFileNameProgrammatically.value = false;

    clearDateValidationErrors();

    selectedFile.value = null;
    formData.value.mime_type = data.mime_type ?? '';
    formData.value.file_size = data.file_size ?? 0;

    open.value = true;

    await nextTick();
};

const loadUsers = async () => {
    if (internalUsers.value.length > 0 || loadingUsers.value) {
        return;
    }

    loadingUsers.value = true;
    try {
        const { data } = await apiClient.get<
            UserOption[] | { data?: UserOption[] }
        >('/api/users');
        internalUsers.value = Array.isArray(data) ? data : (data?.data ?? []);
    } finally {
        loadingUsers.value = false;
    }
};

watch(
    () => props.folderId,
    (value) => {
        formData.value.folder_id = value ?? null;
    },
);

watch(open, (isOpen) => {
    if (isOpen) {
        if (!acquireModalGuard()) {
            open.value = false;
            return;
        }

        loadAllTags();
        return;
    }

    resetFormState();
    releaseModalGuard();
});

const NAV_GUARD_MESSAGE =
    'Your file is still uploading. Leaving now will cancel the upload. Do you want to continue?';

const isNavigationBlocked = () => isUploading.value && !allowNavigation.value;

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!isNavigationBlocked()) return;

    event.preventDefault();
    event.returnValue = NAV_GUARD_MESSAGE;
    return event.returnValue;
};

// Inertia navigation guard for internal navigation (using custom modal)
let removeInertiaListener: (() => void) | null = null;
let pendingNavigation: { url: string; options: any } | null = null;

const handleSidebarNavigationAttempt = (event: any) => {
    const headers = (event.detail.visit.headers || {}) as Record<string, string>;
    const navSource = headers['X-App-Nav'] ?? headers['x-app-nav'];
    const isSidebarNavigation = navSource === 'sidebar';

    if (!isSidebarNavigation) {
        return;
    }

    if (!isNavigationBlocked()) {
        return;
    }

    event.preventDefault();

    if (pendingNavigation) {
        return;
    }

    pendingNavigation = {
        url: event.detail.visit.url.toString(),
        options: event.detail.visit,
    };

    showConfirm({
        title: 'Upload in Progress',
        description: NAV_GUARD_MESSAGE,
        confirmText: 'Continue',
        cancelText: 'Stay on this Page',
    }).then((choice) => {
        if (choice === 'confirm' && pendingNavigation) {
            abortActiveUpload();
            allowNavigation.value = true;
            const nav = pendingNavigation;
            pendingNavigation = null;
            router.visit(nav.url, nav.options);
        } else if (choice === 'cancel') {
            if (pendingNavigation) {
                router.cancel();
                pendingNavigation = null;
            }
            allowNavigation.value = false;
        } else {
            pendingNavigation = null;
            allowNavigation.value = false;
        }
    });
};

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    window.addEventListener('unload', abortActiveUpload);

    // Load users when component mounts
    loadUsers();

    // Intercept Inertia navigation for internal page changes
    removeInertiaListener = router.on('before', handleSidebarNavigationAttempt);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    window.removeEventListener('unload', abortActiveUpload);

    if (removeInertiaListener) {
        removeInertiaListener();
    }

    releaseModalGuard();
});

defineExpose({ prefillFile, prefillMetadata });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    effectiveMode === 'fileOnly'
                        ? 'Upload File'
                        : 'Create New Record'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        effectiveMode === 'fileOnly'
                            ? 'Store a file (e.g. Word, Excel) in this folder. No record will be created.'
                            : 'Capture record details now and attach the file whenever it is ready.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                @submit.prevent="uploadFile"
                class="min-w-0 space-y-4 overflow-x-hidden"
            >
                <div
                    class="grid max-h-[65vh] min-w-0 grid-cols-1 gap-4 overflow-x-hidden overflow-y-auto px-1 py-4"
                >
                    <div class="grid-cols grid min-w-0 gap-2">
                        <Label for="fileName" class="flex items-center gap-1">
                            File Name
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="fileName"
                            v-model="formData.file_name"
                            :disabled="processing"
                            placeholder="Enter File Name"
                            @keydown.enter.prevent
                        />
                        <p
                            v-if="errors.file_name?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.file_name[0] }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="author">Author</Label>
                        <Combobox
                            v-model="formData.author"
                            :disabled="processing || loadingUsers"
                            :filter-function="filterUsers"
                        >
                            <ComboboxAnchor class="w-full">
                                <ComboboxTrigger class="w-full cursor-pointer">
                                    <span
                                        :class="
                                            formData.author
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            formData.author ||
                                            (loadingUsers
                                                ? 'Loading users...'
                                                : 'Select Author...')
                                        }}
                                    </span>
                                    <ChevronsUpDownIcon
                                        class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                    />
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxContent>
                                <div class="flex items-center border-b px-3">
                                    <Search class="h-4 w-4 text-gray-500" />
                                    <ComboboxInput
                                        placeholder="Search Author..."
                                        class="flex h-9 w-full rounded-none border-0 bg-transparent py-3 text-sm shadow-none outline-none placeholder:text-muted-foreground focus-visible:ring-0"
                                    />
                                </div>
                                <ComboboxEmpty>No users found</ComboboxEmpty>

                                <ComboboxItem
                                    v-for="user in internalUsers"
                                    :key="user.id"
                                    :value="user.name"
                                    class="cursor-pointer"
                                >
                                    {{ user.name }}
                                </ComboboxItem>
                            </ComboboxContent>
                        </Combobox>
                        <p
                            v-if="errors.author?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.author[0] }}
                        </p>
                    </div>

                    <div v-if="effectiveMode === 'record'" class="grid gap-2">
                        <Label for="department">Division</Label>
                        <Select
                            v-model="formData.department_id"
                            :disabled="processing"
                        >
                            <SelectTrigger
                                id="department"
                                class="w-full min-w-0 truncate text-left"
                            >
                                <SelectValue
                                    placeholder="Select Division"
                                    class="block truncate"
                                />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectLabel
                                    v-if="availableDepartments.length === 0"
                                    >No Division Available</SelectLabel
                                >
                                <template v-else>
                                    <SelectItem
                                        v-for="dept in availableDepartments"
                                        :key="dept.id"
                                        :value="dept.id"
                                    >
                                        <span
                                            class="block text-left leading-snug break-words whitespace-normal"
                                            >{{ dept.name }}</span
                                        >
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <p
                            v-if="errors.department_id?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.department_id[0] }}
                        </p>
                    </div>

                    <div v-if="effectiveMode === 'record'" class="grid gap-2">
                        <Label for="recordId" class="flex items-center gap-1">
                            Record ID
                            <span class="text-red-500">*</span>
                        </Label>
                        <Input
                            id="recordId"
                            v-model="formData.record_id"
                            :disabled="processing"
                            placeholder="Record Identifier"
                            @keydown.enter.prevent
                        />
                        <p
                            v-if="errors.record_id?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.record_id[0] }}
                        </p>
                    </div>

                    <div v-if="effectiveMode === 'record'" class="grid gap-2">
                        <Label
                            for="dateCreated"
                            class="flex items-center gap-1"
                        >
                            Date Created
                            <span class="text-red-500">*</span>
                        </Label>
                        <DatePicker
                            id="dateCreated"
                            v-model="formData.date_created"
                            :disabled="processing"
                            placeholder="MM/DD/YYYY"
                            :max-value="maxDateCreated"
                            :invalid="
                                Boolean(dateValidationErrors.date_created)
                            "
                            @validation="
                                handleDateValidation('date_created', $event)
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Click calendar icon or type the document's original
                            date
                        </p>
                        <p
                            v-if="dateValidationErrors.date_created"
                            class="text-sm text-red-500"
                        >
                            {{ dateValidationErrors.date_created }}
                        </p>
                        <p
                            v-if="errors.date_created?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.date_created[0] }}
                        </p>
                    </div>

                    <div v-if="effectiveMode === 'record'" class="grid gap-2">
                        <Label
                            for="dateReleased"
                            class="flex items-center gap-1"
                        >
                            Date Released
                            <span class="text-red-500">*</span>
                        </Label>
                        <DatePicker
                            id="dateReleased"
                            v-model="formData.date_released"
                            :disabled="processing"
                            placeholder="MM/DD/YYYY"
                            :min-value="minDateReleased"
                            :invalid="
                                Boolean(dateValidationErrors.date_released)
                            "
                            @validation="
                                handleDateValidation('date_released', $event)
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Click calendar icon or type when the record was
                            processed/released
                        </p>
                        <p
                            v-if="dateValidationErrors.date_released"
                            class="text-sm text-red-500"
                        >
                            {{ dateValidationErrors.date_released }}
                        </p>
                        <p
                            v-if="errors.date_released?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.date_released[0] }}
                        </p>
                    </div>

                    <Alert
                        v-if="
                            effectiveMode === 'record' &&
                            formData.date_released &&
                            formData.retention_duration &&
                            calculatedRetentionStatus === 'ready_for_disposal'
                        "
                        variant="default"
                        class="border-amber-500/50 bg-amber-50 dark:bg-amber-950/20"
                    >
                        <AlertTriangle
                            class="h-4 w-4 text-amber-600 dark:text-amber-500"
                        />
                        <AlertTitle class="text-amber-900 dark:text-amber-100"
                            >Retention Period Exceeded</AlertTitle
                        >
                        <AlertDescription
                            class="text-amber-800 dark:text-amber-200"
                        >
                            <p class="mb-2">
                                This record has already passed its retention
                                period and will be marked as
                                <strong class="font-semibold"
                                    >Ready for Disposal</strong
                                >.
                            </p>
                            <p v-if="calculatedExpiryDate" class="text-sm">
                                <strong>Retention expired:</strong>
                                {{ formatDate(calculatedExpiryDate) }}
                            </p>
                            <p class="mt-1 text-sm">
                                This record will immediately appear in the
                                disposal queue and can be processed for archival
                                or removal.
                            </p>
                        </AlertDescription>
                    </Alert>

                    <div v-if="mode === 'record'" class="grid gap-2">
                        <Label
                            for="retentionDuration"
                            class="flex items-center gap-1"
                        >
                            Retention Period
                            <span class="text-red-500">*</span>
                        </Label>
                        <Select
                            v-model="formData.retention_duration"
                            :disabled="processing"
                        >
                            <SelectTrigger
                                id="retentionDuration"
                                class="w-full min-w-0 cursor-pointer truncate text-left"
                            >
                                <SelectValue
                                    placeholder="Select Retention Period"
                                    class="block truncate"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectLabel
                                    v-if="retentionOptions.length === 0"
                                    >No options available</SelectLabel
                                >
                                <template v-else>
                                    <SelectItem
                                        v-for="option in retentionOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        <span
                                            class="block text-left leading-snug break-words whitespace-normal"
                                        >
                                            {{ option.label }}
                                        </span>
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            This selection determines when the physical copy
                            enters the disposal queue after the Date Released.
                        </p>
                        <p
                            v-if="
                                calculatedExpiryDate &&
                                formData.date_released &&
                                formData.retention_duration
                            "
                            class="text-xs text-blue-600 dark:text-blue-400"
                        >
                            <strong>Status:</strong>
                            {{
                                calculatedRetentionStatus ===
                                'ready_for_disposal'
                                    ? 'Ready for Disposal'
                                    : 'Active'
                            }}
                            &mdash; <strong>Expires:</strong>
                            {{ formatDate(calculatedExpiryDate) }}
                        </p>
                        <p
                            v-if="errors.retention_duration?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.retention_duration[0] }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="metadataTags">Categories & Keywords</Label>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full cursor-pointer justify-start"
                            :disabled="processing"
                            @click="showMetadataTagsModal = true"
                        >
                            <Tags class="mr-2 h-4 w-4" />
                            {{
                                metadataTags.length > 0
                                    ? `${metadataTags.length} selected`
                                    : 'Select Categories & Keywords'
                            }}
                        </Button>
                        <div
                            v-if="metadataTags.length > 0"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="tag in metadataTags"
                                :key="tag.id"
                                variant="secondary"
                                class="flex items-center gap-1 px-2 py-1"
                            >
                                <span>{{ tag.name }}</span>
                                <Button
                                    v-if="!processing"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-4 w-4 cursor-pointer p-0 hover:bg-transparent"
                                    @click="removeMetadataTag(tag.id)"
                                >
                                    <X class="h-3 w-3" />
                                </Button>
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Add categories, topics, or keywords to help organize
                            and find this record.
                        </p>
                        <p
                            v-if="errors.metadata_tag_ids?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.metadata_tag_ids[0] }}
                        </p>
                    </div>

                    <div v-if="effectiveMode === 'record'" class="grid gap-2">
                        <Label
                            for="physicalLocationTags"
                            class="flex items-center gap-1"
                        >
                            Physical Location
                            <span class="text-red-500">*</span>
                        </Label>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full cursor-pointer justify-start"
                            :disabled="processing"
                            @click="showPhysicalLocationTagsModal = true"
                        >
                            <Tags class="mr-2 h-4 w-4" />
                            {{
                                physicalLocationTags.length > 0
                                    ? `${physicalLocationTags.length} selected`
                                    : 'Select Physical Location'
                            }}
                        </Button>
                        <div
                            v-if="physicalLocationTags.length > 0"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="tag in physicalLocationTags"
                                :key="tag.id"
                                variant="secondary"
                                class="flex items-center gap-1 px-2 py-1"
                            >
                                <span>{{ tag.name }}</span>
                                <Button
                                    v-if="!processing"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-4 w-4 cursor-pointer p-0 hover:bg-transparent"
                                    @click="removePhysicalLocationTag(tag.id)"
                                >
                                    <X class="h-3 w-3" />
                                </Button>
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Add at least one tag (Shelf 2, Box A, Cabinet B,
                            etc.). The selected tags are saved as the record's
                            physical location.
                        </p>
                        <p
                            v-if="errors.physical_location_tag_ids?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.physical_location_tag_ids[0] }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="file" class="flex items-center gap-2">
                            <span>Select File</span>
                            <span
                                v-if="effectiveMode === 'record'"
                                class="text-xs font-normal text-muted-foreground"
                                >(Optional - can be added later)</span
                            >
                        </Label>
                        <div
                            ref="dropZone"
                            data-upload-drop-zone
                            :class="[
                                'cursor-pointer rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                                isDragging
                                    ? 'border-primary bg-primary/5'
                                    : 'border-muted-foreground/25',
                            ]"
                            @dragover="handleDragOver"
                            @dragleave="handleDragLeave"
                            @drop="handleDrop"
                            @click="fileInput?.click()"
                        >
                            <Upload
                                class="mx-auto mb-4 h-12 w-12 text-muted-foreground"
                            />
                            <p class="mb-2 text-sm text-muted-foreground">
                                {{
                                    effectiveMode === 'fileOnly'
                                        ? 'Drag and drop your file here, or click to browse.'
                                        : 'Drag and drop your file here, or click to browse. You can skip this and upload later.'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    effectiveMode === 'fileOnly'
                                        ? 'Use Create New Record for PDFs and images.'
                                        : 'PDFs and images become records; other file types are stored only.'
                                }}
                            </p>
                            <input
                                id="file"
                                ref="fileInput"
                                type="file"
                                @change="handleFileChange"
                                :disabled="processing"
                                class="hidden"
                            />
                            <div
                                v-if="chosenFileName"
                                class="mt-4 flex justify-center"
                            >
                                <div
                                    class="inline-flex max-w-full items-start gap-2 rounded-md bg-muted px-3 py-2 text-sm font-medium"
                                >
                                    <Check
                                        v-if="selectedFile"
                                        class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                                    />
                                    <span
                                        class="min-w-0 break-all text-foreground"
                                        >{{ chosenFileName }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="errors.file?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.file[0] }}
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="processing"
                        class="cursor-pointer"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="submitDisabled"
                        class="cursor-pointer"
                    >
                        {{
                            effectiveMode === 'fileOnly'
                                ? 'Upload'
                                : selectedFile
                                  ? 'Upload'
                                  : 'Save Record'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Tag Management Modals -->
    <TagManagementModal
        v-model:open="showMetadataTagsModal"
        v-model:selected-tag-ids="formData.metadata_tag_ids"
        type="metadata"
        title="Categories & Keywords"
        description="Select existing tags or create new categories and keywords to help organize this record."
        :allow-create="true"
        @tag-created="handleTagCreated"
    />

    <TagManagementModal
        v-model:open="showPhysicalLocationTagsModal"
        v-model:selected-tag-ids="formData.physical_location_tag_ids"
        type="physical_location"
        title="Physical Location Tags"
        description="Select or create tags for the physical location (e.g., Shelf 2, Box A, Cabinet B)."
        :allow-create="true"
        @tag-created="handleTagCreated"
    />
</template>
