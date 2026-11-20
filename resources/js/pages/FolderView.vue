<script setup lang="ts">
import { router, Head, usePage } from '@inertiajs/vue3';
import { FolderX, FolderPlus, Upload } from 'lucide-vue-next';
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import CreateNewButton from '@/components/records/CreateNewButton.vue';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';
import RecordsSortBar from '@/components/records/RecordsSortBar.vue';
import FolderTile from '@/components/records/FolderTile.vue';
import FolderListItem from '@/components/records/FolderListItem.vue';
import FileTile from '@/components/records/FileTile.vue';
import FileListItem from '@/components/records/FileListItem.vue';
import FolderTileSkeleton from '@/components/records/FolderTileSkeleton.vue';
import FileTileSkeleton from '@/components/records/FileTileSkeleton.vue';
import FolderListItemSkeleton from '@/components/records/FolderListItemSkeleton.vue';
import FileListItemSkeleton from '@/components/records/FileListItemSkeleton.vue';
import CreateFolderDialog from '@/components/records/CreateFolderDialog.vue';
import UploadFileDialog from '@/components/records/UploadFileDialog.vue';
import UpdateFileDialog from '@/components/records/UpdateFileDialog.vue';
import { createDragHandlers, createDropHandler, type DraggedItem } from '@/utils/dragHandlers';
import { useSelectionState } from '@/utils/selection';
import type { SelectionIdentity } from '@/utils/selection';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useViewMode } from '@/composables/useViewMode';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { useSelectionHelpers, type SelectState } from '@/composables/useSelectionHelpers';
import { type BreadcrumbItem, type SelectedItemType } from '@/types';
import { type FolderSchema, type File } from '@/types/schema';
import { buildFileMetadata } from '@/utils/fileAttachment';
import { openFile as navigateToFile } from '@/utils/itemNavigation';
import { sortFolders, sortFiles, buildOrderedFromSorted, groupFiles } from '@/utils/sorting';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuPortal,
    ContextMenuRoot,
    ContextMenuTrigger,
} from 'reka-ui';

const pluralizeCount = (count: number, singular: string, plural: string): string =>
    count >= 2 ? plural : singular;

const isLoading = ref(true);

const countsLabel = computed((): string => {
    const parts: string[] = [];

    if (localFolders.value.length > 0) {
        parts.push(`${localFolders.value.length} ${pluralizeCount(localFolders.value.length, 'Folder', 'Folders')}`);
    }

    if (props.files.length > 0) {
        parts.push(`${props.files.length} ${pluralizeCount(props.files.length, 'File', 'Files')}`);
    }

    return parts.join(' • ');
});

const { success, error } = useToast();
const { confirm: showConfirm } = useConfirm();
const { viewMode: folderViewMode, setViewMode: setFolderViewMode } = useViewMode('folderViewMode');
const { viewMode: fileViewMode, setViewMode: setFileViewMode } = useViewMode('fileViewMode');

const page = usePage();
const canOrganizeContent = computed(() => page.props.auth?.can?.organizeContent ?? false);

const handleSearch = (query: string) => {
    router.visit('/search', {
        method: 'get',
        data: { search: query },
        preserveScroll: true,
        preserveState: true,
    });
};

const draggedItem = ref<DraggedItem | null>(null);
const draggedItems = ref<DraggedItem[]>([]);
const dropTargetId = ref<string | null>(null);

const { handleDragStart, handleDragEnd, handleDragOver, handleDragLeave } = createDragHandlers({
    draggedItem,
    dropTargetId,
    draggedItems,
});
const { isTouchDevice } = usePointerCapabilities();


interface FolderData {
    id: string;
    name: string;
    parent_id: string | null;
}

interface FolderAncestor {
    id: string;
    name: string;
}

const props = defineProps<{
    folder: FolderData;
    ancestors: FolderAncestor[];
    folders: FolderSchema[];
    files: File[];
}>();

const localFolders = ref<FolderSchema[]>([...props.folders]);

watch(
    () => props.folders,
    (folders) => {
        localFolders.value = [...folders];
    },
    { deep: true },
);

const updateDialogRef = ref<InstanceType<typeof UpdateFileDialog> | null>(null);
const folderDialogOpen = ref(false);
const uploadDialogOpen = ref(false);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});

const folderSortField = ref<SortField>('name');
const folderSortDirection = ref<SortDirection>('asc');
const fileSortField = ref<SortField>('name');
const fileSortDirection = ref<SortDirection>('asc');
const folderGroupBy = ref<GroupBy>(null);
const fileGroupBy = ref<GroupBy>(null);
const fileRecordsPlacement = ref<RecordsPlacement>('top');

const sortedFolders = computed(() =>
    sortFolders(localFolders.value, folderSortField.value, folderSortDirection.value),
);

const sortedFiles = computed(() =>
    sortFiles(props.files, fileSortField.value, fileSortDirection.value, fileRecordsPlacement.value),
);

const orderedItems = computed<SelectionIdentity[]>(() =>
    buildOrderedFromSorted(sortedFolders.value, sortedFiles.value),
);

const groupedFiles = computed(() =>
    fileViewMode.value === 'tiles' ? groupFiles(sortedFiles.value, fileGroupBy.value) : [],
);

const {
    selectMode,
    selectedItems,
    selectionCount,
    toggleSelectMode,
    toggleSelection: toggleSelectionIdentity,
    selectOnly,
    isSelected: isIdentitySelected,
    clearSelection,
    lastSelected,
} = useSelectionState(() => orderedItems.value);

const selectionToDraggedItem = (selection: SelectionIdentity): DraggedItem | null => {
    if (selection.type === 'folder') {
        const folder = localFolders.value.find(f => f.id === selection.id);
        if (!folder) {
            return null;
        }

        return {
            id: folder.id,
            type: 'folder',
            name: folder.name,
        } satisfies DraggedItem;
    }

    const file = props.files.find(f => f.id === selection.id);
    if (!file) {
        return null;
    }

    return {
        id: file.id,
        type: 'file',
        name: file.file_name,
    } satisfies DraggedItem;
};

const buildDragCollection = (primary: DraggedItem): DraggedItem[] => {
    const isPrimarySelected = isIdentitySelected({ id: primary.id, type: primary.type });

    if (!isPrimarySelected || selectedItems.value.length <= 1) {
        return [primary];
    }

    const resolved = selectedItems.value
        .map(selectionToDraggedItem)
        .filter((item): item is DraggedItem => Boolean(item));

    return resolved.length > 0 ? resolved : [primary];
};

const handleItemDragStart = (item: DraggedItem, event: DragEvent): void => {
    draggedItems.value = buildDragCollection(item);
    handleDragStart(item, event);
};

const handleDrop = createDropHandler({
    draggedItem,
    draggedItems,
    dropTargetId,
    foldersSource: () => localFolders.value,
    router,
    showConfirm,
    success,
    error,
    reloadOnly: ['folders', 'files'],
});

const selectedContexts = computed<SelectedItemType[]>(() => {
    return selectedItems.value
        .map(selection => {
            if (selection.type === 'folder') {
                const folder = localFolders.value.find(f => f.id === selection.id);
                if (!folder) return null;

                return {
                    id: folder.id,
                    type: 'folder' as const,
                    name: folder.name,
                    isBookmarked: folder.is_bookmarked,
                    bookmarkId: folder.bookmark_id,
                    currentFolderId: props.folder.id,
                } satisfies SelectedItemType;
            }

            const file = props.files.find(f => f.id === selection.id);
            if (!file) return null;

            return {
                id: file.id,
                type: 'file' as const,
                name: file.file_name,
                isBookmarked: file.is_bookmarked,
                bookmarkId: file.bookmark_id,
                currentFolderId: props.folder.id,
                hasFile: Boolean(file.file_path),
            } satisfies SelectedItemType;
        })
        .filter(Boolean) as SelectedItemType[];
});

const selectedFolderContexts = computed(() => selectedContexts.value.filter(i => i.type === 'folder'));
const selectedFileContexts = computed(() => selectedContexts.value.filter(i => i.type === 'file'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'All Records',
        href: '/records',
    },
    ...props.ancestors.map(ancestor => ({
        title: ancestor.name,
        href: `/records/${ancestor.id}`,
    })),
]);

const openFolder = (folderId: string) => {
    router.visit(`/records/${folderId}`);
};

const openFile = (fileId: string) => {
    const file = props.files.find(f => f.id === fileId);
    navigateToFile(fileId, file, error);
};

const AUTO_REFRESH_INTERVAL_MS = 5000;
const hasProcessingFiles = computed(() =>
    props.files.some(file =>
        file.assembly_status === 'processing'
        || file.ocr_status === 'pending'
        || file.ocr_status === 'processing'
    )
);
const isAutoRefreshing = ref(false);
const autoRefreshTimer = ref<number | null>(null);

const refreshProcessingFiles = (): void => {
    if (typeof document !== 'undefined' && document.hidden) {
        return;
    }

    if (!hasProcessingFiles.value || isAutoRefreshing.value) {
        return;
    }

    isAutoRefreshing.value = true;

    router.reload({
        only: ['files'],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isAutoRefreshing.value = false;
        },
    });
};

const startAutoRefresh = (): void => {
    if (typeof window === 'undefined' || autoRefreshTimer.value !== null) {
        return;
    }

    autoRefreshTimer.value = window.setInterval(() => {
        refreshProcessingFiles();
    }, AUTO_REFRESH_INTERVAL_MS);
};

const stopAutoRefresh = (): void => {
    if (autoRefreshTimer.value === null || typeof window === 'undefined') {
        return;
    }

    window.clearInterval(autoRefreshTimer.value);
    autoRefreshTimer.value = null;
};

watch(hasProcessingFiles, (isProcessing) => {
    if (isProcessing) {
        startAutoRefresh();
        refreshProcessingFiles();
        return;
    }

    stopAutoRefresh();
});

onMounted(() => {
    if (!hasProcessingFiles.value) {
        return;
    }

    startAutoRefresh();
});

onBeforeUnmount(() => {
    stopAutoRefresh();
});

const {
    isSelected,
    selectExclusively,
    handleFolderClick,
    handleFileClick,
    handleLongPress,
    handleFolderContextMenu,
    handleFileContextMenu,
    resolveSelectState,
    applySectionSelection,
} = useSelectionHelpers({
    isIdentitySelected,
    selectOnly,
    selectedItems,
    selectMode,
    lastSelected,
    selectionCount,
    toggleSelectionIdentity,
    toggleSelectMode,
    isTouchDevice,
    openFolder,
    openFile,
});

const folderSelectState = computed<SelectState>(() =>
    resolveSelectState(sortedFolders.value.map(folder => ({ id: folder.id, type: 'folder' as const })))
);

const fileSelectState = computed<SelectState>(() =>
    resolveSelectState(sortedFiles.value.map(file => ({ id: file.id, type: 'file' as const })))
);

const toggleSelectAllFolders = (): void => {
    if (sortedFolders.value.length === 0) return;

    if (folderSelectState.value === 'checked') {
        applySectionSelection('folder', []);
        return;
    }

    const identities = sortedFolders.value.map(folder => ({ id: folder.id, type: 'folder' as const }));
    applySectionSelection('folder', identities);
};

const toggleSelectAllFiles = (): void => {
    if (sortedFiles.value.length === 0) return;

    if (fileSelectState.value === 'checked') {
        applySectionSelection('file', []);
        return;
    }

    const identities = sortedFiles.value.map(file => ({ id: file.id, type: 'file' as const }));
    applySectionSelection('file', identities);
};

const handleAttachFile = (file: File) => {
    updateDialogRef.value?.prefillMetadata({
        ...buildFileMetadata(file),
        folder_id: file.folder_id ?? props.folder.id ?? null,
    });
};

const openFolderDialog = (): void => {
    folderDialogOpen.value = true;
};

const openRecordDialog = (): void => {
    uploadDialogOpen.value = true;
};

const handleFolderUnbookmarked = (itemId: string, itemType: 'folder' | 'file'): void => {
    if (itemType !== 'folder') {
        return;
    }

    localFolders.value = localFolders.value.map(folder =>
        folder.id === itemId
            ? {
                ...folder,
                is_bookmarked: false,
                bookmark_id: null,
            }
            : folder,
    );
};

const contentClasses = 'z-50 w-56 rounded-md border bg-popover p-1 shadow-md text-sm outline-none';
const itemClasses =
    'relative flex items-center gap-2 h-8 cursor-pointer select-none rounded px-2 text-left outline-none transition-colors data-[highlighted]:bg-muted';

</script>

<template>

    <Head :title="folder.name" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <ContextMenuRoot>
            <ContextMenuTrigger as-child>
                <div class="flex h-full flex-1 flex-col gap-6 p-6" @click="clearSelection">
                    <div class="flex items-center justify-between">
                        <p class="text-muted-foreground">
                            {{ countsLabel }}
                        </p>
                        <CreateNewButton :compact="true" :parent-id="folder.id" :folder-id="folder.id" />
                    </div>

                    <!-- Folders Section -->
                    <div v-if="localFolders.length > 0 || isLoading">
                        <h2 class="text-lg font-semibold mb-3">
                            {{ pluralizeCount(localFolders.length, 'Folder', 'Folders') }}
                        </h2>

                        <ViewModeContainer :view-mode="folderViewMode">
                            <template #tiles>
                                <div class="space-y-4">
                                    <RecordsSortBar
                                        :view-mode="folderViewMode"
                                        :sort-field="folderSortField"
                                        :sort-direction="folderSortDirection"
                                        :group-by="folderGroupBy"
                                        name-label="Folder Name"
                                        :show-grouping="true"
                                        :show-size-option="false"
                                        :show-size-column="false"
                                        :show-actions-column="false"
                                        :show-version-column="true"
                                        :show-version-label="false"
                                        :show-select-control="true"
                                        size-label="Items"
                                        :select-state="folderSelectState"
                                        :force-select-visible="isTouchDevice"
                                        :selections="selectedFolderContexts"
                                        :selection-count="selectedFolderContexts.length"
                                        @update:sort-field="folderSortField = $event"
                                        @update:sort-direction="folderSortDirection = $event"
                                        @update:group-by="folderGroupBy = $event"
                                        @update:view-mode="setFolderViewMode"
                                        @toggle-select-all="toggleSelectAllFolders"
                                        @toggle-select="toggleSelectMode"
                                    />

                                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                        <template v-if="isLoading">
                                            <FolderTileSkeleton v-for="i in 8" :key="`folder-skeleton-${i}`" />
                                        </template>
                                        <template v-else>
                                            <FolderTile v-for="subfolder in sortedFolders" :key="subfolder.id" :folder="subfolder"
                                                :is-selected="isSelected(subfolder.id, 'folder')"
                                                :is-dragging="draggedItem?.id === subfolder.id"
                                                :is-drop-target="dropTargetId === subfolder.id" :current-folder-id="folder.id"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @unbookmarked="handleFolderUnbookmarked"
                                                @click="(event: Event) => handleFolderClick(subfolder.id, event)"
                                                @contextmenu="handleFolderContextMenu(subfolder.id)"
                                                @dblclick="openFolder(subfolder.id)"
                                                @longpress="handleLongPress(subfolder.id, 'folder')"
                                                @dragstart="(e: DragEvent) => handleItemDragStart({ id: subfolder.id, type: 'folder', name: subfolder.name }, e)"
                                                @dragend="handleDragEnd" @dragover="(e: DragEvent) => handleDragOver(e, subfolder.id)"
                                                @dragleave="handleDragLeave" @drop="(e: DragEvent) => handleDrop(e, subfolder.id)" />
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template #list>
                                <div class="w-full">
                                    <div class="mb-2">
                                        <RecordsSortBar
                                            :view-mode="folderViewMode"
                                            :sort-field="folderSortField"
                                            :sort-direction="folderSortDirection"
                                            :group-by="folderGroupBy"
                                            name-label="Folder Name"
                                            :show-grouping="true"
                                            :show-size-option="true"
                                            :show-size-column="true"
                                            :show-version-label="false"
                                            :show-status-column="true"
                                            :show-status-label="false"
                                            :show-select-control="true"
                                            size-label="Items"
                                            :select-state="folderSelectState"
                                            :force-select-visible="isTouchDevice"
                                            :selections="selectedFolderContexts"
                                            :selection-count="selectedFolderContexts.length"
                                            @update:sort-field="folderSortField = $event"
                                            @update:sort-direction="folderSortDirection = $event"
                                            @update:group-by="folderGroupBy = $event"
                                            @update:view-mode="setFolderViewMode"
                                            @toggle-select-all="toggleSelectAllFolders"
                                            @toggle-select="toggleSelectMode"
                                        />
                                    </div>

                                    <div>
                                        <template v-if="isLoading">
                                            <FolderListItemSkeleton v-for="i in 6" :key="`folder-list-skeleton-${i}`" />
                                        </template>
                                        <template v-else>
                                            <FolderListItem v-for="subfolder in sortedFolders" :key="subfolder.id" :folder="subfolder"
                                                :is-selected="isSelected(subfolder.id, 'folder')"
                                                :is-dragging="draggedItem?.id === subfolder.id"
                                                :is-drop-target="dropTargetId === subfolder.id" :current-folder-id="folder.id"
                                                :show-status-spacer="true"
                                                :show-version-spacer="true"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @unbookmarked="handleFolderUnbookmarked"
                                                @click="(event: Event) => handleFolderClick(subfolder.id, event)"
                                                @contextmenu="handleFolderContextMenu(subfolder.id)"
                                                @dblclick="openFolder(subfolder.id)"
                                                @longpress="handleLongPress(subfolder.id, 'folder')"
                                                @dragstart="(e: DragEvent) => handleItemDragStart({ id: subfolder.id, type: 'folder', name: subfolder.name }, e)"
                                                @dragend="handleDragEnd"
                                                @dragover="(e: DragEvent) => handleDragOver(e, subfolder.id)"
                                                @dragleave="handleDragLeave"
                                                @drop="(e: DragEvent) => handleDrop(e, subfolder.id)" />
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>

                    <!-- Files Section -->
                    <div v-if="files.length > 0 || isLoading">
                        <h2 class="text-lg font-semibold mb-3">
                            {{ pluralizeCount(files.length, 'File', 'Files') }}
                        </h2>

                        <ViewModeContainer :view-mode="fileViewMode">
                            <template #tiles>
                                <div class="space-y-4">
                                    <RecordsSortBar
                                        :view-mode="fileViewMode"
                                        :sort-field="fileSortField"
                                        :sort-direction="fileSortDirection"
                                        :group-by="fileGroupBy"
                                        :show-records-placement="true"
                                        :records-placement="fileRecordsPlacement"
                                        :show-select-control="true"
                                        :select-state="fileSelectState"
                                        :force-select-visible="isTouchDevice"
                                        :selections="selectedFileContexts"
                                        :selection-count="selectedFileContexts.length"
                                        @update:sort-field="fileSortField = $event"
                                        @update:sort-direction="fileSortDirection = $event"
                                        @update:group-by="fileGroupBy = $event"
                                        @update:records-placement="fileRecordsPlacement = $event"
                                        @update:view-mode="setFileViewMode"
                                        @toggle-select-all="toggleSelectAllFiles"
                                        @toggle-select="toggleSelectMode"
                                    />

                                    <template v-if="isLoading">
                                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                            <FileTileSkeleton v-for="i in 8" :key="`file-skeleton-${i}`" />
                                        </div>
                                    </template>

                                    <template v-else-if="fileGroupBy && groupedFiles.length">
                                        <div v-for="group in groupedFiles" :key="group.key" class="space-y-2">
                                            <div class="text-xs font-semibold text-muted-foreground border-b border-sidebar-border/60 pb-1">
                                                {{ group.label }}
                                            </div>
                                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                                <FileTile v-for="file in group.items" :key="file.id" :file="file"
                                                    :is-selected="isSelected(file.id, 'file')" :is-dragging="draggedItem?.id === file.id"
                                                    :is-drop-target="dropTargetId === file.id" :current-folder-id="folder.id"
                                                    :current-folder-name="folder.name"
                                                    :selected-items="selectedContexts" :selection-count="selectionCount"
                                                    @click="(event: Event) => handleFileClick(file.id, event)" @contextmenu="handleFileContextMenu(file.id)" @dblclick="openFile(file.id)"
                                                    @longpress="handleLongPress(file.id, 'file')"
                                                    @dragstart="(e: DragEvent) => handleItemDragStart({ id: file.id, type: 'file', name: file.file_name }, e)"
                                                    @dragend="handleDragEnd" @dragover="(e: DragEvent) => handleDragOver(e, file.id)"
                                                    @dragleave="handleDragLeave" @drop="(e: DragEvent) => handleDrop(e, file.id)"
                                                    @attachfile="handleAttachFile(file)" />
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                            <FileTile v-for="file in sortedFiles" :key="file.id" :file="file"
                                                :is-selected="isSelected(file.id, 'file')" :is-dragging="draggedItem?.id === file.id"
                                                :is-drop-target="dropTargetId === file.id" :current-folder-id="folder.id"
                                                :current-folder-name="folder.name"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @click="(event: Event) => handleFileClick(file.id, event)" @contextmenu="handleFileContextMenu(file.id)" @dblclick="openFile(file.id)"
                                                @longpress="handleLongPress(file.id, 'file')"
                                                @dragstart="(e: DragEvent) => handleItemDragStart({ id: file.id, type: 'file', name: file.file_name }, e)"
                                                @dragend="handleDragEnd" @dragover="(e: DragEvent) => handleDragOver(e, file.id)"
                                                @dragleave="handleDragLeave" @drop="(e: DragEvent) => handleDrop(e, file.id)"
                                                @attachfile="handleAttachFile(file)" />
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template #list>
                                <div class="w-full">
                                    <div class="mb-2">
                                        <RecordsSortBar
                                            :view-mode="fileViewMode"
                                            :sort-field="fileSortField"
                                            :sort-direction="fileSortDirection"
                                            :group-by="fileGroupBy"
                                            :show-records-placement="true"
                                            :records-placement="fileRecordsPlacement"
                                            :show-status-column="true"
                                            :show-select-control="true"
                                            :select-state="fileSelectState"
                                            :force-select-visible="isTouchDevice"
                                            :selections="selectedFileContexts"
                                            :selection-count="selectedFileContexts.length"
                                            :show-status-label="true"
                                            @update:sort-field="fileSortField = $event"
                                            @update:sort-direction="fileSortDirection = $event"
                                            @update:group-by="fileGroupBy = $event"
                                            @update:records-placement="fileRecordsPlacement = $event"
                                            @update:view-mode="setFileViewMode"
                                            @toggle-select-all="toggleSelectAllFiles"
                                            @toggle-select="toggleSelectMode"
                                        />
                                    </div>

                                    <div>
                                        <template v-if="isLoading">
                                            <FileListItemSkeleton v-for="i in 6" :key="`file-list-skeleton-${i}`" />
                                        </template>
                                        <template v-else>
                                            <FileListItem v-for="file in sortedFiles" :key="file.id" :file="file"
                                                :is-selected="isSelected(file.id, 'file')"
                                                :is-dragging="draggedItem?.id === file.id"
                                                :is-drop-target="dropTargetId === file.id" :current-folder-id="folder.id"
                                                :current-folder-name="folder.name"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @click="(event: Event) => handleFileClick(file.id, event)"
                                                @contextmenu="handleFileContextMenu(file.id)"
                                                @dblclick="openFile(file.id)" @longpress="handleLongPress(file.id, 'file')"
                                                @dragstart="(e: DragEvent) => handleItemDragStart({ id: file.id, type: 'file', name: file.file_name }, e)"
                                                @dragend="handleDragEnd" @dragover="(e: DragEvent) => handleDragOver(e, file.id)"
                                                @dragleave="handleDragLeave" @drop="(e: DragEvent) => handleDrop(e, file.id)"
                                                @attachfile="handleAttachFile(file)" />
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>

                    <!-- Empty State -->
                    <div v-if="localFolders.length === 0 && files.length === 0"
                        class="flex flex-col items-center justify-center py-20 text-center">
                        <FolderX class="h-24 w-24 text-muted-foreground/50 mb-4" />
                        <h2 class="text-xl font-semibold mb-2">This Folder is Empty</h2>
                        <p class="text-muted-foreground max-w-md">
                            Add folders or files using the "New" button above.
                        </p>
                    </div>
                </div>
            </ContextMenuTrigger>

            <ContextMenuPortal>
                <ContextMenuContent :class="contentClasses" :side-offset="6">
                    <ContextMenuItem :class="itemClasses" @click="openFolderDialog">
                        <FolderPlus class="h-4 w-4" />
                        <span>New Folder</span>
                    </ContextMenuItem>
                    <ContextMenuItem :class="itemClasses" @click="openRecordDialog">
                        <Upload class="h-4 w-4" />
                        <span>New Record</span>
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>

        <CreateFolderDialog v-model:open="folderDialogOpen" :parent-id="folder.id" />
        <UploadFileDialog v-model:open="uploadDialogOpen" :folder-id="folder.id" />
        <UpdateFileDialog ref="updateDialogRef" :folder-id="folder.id" />
    </AppLayout>
</template>
