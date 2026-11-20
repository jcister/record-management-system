<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileUp } from 'lucide-vue-next';
import { computed, ref, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import RecordsLayout from '@/layouts/RecordsLayout.vue';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';
import RecordsSortBar from '@/components/records/RecordsSortBar.vue';
import FolderTile from '@/components/records/FolderTile.vue';
import FolderListItem from '@/components/records/FolderListItem.vue';
import FileTile from '@/components/records/FileTile.vue';
import FileListItem from '@/components/records/FileListItem.vue';
import FileTileSkeleton from '@/components/records/FileTileSkeleton.vue';
import FileListItemSkeleton from '@/components/records/FileListItemSkeleton.vue';
import UploadFileDialog from '@/components/records/UploadFileDialog.vue';
import UpdateFileDialog from '@/components/records/UpdateFileDialog.vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/utils/permission';
import { useSelectionState } from '@/utils/selection';
import type { SelectionIdentity } from '@/utils/selection';
import { type BreadcrumbItem, type SelectedItemType } from '@/types';
import { buildRecordId } from '@/utils/upload';
import { type Department, type File, type FolderSchema } from '@/types/schema';
import { buildOrderedItems } from '@/utils/itemOrdering';
import { buildSelectedContexts } from '@/utils/selectionContext';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useSelectionHelpers } from '@/composables/useSelectionHelpers';
import { useViewMode } from '@/composables/useViewMode';
import { openFolder as navigateToFolder, openFile as navigateToFile } from '@/utils/itemNavigation';
import { buildFileMetadata } from '@/utils/fileAttachment';
import type { RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import { sortFiles } from '@/utils/sorting';
import { formatRequestCode } from '@/utils/format';
import { apiClient } from '@/lib/axios';
import requestsApi from '@/routes/api/requests';
import requestsRoutes from '@/routes/requests';

const props = defineProps<{
    folders: FolderSchema[];
    files: File[];
    departments: Department[];
}>();

const { isTouchDevice } = usePointerCapabilities();
const { success, error } = useToast();
const { confirm: showConfirm } = useConfirm();
const { can } = usePermissions();
const { viewMode, setViewMode } = useViewMode();
const pendingViewMode = ref(viewMode.value);
const page = usePage();
const isLoading = ref(true);
const importSortField = ref<SortField>('name');
const importSortDirection = ref<SortDirection>('asc');
const recordsPlacement = ref<RecordsPlacement>('top');
const canReviewContent = computed(() => can('reviewContent'));
const approvingFileId = ref<string | null>(null);
const rejectingFile = ref<File | null>(null);
const showRejectDialog = ref(false);
const rejectReason = ref('');
const submittingRejection = ref(false);

const orderedPendingItems = computed<SelectionIdentity[]>(() =>
    buildOrderedItems([], sortedPendingFiles.value)
);

const orderedRecordItems = computed<SelectionIdentity[]>(() =>
    buildOrderedItems(props.folders, recordsFiles.value)
);

const pendingSelection = useSelectionState(() => orderedPendingItems.value);
const recordSelection = useSelectionState(() => orderedRecordItems.value);

const {
    selectMode: pendingSelectMode,
    selectedItems: pendingSelectedItems,
    selectionCount: pendingSelectionCount,
    toggleSelectMode: togglePendingSelectMode,
    toggleSelection: togglePendingSelectionIdentity,
    isSelected: isPendingIdentitySelected,
    clearSelection: clearPendingSelection,
    selectOnly: selectOnlyPending,
    lastSelected: lastPendingSelected,
} = pendingSelection;

const {
    selectMode: recordSelectMode,
    selectedItems: recordSelectedItems,
    selectionCount: recordSelectionCount,
    toggleSelectMode: toggleRecordSelectMode,
    toggleSelection: toggleRecordSelectionIdentity,
    isSelected: isRecordIdentitySelected,
    clearSelection: clearRecordSelection,
    selectOnly: selectOnlyRecord,
    lastSelected: lastRecordSelected,
} = recordSelection;

const activeSelectMode = computed(() => pendingSelectMode.value || recordSelectMode.value);
const activeSelectionCount = computed(() => pendingSelectionCount.value || recordSelectionCount.value);
const activeSelectedContexts = computed<SelectedItemType[]>(() => {
    if (pendingSelectionCount.value > 0 || pendingSelectMode.value) {
        return pendingSelectedItems.value.length ? buildSelectedContexts({
            selections: pendingSelectedItems.value,
            folders: [],
            files: sortedPendingFiles.value,
            currentFolderId: null,
        }) : [];
    }

    if (recordSelectionCount.value > 0 || recordSelectMode.value) {
        return buildSelectedContexts({
            selections: recordSelectedItems.value,
            folders: props.folders,
            files: recordsFiles.value,
            currentFolderId: null,
        });
    }

    return [];
});

const showUploadDialog = ref(false);
const updateDialogRef = ref<InstanceType<typeof UpdateFileDialog> | null>(null);
const isReloading = ref(false);
let eventSource: EventSource | null = null;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pending & Unorganized',
        description: 'Review and organize recently uploaded files before they are added to All Records.',
        href: '/records/pending-unorganized',
    },
];

const openFolder = (folderId: string) => {
    navigateToFolder(folderId, '/records');
};

const openFile = (fileId: string) => {
    const file = props.files.find(f => f.id === fileId);
    navigateToFile(fileId, file, error);
};

const previewFile = (file: File) => {
    navigateToFile(file.id, file, error);
};

const pendingSelectionHelpers = useSelectionHelpers({
    isIdentitySelected: isPendingIdentitySelected,
    selectOnly: selectOnlyPending,
    selectedItems: pendingSelectedItems,
    selectMode: pendingSelectMode,
    lastSelected: lastPendingSelected,
    selectionCount: pendingSelectionCount,
    toggleSelectionIdentity: togglePendingSelectionIdentity,
    toggleSelectMode: togglePendingSelectMode,
    isTouchDevice,
    openFolder,
    openFile,
});

const recordSelectionHelpers = useSelectionHelpers({
    isIdentitySelected: isRecordIdentitySelected,
    selectOnly: selectOnlyRecord,
    selectedItems: recordSelectedItems,
    selectMode: recordSelectMode,
    lastSelected: lastRecordSelected,
    selectionCount: recordSelectionCount,
    toggleSelectionIdentity: toggleRecordSelectionIdentity,
    toggleSelectMode: toggleRecordSelectMode,
    isTouchDevice,
    openFolder,
    openFile,
});

const pendingSelectedContexts = computed<SelectedItemType[]>(() =>
    buildSelectedContexts({
        selections: pendingSelectedItems.value,
        folders: [],
        files: sortedPendingFiles.value,
        currentFolderId: null,
    })
);

const recordSelectedContexts = computed<SelectedItemType[]>(() =>
    buildSelectedContexts({
        selections: recordSelectedItems.value,
        folders: props.folders,
        files: recordsFiles.value,
        currentFolderId: null,
    })
);

const scannedFiles = computed<File[]>(() =>
    props.files.filter(file => Boolean(file.pending_scan_metadata))
);

const restoredFiles = computed<File[]>(() =>
    props.files.filter(file => !file.pending_scan_metadata && Boolean(file.restored_at))
);

const otherFiles = computed<File[]>(() =>
    props.files.filter(file => !file.pending_scan_metadata && !file.restored_at)
);

const recordsFiles = computed<File[]>(() => [
    ...restoredFiles.value,
    ...otherFiles.value,
]);

const showFileMetaColumns = computed(() => viewMode.value === 'list');

const sortedPendingFiles = computed<File[]>(() =>
    sortFiles(scannedFiles.value, importSortField.value, importSortDirection.value, recordsPlacement.value)
);

const sortedRecordsFiles = computed<File[]>(() =>
    sortFiles(recordsFiles.value, importSortField.value, importSortDirection.value, recordsPlacement.value)
);

const hasProcessingFiles = computed(() =>
    props.files.some(file =>
        file.assembly_status === 'processing'
        || file.ocr_status === 'pending'
        || file.ocr_status === 'processing'
    )
);
const highlightedFileId = computed(() => {
    const [, queryString] = page.url.split('?');

    if (!queryString) {
        return null;
    }

    const params = new URLSearchParams(queryString);

    return params.get('highlight');
});

const isFileHighlighted = (fileId: string): boolean =>
    Boolean(highlightedFileId.value && highlightedFileId.value === fileId);

const highlightedFile = computed<File | null>(() => {
    if (!highlightedFileId.value) {
        return null;
    }

    return props.files.find(file => file.id === highlightedFileId.value) ?? null;
});

const highlightedFileLabel = computed(() => highlightedFile.value?.file_name ?? null);

const scrollToHighlightedFile = (fileId: string): void => {
    if (typeof window === 'undefined') {
        return;
    }

    nextTick(() => {
        const element = document.querySelector<HTMLElement>(`[data-file-id="${fileId}"]`);
        element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
};

watch(highlightedFileId, (newId) => {
    if (!newId) {
        return;
    }

    scrollToHighlightedFile(newId);
}, { immediate: true });

const clearHighlightedFile = (): void => {
    if (!highlightedFileId.value) {
        return;
    }

    const [path, queryString] = page.url.split('?');
    const params = new URLSearchParams(queryString ?? '');

    if (!params.has('highlight')) {
        return;
    }

    params.delete('highlight');
    const nextUrl = params.toString() ? `${path}?${params.toString()}` : path;

    router.visit(nextUrl || '/records/pending-unorganized', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const fileCountLabel = computed(() => {
    const count = props.files.length;
    const noun = count === 1 ? 'File' : 'Files';

    return `${count} ${noun}`;
});

const reloadFiles = (): void => {
    if (isReloading.value || (typeof document !== 'undefined' && document.hidden)) {
        return;
    }

    isReloading.value = true;

    router.reload({
        only: ['files'],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isReloading.value = false;
        },
    });
};

const closeEventSource = (): void => {
    if (eventSource !== null) {
        eventSource.close();
        eventSource = null;
    }
};

const openEventSource = (): void => {
    closeEventSource();

    eventSource = new EventSource('/api/sse/records/pending-unorganized/updates');

    eventSource.onmessage = () => {
        reloadFiles();
    };

    eventSource.onerror = () => {
        if (!hasProcessingFiles.value) {
            closeEventSource();
        }
    };
};

watch(
    hasProcessingFiles,
    (value) => {
        if (value) {
            openEventSource();
        } else {
            closeEventSource();
        }
    },
    { immediate: true },
);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});

onBeforeUnmount(() => {
    closeEventSource();
});

const handleAttachFile = (file: File) => {
    if (!updateDialogRef.value) return;

    updateDialogRef.value.prefillMetadata({
        ...buildFileMetadata(file),
        record_id: file.record_id || buildRecordId(file.file_name),
        physical_location: file.physical_location || '',
        author: file.author || '',
        folder_id: null,
        visibility: file.visibility || 'public',
        status: (file.status === 'pending' || file.status === 'approved' || file.status === 'rejected') ? file.status : 'approved',
    });
};


    const {
        isSelected: isPendingSelected,
        handleFileClick: pendingFileClick,
        handleLongPress: pendingLongPress,
    } = pendingSelectionHelpers;

    const {
        isSelected: isRecordSelected,
        handleFolderClick: recordFolderClick,
        handleFileClick: recordFileClick,
        handleLongPress: recordLongPress,
    } = recordSelectionHelpers;

    const clearAllSelections = (): void => {
        clearPendingSelection();
        clearRecordSelection();
    };

    const beginPendingSelection = (): void => {
        clearRecordSelection();
    };

    const beginRecordSelection = (): void => {
        clearPendingSelection();
    };

    const handlePendingFileClick = (id: string, event?: Event): void => {
        beginPendingSelection();
        pendingFileClick(id, event);
    };

    const handlePendingLongPress = (id: string, type: 'file' | 'folder'): void => {
        beginPendingSelection();
        pendingLongPress(id, type);
    };

    const handleRecordFolderClick = (id: string, event?: Event): void => {
        beginRecordSelection();
        recordFolderClick(id, event);
    };

    const handleRecordFileClick = (id: string, event?: Event): void => {
        beginRecordSelection();
        recordFileClick(id, event);
    };

    const handleRecordLongPress = (id: string, type: 'file' | 'folder'): void => {
        beginRecordSelection();
        recordLongPress(id, type);
    };

    const togglePendingSelect = (): void => {
        beginPendingSelection();
        togglePendingSelectMode();
    };

    const toggleRecordSelect = (): void => {
        beginRecordSelection();
        toggleRecordSelectMode();
    };

    const handleToggleSelect = (): void => {
        if (pendingSelectMode.value || pendingSelectionCount.value > 0) {
            togglePendingSelect();
            return;
        }

        toggleRecordSelect();
    };
const isApproving = (fileId: string): boolean => approvingFileId.value === fileId;

const approvePendingScan = async (file: File) => {
    if (!file.pending_scan_metadata) {
        return;
    }

    const targetName = file.pending_scan_metadata.target_file_name ?? 'the linked record';
    const confirmed = await showConfirm({
        title: 'Approve scanned update?',
        description: `This will publish the upload as the newest version of ${targetName} and queue OCR.`,
        confirmText: 'Approve Scan',
    });

    if (confirmed !== 'confirm') {
        return;
    }

    approvingFileId.value = file.id;

    try {
        await apiClient.post(
            requestsApi.approveScan.url([
                file.pending_scan_metadata.request_id,
                file.id,
            ]),
        );

        success('Scan approved. The target record now has a new version.');
        router.reload({ only: ['files'] });
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error('Failed to approve scan', message ?? 'Please try again.');
    } finally {
        approvingFileId.value = null;
    }
};

const buildRequestHighlightLink = (requestNumber?: string | null): string | null => {
    if (!requestNumber) {
        return null;
    }

    return requestsRoutes.index.url({
        query: { highlight: requestNumber },
    });
};

const rejectingRequestCode = computed(() => {
    const meta = rejectingFile.value?.pending_scan_metadata;

    if (!meta) {
        return null;
    }

    return formatRequestCode(meta.request_number, meta.request_id);
});

const rejectingRequestLink = computed(() => {
    const meta = rejectingFile.value?.pending_scan_metadata;

    if (!meta) {
        return null;
    }

    return buildRequestHighlightLink(meta.request_number);
});

const rejectingTargetName = computed(() => {
    return rejectingFile.value?.pending_scan_metadata?.target_file_name ?? 'the linked record';
});

const openRejectDialog = (file: File) => {
    if (!file.pending_scan_metadata) {
        return;
    }

    rejectingFile.value = file;
    rejectReason.value = '';
    showRejectDialog.value = true;
};

const closeRejectDialog = () => {
    showRejectDialog.value = false;
    rejectingFile.value = null;
    rejectReason.value = '';
};

const submitReject = async () => {
    if (!rejectingFile.value?.pending_scan_metadata) {
        closeRejectDialog();

        return;
    }

    const reason = rejectReason.value.trim();

    if (!reason) {
        error('Please describe why the scan is being rejected.');

        return;
    }

    submittingRejection.value = true;

    try {
        await apiClient.post(
            requestsApi.rejectScan.url([
                rejectingFile.value.pending_scan_metadata.request_id,
                rejectingFile.value.id,
            ]),
            { reason }
        );

        success('Scan rejected. The requester has been asked for a new upload.');
        closeRejectDialog();
        router.reload({ only: ['files'] });
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error('Failed to reject scan', message ?? 'Please try again.');
    } finally {
        submittingRejection.value = false;
    }
};


const { hasPermission } = usePermissions();



</script>

<template>
    <Head title="Pending & Unorganized" />

    <RecordsLayout :breadcrumbs="breadcrumbs" :selections="activeSelectedContexts"
        :selection-count="activeSelectionCount" :select-mode="activeSelectMode" @toggle-select="handleToggleSelect">
        <div class="flex h-full flex-1 flex-col gap-6 p-6" @click="clearAllSelections">
            <p class="text-muted-foreground">{{ fileCountLabel }}</p>
            <div v-if="highlightedFileLabel"
                class="flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 shadow-sm dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-100 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    File <span class="font-semibold">{{ highlightedFileLabel }}</span> is currently highlighted.
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="self-start text-blue-900 hover:bg-blue-100 dark:text-blue-100 dark:hover:bg-blue-900/60 sm:self-auto"
                    @click="clearHighlightedFile"
                >
                    Clear highlight
                </Button>
            </div>

            <!-- Loading State -->
            <template v-if="isLoading">
                <div class="space-y-3">
                    <RecordsSortBar
                        :view-mode="viewMode"
                        :sort-field="importSortField"
                        :sort-direction="importSortDirection"
                        :show-grouping="false"
                        :show-actions-column="false"
                        :show-date-column="showFileMetaColumns"
                        :show-size-column="showFileMetaColumns"
                        :show-status-column="showFileMetaColumns"
                        :show-version-column="showFileMetaColumns"
                        :show-type-option="false"
                        :show-records-placement="true"
                        :records-placement="recordsPlacement"
                        :selections="recordSelectedContexts"
                        :selection-count="recordSelectionCount"
                        @update:view-mode="setViewMode"
                        @update:sort-field="importSortField = $event"
                        @update:sort-direction="importSortDirection = $event"
                        @update:records-placement="recordsPlacement = $event"
                        @toggle-select="toggleRecordSelect"
                    />
                </div>

                <ViewModeContainer :view-mode="viewMode">
                    <template #tiles>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                            <FileTileSkeleton v-for="i in 8" :key="`file-skeleton-${i}`" />
                        </div>
                    </template>

                    <template #list>
                        <div class="w-full">
                            <div>
                                <FileListItemSkeleton v-for="i in 8" :key="`file-list-skeleton-${i}`" />
                            </div>
                        </div>
                    </template>
                </ViewModeContainer>
            </template>

            <!-- Loaded Content -->
            <template v-else>
                <div v-if="folders.length > 0">
                    <h2 class="text-lg font-semibold mb-3">Folders</h2>
                    <RecordsSortBar
                        :view-mode="viewMode"
                        :sort-field="importSortField"
                        :sort-direction="importSortDirection"
                        :show-grouping="false"
                        :show-actions-column="false"
                        :show-date-column="false"
                        :show-size-column="false"
                        :show-version-column="false"
                        :show-type-option="false"
                        :show-size-option="false"
                        @update:view-mode="setViewMode"
                        @update:sort-field="importSortField = $event"
                        @update:sort-direction="importSortDirection = $event"
                    />
                    <ViewModeContainer :view-mode="viewMode">
                        <template #tiles>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                <FolderTile v-for="folder in folders" :key="folder.id" :folder="folder"
                                    :is-selected="isRecordSelected(folder.id, 'folder')" :current-folder-id="null"
                                    :selected-items="recordSelectedContexts" :selection-count="recordSelectionCount"
                                    :draggable="false" :show-actions="false"
                                    @click="(e) => handleRecordFolderClick(folder.id, e)"
                                    @dblclick="openFolder(folder.id)"
                                    @longpress="() => handleRecordLongPress(folder.id, 'folder')" />
                            </div>
                        </template>

                        <template #list>
                            <div class="w-full">
                                <div>
                                        <FolderListItem v-for="folder in folders" :key="folder.id" :folder="folder"
                                        :is-selected="isRecordSelected(folder.id, 'folder')" :current-folder-id="null"
                                        :selected-items="recordSelectedContexts" :selection-count="recordSelectionCount"
                                        :draggable="false" :show-actions="false"
                                        @click="(e) => handleRecordFolderClick(folder.id, e)"
                                        @dblclick="openFolder(folder.id)"
                                        @longpress="() => handleRecordLongPress(folder.id, 'folder')" />
                                </div>
                            </div>
                        </template>
                    </ViewModeContainer>
                </div>

                <div v-if="files.length > 0" class="space-y-10">
                    <div v-if="sortedPendingFiles.length > 0 && hasPermission('review-content')" class="space-y-3">
                            <RecordsSortBar
                                :view-mode="pendingViewMode"
                                :sort-field="importSortField"
                                :sort-direction="importSortDirection"
                                :show-grouping="false"
                                :show-actions-column="false"
                                :show-date-column="false"
                                :show-size-column="false"
                                :show-status-column="false"
                                :show-version-column="false"
                                :show-version-label="false"
                                :show-type-option="false"
                                :show-size-option="false"
                                :selections="pendingSelectedContexts"
                                :selection-count="pendingSelectionCount"
                                @update:view-mode="pendingViewMode = $event"
                                @update:sort-field="importSortField = $event"
                                @update:sort-direction="importSortDirection = $event"
                                @toggle-select="togglePendingSelect"
                            />
                        <p class="text-sm font-semibold text-muted-foreground">Pending</p>
                        <ViewModeContainer :view-mode="pendingViewMode">
                            <template #tiles>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                    <FileTile v-for="file in sortedPendingFiles" :key="file.id" :file="file"
                                        :is-selected="isPendingSelected(file.id, 'file')"
                                        :is-highlighted="isFileHighlighted(file.id)"
                                        :current-folder-id="null"
                                        :current-folder-name="null"
                                        :show-actions="false"
                                        :show-bookmark="false"
                                        :show-status="false"
                                        :show-pending-actions="canReviewContent"
                                        :pending-request-link="buildRequestHighlightLink(file.pending_scan_metadata?.request_number)"
                                        :is-approving-pending-scan="isApproving(file.id)"
                                        :selected-items="pendingSelectedContexts" :selection-count="pendingSelectionCount"
                                        @click="(e) => handlePendingFileClick(file.id, e)" @dblclick="openFile(file.id)"
                                        @longpress="() => handlePendingLongPress(file.id, 'file')"
                                        @attachfile="handleAttachFile(file)"
                                        @preview-pending="previewFile($event)"
                                        @approve-pending="approvePendingScan($event)"
                                        @reject-pending="openRejectDialog($event)"
                                    />
                                </div>
                            </template>

                            <template #list>
                                <div class="w-full">
                                    <div>
                                        <FileListItem v-for="file in sortedPendingFiles" :key="file.id" :file="file"
                                            :is-selected="isPendingSelected(file.id, 'file')"
                                            :is-highlighted="isFileHighlighted(file.id)"
                                            :current-folder-id="null"
                                            :current-folder-name="null"
                                            :show-pending-actions="canReviewContent"
                                            :pending-request-link="buildRequestHighlightLink(file.pending_scan_metadata?.request_number)"
                                            :is-approving-pending-scan="isApproving(file.id)"
                                            :show-actions="false"
                                            :show-version="false"
                                            :show-date="false"
                                            :show-size="false"
                                            :show-bookmark="false"
                                            :show-status="false"
                                            :selected-items="pendingSelectedContexts" :selection-count="pendingSelectionCount"
                                            @click="(e) => handlePendingFileClick(file.id, e)" @dblclick="openFile(file.id)"
                                            @longpress="() => handlePendingLongPress(file.id, 'file')"
                                            @attachfile="handleAttachFile(file)"
                                            @preview-pending="previewFile($event)"
                                            @approve-pending="approvePendingScan($event)"
                                            @reject-pending="openRejectDialog($event)"
                                        />
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>

                    <div v-if="sortedRecordsFiles.length > 0 && hasPermission('organize-content')" class="space-y-3">
                        <RecordsSortBar
                            :view-mode="viewMode"
                            :sort-field="importSortField"
                            :sort-direction="importSortDirection"
                            :show-grouping="false"
                            :show-actions-column="false"
                            :show-date-column="showFileMetaColumns"
                            :show-size-column="showFileMetaColumns"
                            :show-status-column="showFileMetaColumns"
                            :show-version-column="showFileMetaColumns"
                            :show-type-option="false"
                            :show-records-placement="true"
                            :records-placement="recordsPlacement"
                            :selections="recordSelectedContexts"
                            :selection-count="recordSelectionCount"
                            @update:view-mode="setViewMode"
                            @update:sort-field="importSortField = $event"
                            @update:sort-direction="importSortDirection = $event"
                            @update:records-placement="recordsPlacement = $event"
                            @toggle-select="toggleRecordSelectMode"
                        />
                        <p class="text-sm font-semibold text-muted-foreground">Unorganized Records</p>
                        <ViewModeContainer :view-mode="viewMode">
                            <template #tiles>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                        <FileTile v-for="file in sortedRecordsFiles" :key="file.id" :file="file"
                                        :is-selected="isRecordSelected(file.id, 'file')"
                                        :is-highlighted="isFileHighlighted(file.id)"
                                        :current-folder-id="null"
                                        :current-folder-name="null"
                                        :selected-items="recordSelectedContexts" :selection-count="recordSelectionCount"
                                        @click="(e) => handleRecordFileClick(file.id, e)" @dblclick="openFile(file.id)"
                                        @longpress="() => handleRecordLongPress(file.id, 'file')"
                                        @attachfile="handleAttachFile(file)"
                                    />
                                </div>
                            </template>

                            <template #list>
                                <div class="w-full">
                                    <div>
                                        <FileListItem v-for="file in sortedRecordsFiles" :key="file.id" :file="file"
                                            :is-selected="isRecordSelected(file.id, 'file')"
                                            :is-highlighted="isFileHighlighted(file.id)"
                                            :current-folder-id="null"
                                            :current-folder-name="null"
                                            :show-version="showFileMetaColumns"
                                            :show-date="showFileMetaColumns"
                                            :show-size="showFileMetaColumns"
                                            :selected-items="recordSelectedContexts" :selection-count="recordSelectionCount"
                                            @click="(e) => handleRecordFileClick(file.id, e)" @dblclick="openFile(file.id)"
                                            @longpress="() => handleRecordLongPress(file.id, 'file')"
                                            @attachfile="handleAttachFile(file)"
                                        />
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="folders.length === 0 && files.length === 0"
                    class="flex flex-col items-center justify-center py-20 text-center">
                    <FileUp class="h-24 w-24 text-muted-foreground/50 mb-4" />
                    <h2 class="text-xl font-semibold mb-2">No Pending & Unorganized Records</h2>
                    <p class="text-muted-foreground max-w-md">
                        Upload Files to start importing records. Folder Uploads are not allowed here.
                    </p>
                </div>
            </template>
        </div>
        <UploadFileDialog v-model:open="showUploadDialog" :departments="props.departments" />
        <UpdateFileDialog ref="updateDialogRef" :folder-id="null" />
        <Dialog v-model:open="showRejectDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Reject Scanned Upload</DialogTitle>
                    <DialogDescription>
                        Let the requester know what needs to change before you can approve the scan.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3 text-sm">
                    <p class="text-muted-foreground">
                        Targeting {{ rejectingTargetName }}
                        <template v-if="rejectingRequestCode">
                            for
                            <a
                                v-if="rejectingRequestLink"
                                :href="rejectingRequestLink"
                                class="text-primary hover:underline"
                            >
                                {{ rejectingRequestCode }}
                            </a>
                            <span v-else>{{ rejectingRequestCode }}</span>
                        </template>
                    </p>
                    <div class="space-y-2">
                        <Label for="reject-reason" class="text-xs font-medium text-muted-foreground">
                            Reason for rejection
                        </Label>
                        <Textarea
                            id="reject-reason"
                            v-model="rejectReason"
                            rows="4"
                            placeholder="Describe what needs to change with this scan."
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="submittingRejection" @click="closeRejectDialog">
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="submittingRejection"
                        @click="submitReject"
                    >
                        {{ submittingRejection ? 'Rejecting...' : 'Reject Scan' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </RecordsLayout>
</template>
