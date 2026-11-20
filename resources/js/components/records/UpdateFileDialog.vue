<script setup lang="ts">
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import {
    isReleasedBeforeCreated,
    parseShortDateInput,
    toShortDateInput,
} from '@/lib/upload-file-dialog/dateHelpers';
import {
    normalizeFileNameInput as normalizeBaseName,
    selectedFileBaseName as selectedFileBaseNameHelper,
} from '@/lib/upload-file-dialog/fileHelpers';
import { update as updateFile } from '@/routes/api/files';
import { formatDate } from '@/utils/format';
import {
    calculateDynamicRetentionStatus,
    calculateRetentionExpiresAt,
    type RetentionDuration as RetentionDurationType,
} from '@/utils/retention';
import { buildRecordId, getDefaultAuthor } from '@/utils/upload';

const { confirm: showConfirm } = useConfirm();
const { error: showError, success: showSuccess } = useToast();

const MAX_FILE_BYTES = 100 * 1024 * 1024;

interface DepartmentOption {
    id: string | number;
    name: string;
}

interface UserOption {
    id: string;
    name: string;
}

interface ExistingFileMetadata {
    id: string;
    is_record?: boolean;
    author?: string;
    department_id?: string;
    record_id?: string;
    date_created?: string;
    date_released?: string;
    retention_duration?: RetentionDuration | null;
    retention_status?: RetentionStatus | null;
    physical_location?: string;
    file_name?: string;
    mime_type?: string | null;
    file_size?: number | null;
    folder_id?: string | null;
    visibility?: 'public' | 'restricted' | 'private';
    status?: 'pending' | 'approved' | 'rejected';
    metadata_tag_ids?: number[];
    physical_location_tag_ids?: number[];
}

interface FileUploadFormData {
    author: string;
    department_id: string;
    date_created: string;
    date_released: string;
    record_id: string;
    file_name: string;
    mime_type: string;
    file_size: number;
    folder_id: string | null;
    visibility: 'public' | 'restricted' | 'private';
    status: 'pending' | 'approved' | 'rejected';
    metadata_tag_ids: number[];
    physical_location_tag_ids: number[];
    retention_duration: '' | RetentionDuration;
    retention_status: RetentionStatus;
}

type ComboboxListItem = { value: string };

interface DateValidationPayload {
    isValid: boolean;
    message?: string;
}

type RetentionDuration = 'one_year' | 'ten_years' | 'perpetual';

type RetentionStatus =
    | 'active'
    | 'archived'
    | 'ready_for_disposal'
    | 'disposed';

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

const retentionStatusOptions: Array<{ value: RetentionStatus; label: string }> =
    [
        {
            value: 'active',
            label: 'Active — accessible and within retention window',
        },
        { value: 'archived', label: 'Archived — read-only reference copy' },
        {
            value: 'ready_for_disposal',
            label: 'Ready for Disposal — awaiting final review',
        },
        {
            value: 'disposed',
            label: 'Disposed — confirmed destroyed or returned',
        },
    ];

const props = defineProps<{
    folderId?: string | null;
    departments?: DepartmentOption[];
    fileId?: string | null;
}>();

const open = defineModel<boolean>('open', { default: false });
const fileInput = ref<HTMLInputElement | null>(null);
const showMetadataTagsModal = ref(false);
const showPhysicalLocationTagsModal = ref(false);
const dropZone = ref<HTMLDivElement | null>(null);
const isDragging = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string[]>>({});
const loadingUsers = ref(false);
const internalUsers = ref<UserOption[]>([]);
const selectedFile = ref<File | null>(null);
const hasExistingFile = ref(false);
const normalizeFileNameInput = (value?: string | null): string =>
    normalizeBaseName(value);
const selectedFileBaseName = (): string =>
    selectedFileBaseNameHelper(selectedFile.value ?? null);
const requiredFileName = ref<string>('');
const targetFileId = ref<string | null>(props.fileId ?? null);
const isFileOnlyMode = ref(false);
const allowNavigation = ref(false);
const isSettingFileNameProgrammatically = ref(false);
const isSettingRecordIdProgrammatically = ref(false);
const buildEmptyFormData = (): FileUploadFormData => ({
    author: '',
    department_id: '',
    date_created: '',
    date_released: '',
    record_id: '',
    file_name: '',
    mime_type: '',
    file_size: 0,
    folder_id: props.folderId || null,
    visibility: 'public',
    status: 'approved',
    metadata_tag_ids: [],
    physical_location_tag_ids: [],
    retention_duration: '',
    retention_status: 'active',
});

const formData = ref<FileUploadFormData>(buildEmptyFormData());
const dateValidationErrors = ref({ date_created: '', date_released: '' });
const showRetentionWarning = ref(false);
const previousRetentionStatus = ref<RetentionStatus>('active');
const calculatedRetentionStatus = ref<RetentionStatus>('active');

interface Tag {
    id: number;
    name: string;
}

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
    return formData.value.metadata_tag_ids
        .map((id) => allTags.value.find((tag) => tag.id === id))
        .filter((tag): tag is Tag => tag !== undefined);
});

const physicalLocationTags = computed(() => {
    return formData.value.physical_location_tag_ids
        .map((id) => allTags.value.find((tag) => tag.id === id))
        .filter((tag): tag is Tag => tag !== undefined);
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
    if (
        processing.value ||
        !targetFileId.value ||
        !formData.value.file_name ||
        !formData.value.author
    ) {
        return true;
    }
    if (isFileOnlyMode.value) {
        return false;
    }
    return (
        !formData.value.department_id ||
        !formData.value.record_id ||
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

const minDateReleased = computed(() => {
    return parseShortDateInput(formData.value.date_created);
});

const maxDateCreated = computed(() => {
    return parseShortDateInput(formData.value.date_released);
});

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
    formData.value = buildEmptyFormData();
    selectedFile.value = null;
    hasExistingFile.value = false;
    isFileOnlyMode.value = false;
    clearDateValidationErrors();
    showRetentionWarning.value = false;
    previousRetentionStatus.value = 'active';
    calculatedRetentionStatus.value = 'active';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

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

const updateRetentionStatus = () => {
    if (
        isFileOnlyMode.value ||
        !formData.value.date_released ||
        !formData.value.retention_duration
    ) {
        showRetentionWarning.value = false;
        return;
    }

    const newStatus = calculateDynamicRetentionStatus(
        formData.value.date_released,
        formData.value.retention_duration as RetentionDurationType,
    );

    calculatedRetentionStatus.value = newStatus;
    formData.value.retention_status = newStatus;

    // Show warning if the retention status has changed
    if (newStatus !== previousRetentionStatus.value && targetFileId.value) {
        showRetentionWarning.value = true;
    } else if (!targetFileId.value) {
        // For new records, just set the status without warning
        showRetentionWarning.value = false;
    } else {
        showRetentionWarning.value = false;
    }
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
    [() => formData.value.date_created, () => formData.value.date_released],
    ([dateCreated, dateReleased]) => {
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

        if (isReleasedBeforeCreated(dateCreated, dateReleased)) {
            errors.value.date_released = [
                'Date Released cannot be before Date Created',
            ];
        }
    },
);

watch(
    () => formData.value.file_name,
    (value) => {
        if (isSettingFileNameProgrammatically.value) {
            return;
        }

        const normalized = normalizeFileNameInput(value);
        const uppercased = normalized.toUpperCase();

        if (uppercased === value) {
            return;
        }

        isSettingFileNameProgrammatically.value = true;
        formData.value.file_name = uppercased;
        isSettingFileNameProgrammatically.value = false;
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

const applyFile = (file: File) => {
    delete errors.value.file;

    if (file.size > MAX_FILE_BYTES) {
        showError('File too large', 'Maximum file size is 100MB.');
        errors.value = { file: ['File exceeds the 100MB limit.'] };
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return;
    }

    const expectedName = requiredFileName.value || formData.value.file_name;
    const normalizedExpected = normalizeFileNameInput(expectedName);
    const incomingBase = selectedFileBaseNameHelper(file);
    if (
        normalizedExpected &&
        incomingBase.toLowerCase() !== normalizedExpected.toLowerCase()
    ) {
        const message = `Rename your file to "${normalizedExpected}" before uploading a new version.`;
        errors.value = { file: [message] };
        showError('File name mismatch', message);
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return;
    }

    selectedFile.value = file;
    formData.value.mime_type = file.type;
    formData.value.file_size = file.size;

    if (!formData.value.record_id) {
        isSettingRecordIdProgrammatically.value = true;
        formData.value.record_id = buildRecordId(file.name).toUpperCase();
        isSettingRecordIdProgrammatically.value = false;
    }

    if (!formData.value.file_name) {
        isSettingFileNameProgrammatically.value = true;
        formData.value.file_name = normalizeFileNameInput(
            file.name,
        ).toUpperCase();
        isSettingFileNameProgrammatically.value = false;
    }

    if (!formData.value.author) {
        formData.value.author = defaultAuthor.value;
    }
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        applyFile(file);
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

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;

    const file = e.dataTransfer?.files?.[0];
    if (file) {
        applyFile(file);
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

const submitUpdate = async () => {
    if (!targetFileId.value) {
        showError(
            'No record selected',
            'Select a record to update before submitting.',
        );
        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        const payload = new FormData();
        payload.append('_method', 'patch');

        const hadSelectedFile = Boolean(selectedFile.value);

        if (selectedFile.value) {
            payload.append('file', selectedFile.value);
        }

        if (formData.value.folder_id) {
            payload.append('folder_id', formData.value.folder_id);
        }

        payload.append(
            'author',
            formData.value.author || defaultAuthor.value || '',
        );
        payload.append('file_name', formData.value.file_name);
        payload.append('visibility', formData.value.visibility);
        payload.append('status', formData.value.status);
        payload.append('retention_status', formData.value.retention_status);

        if (isFileOnlyMode.value) {
            payload.append('upload_mode', 'file_only');
        } else {
            payload.append('department_id', formData.value.department_id);
            payload.append(
                'record_id',
                formData.value.record_id ||
                    buildRecordId(
                        formData.value.file_name ||
                            selectedFileBaseName() ||
                            'record',
                    ),
            );
            payload.append(
                'retention_duration',
                formData.value.retention_duration,
            );
            if (formData.value.date_created) {
                payload.append('date_created', formData.value.date_created);
            }
            if (formData.value.date_released) {
                payload.append('date_released', formData.value.date_released);
            }
        }

        if (formData.value.mime_type) {
            payload.append('mime_type', formData.value.mime_type);
        }
        if (formData.value.file_size) {
            payload.append('file_size', String(formData.value.file_size));
        }

        formData.value.metadata_tag_ids.forEach((id) => {
            payload.append('metadata_tag_ids[]', String(id));
        });

        if (!isFileOnlyMode.value) {
            formData.value.physical_location_tag_ids.forEach((id) => {
                payload.append('physical_location_tag_ids[]', String(id));
            });
        }

        await apiClient.post(updateFile(targetFileId.value).url, payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        resetFormState();
        targetFileId.value = null;
        requiredFileName.value = '';

        open.value = false;
        allowNavigation.value = true;
        router.reload();
        showSuccess(
            hadSelectedFile ? 'File updated' : 'Record updated',
            hadSelectedFile
                ? 'The file has been attached to this record.'
                : 'The record metadata was updated.',
        );
    } catch (error: any) {
        if (error.response?.status === 413) {
            errors.value = {
                file: [
                    'File is too large for the server. Maximum size is 100MB.',
                ],
            };
            return;
        }

        if (error.code === 'ECONNABORTED') {
            errors.value = {
                file: [
                    'Upload timed out. Please try again or use a smaller file.',
                ],
            };
            return;
        }

        if (!error.response || error.code === 'ERR_NETWORK') {
            errors.value = {
                file: [
                    'Upload failed before reaching the server. Check PHP/proxy upload limits and try again.',
                ],
            };
            return;
        }

        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            errors.value = {
                file: [error.response?.data?.message || 'Update failed'],
            };
        }
    } finally {
        processing.value = false;
    }
};

const prefillFile = async (
    file: File,
    options?: { folderId?: string | null; fileId?: string },
) => {
    if (options && Object.prototype.hasOwnProperty.call(options, 'folderId')) {
        formData.value.folder_id = options.folderId ?? null;
    }

    if (options?.fileId) {
        targetFileId.value = options.fileId;
    }

    applyFile(file);
    await loadAllTags();
    open.value = true;

    await nextTick();
};

const prefillMetadata = async (
    data: Partial<FileUploadFormData> & Partial<ExistingFileMetadata>,
) => {
    targetFileId.value = data.id ?? targetFileId.value;
    isFileOnlyMode.value = data.is_record === false;
    const incomingName = data.file_name ?? '';
    requiredFileName.value = normalizeFileNameInput(incomingName);

    // Load all tags so we can display them
    await loadAllTags();

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
        mime_type: data.mime_type ?? formData.value.mime_type,
        file_size: data.file_size ?? formData.value.file_size,
        file_name: data.file_name ?? formData.value.file_name,
        metadata_tag_ids: data.metadata_tag_ids ?? [],
        physical_location_tag_ids: data.physical_location_tag_ids ?? [],
        retention_duration:
            (data.retention_duration as RetentionDuration | undefined) ?? '',
        retention_status:
            (data.retention_status as RetentionStatus | undefined) ??
            formData.value.retention_status ??
            'active',
    } as FileUploadFormData;
    if (data.file_name) {
        isSettingFileNameProgrammatically.value = true;
        formData.value.file_name = normalizeFileNameInput(
            data.file_name,
        ).toUpperCase();
        isSettingFileNameProgrammatically.value = false;
    }
    if (data.record_id) {
        isSettingRecordIdProgrammatically.value = true;
        formData.value.record_id = data.record_id.toUpperCase();
        isSettingRecordIdProgrammatically.value = false;
    }
    clearDateValidationErrors();

    // Store the original retention status
    previousRetentionStatus.value = formData.value.retention_status;
    calculatedRetentionStatus.value = formData.value.retention_status;
    showRetentionWarning.value = false;

    selectedFile.value = null;
    hasExistingFile.value = Boolean(
        data.mime_type || (data.file_size && data.file_size > 0),
    );
    formData.value.mime_type = data.mime_type ?? formData.value.mime_type;
    formData.value.file_size = data.file_size ?? formData.value.file_size;

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
    if (!isOpen) {
        resetFormState();
        targetFileId.value = null;
        requiredFileName.value = '';
    }
});

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (processing.value && !allowNavigation.value) {
        event.preventDefault();
        event.returnValue =
            'An update is in progress. Are you sure you want to leave?';
        return event.returnValue;
    }
};

let removeInertiaListener: (() => void) | null = null;
let pendingNavigation: { url: string; options: any } | null = null;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);

    loadUsers();

    removeInertiaListener = router.on('before', (event) => {
        if (processing.value && !allowNavigation.value && !pendingNavigation) {
            event.preventDefault();

            pendingNavigation = {
                url: event.detail.visit.url.toString(),
                options: event.detail.visit,
            };

            showConfirm({
                title: 'Update in Progress',
                description:
                    'You have an update in progress. Leaving this page will cancel it. Continue?',
                confirmText: 'Leave Page',
                cancelText: 'Stay',
            }).then((confirmed) => {
                if (confirmed && pendingNavigation) {
                    processing.value = false;
                    open.value = false;
                    const nav = pendingNavigation;
                    pendingNavigation = null;
                    router.visit(nav.url);
                } else {
                    pendingNavigation = null;
                }
            });
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);

    if (removeInertiaListener) {
        removeInertiaListener();
    }
});

defineExpose({ prefillFile, prefillMetadata });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    isFileOnlyMode ? 'Update File' : 'Update Record'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        isFileOnlyMode
                            ? 'Update file details. No record will be created or changed.'
                            : 'Attach or replace the file for this record without creating a new one.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <form
                @submit.prevent="submitUpdate"
                class="min-w-0 space-y-4 overflow-x-hidden"
            >
                <div
                    class="grid max-h-[65vh] min-w-0 grid-cols-1 gap-4 overflow-x-hidden overflow-y-auto px-1 py-4"
                >
                    <div class="grid gap-2">
                        <Label for="fileName">File Name</Label>
                        <Input
                            id="fileName"
                            v-model="formData.file_name"
                            :disabled="processing"
                            class="w-full min-w-0 truncate"
                            placeholder="Enter file name"
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
                                    @click="formData.author = user.name"
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

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
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
                                    >No divisions available</SelectLabel
                                >
                                <template v-else>
                                    <SelectItem
                                        v-for="dept in availableDepartments"
                                        :key="dept.id"
                                        :value="dept.id"
                                    >
                                        <span
                                            class="block text-left leading-snug wrap-break-word whitespace-normal"
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

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
                        <Label for="recordId">Record ID</Label>
                        <Input
                            id="recordId"
                            v-model="formData.record_id"
                            :disabled="processing"
                            placeholder="Record identifier"
                        />
                        <p
                            v-if="errors.record_id?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.record_id[0] }}
                        </p>
                    </div>

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
                        <Label for="dateCreated">Date Created</Label>
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

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
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
                        v-if="!isFileOnlyMode && showRetentionWarning"
                        variant="default"
                        class="border-amber-500/50 bg-amber-50 dark:bg-amber-950/20"
                    >
                        <AlertTriangle
                            class="h-4 w-4 text-amber-600 dark:text-amber-500"
                        />
                        <AlertTitle class="text-amber-900 dark:text-amber-100"
                            >Retention Status Will Change</AlertTitle
                        >
                        <AlertDescription
                            class="text-amber-800 dark:text-amber-200"
                        >
                            <p class="mb-2">
                                Based on the date released and retention period,
                                this record's status will be updated to
                                <strong class="font-semibold">{{
                                    calculatedRetentionStatus ===
                                    'ready_for_disposal'
                                        ? 'Ready for Disposal'
                                        : 'Active'
                                }}</strong
                                >.
                            </p>
                            <p v-if="calculatedExpiryDate" class="text-sm">
                                <strong>Retention Expires:</strong>
                                {{ formatDate(calculatedExpiryDate) }}
                            </p>
                            <p class="mt-1 text-sm">
                                {{
                                    calculatedRetentionStatus ===
                                    'ready_for_disposal'
                                        ? 'This record has passed its retention period and will appear in the disposal queue.'
                                        : 'This record is within its retention period and will remain active.'
                                }}
                            </p>
                        </AlertDescription>
                    </Alert>

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
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
                                            class="block text-left leading-snug wrap-break-word whitespace-normal"
                                        >
                                            {{ option.label }}
                                        </span>
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            Updates to this field adjust the disposal timer that
                            mirrors the physical archive.
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
                            @click="showMetadataTagsModal = true"
                            :disabled="processing"
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
                                    class="h-4 w-4 p-0 hover:bg-transparent"
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

                    <div v-if="!isFileOnlyMode" class="grid gap-2">
                        <Label
                            for="physicalLocationTags"
                            class="flex items-center gap-1"
                        >
                            Physical Location Tags
                            <span class="text-red-500">*</span>
                        </Label>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full cursor-pointer justify-start"
                            @click="showPhysicalLocationTagsModal = true"
                            :disabled="processing"
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
                            Add at least one tag like Shelf 2, Box A, Cabinet B,
                            etc. These tags determine the stored physical
                            location.
                        </p>
                        <p
                            v-if="errors.physical_location_tag_ids?.length"
                            class="text-sm text-red-500"
                        >
                            {{ errors.physical_location_tag_ids[0] }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="file">Select File</Label>
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
                                Drag and drop your file here, or click to
                                browse.
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
                                class="inline-flex max-w-full items-start gap-2 rounded-md bg-muted px-3 py-2 text-sm font-medium"
                            >
                                <Check
                                    v-if="selectedFile || hasExistingFile"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                                />
                                <span
                                    class="min-w-0 break-all text-foreground"
                                    >{{ chosenFileName }}</span
                                >
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
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="submitDisabled">
                        {{ selectedFile ? 'Upload' : 'Save Changes' }}
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
