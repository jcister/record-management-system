<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Folder, FolderPlus } from 'lucide-vue-next';
import RecordsLayout from '@/layouts/RecordsLayout.vue';
import CreateNewButton from '@/components/records/CreateNewButton.vue';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';
import RecordsSortBar from '@/components/records/RecordsSortBar.vue';
import FolderTile from '@/components/records/FolderTile.vue';
import FolderListItem from '@/components/records/FolderListItem.vue';
import FileTile from '@/components/records/FileTile.vue';
import FileListItem from '@/components/records/FileListItem.vue';
import CreateFolderDialog from '@/components/records/CreateFolderDialog.vue';
import UploadFileDialog from '@/components/records/UploadFileDialog.vue';
import UpdateFileDialog from '@/components/records/UpdateFileDialog.vue';
import { createDragHandlers, createDropHandler, type DraggedItem } from '@/utils/dragHandlers';
import { useSelectionState } from '@/utils/selection';
import type { SelectionIdentity } from '@/utils/selection';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useSelectionHelpers, type SelectState } from '@/composables/useSelectionHelpers';
import { type BreadcrumbItem, type SelectedItemType } from '@/types';
import { type FolderSchema, type File } from '@/types/schema';
import { buildSelectedContexts } from '@/utils/selectionContext';
import { openFolder as navigateToFolder, openFile as navigateToFile } from '@/utils/itemNavigation';
import { buildFileMetadata } from '@/utils/fileAttachment';
import { sortFolders, sortFiles, buildOrderedFromSorted, groupFiles } from '@/utils/sorting';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import { useViewMode } from '@/composables/useViewMode';
import FolderTileSkeleton from '@/components/records/FolderTileSkeleton.vue';
import FolderListItemSkeleton from '@/components/records/FolderListItemSkeleton.vue';
// SearchBar is provided by RecordsLayout
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuPortal,
    ContextMenuRoot,
    ContextMenuTrigger,
} from 'reka-ui';
import { usePermissions } from '@/utils/permission';
import { onMounted } from 'vue';

const pluralizeCount = (count: number, singular: string, plural: string): string =>
    count >= 2 ? plural : singular;

const isLoading = ref(true);

const { success, error } = useToast();
const { confirm: showConfirm } = useConfirm();
const { viewMode: folderViewMode, setViewMode: setFolderViewMode } = useViewMode('folderViewMode');
const { viewMode: fileViewMode, setViewMode: setFileViewMode } = useViewMode('fileViewMode');

const draggedItem = ref<DraggedItem | null>(null);
const draggedItems = ref<DraggedItem[]>([]);
const dropTargetId = ref<string | null>(null);
const { isTouchDevice } = usePointerCapabilities();

const folderSortField = ref<SortField>('name');
const folderSortDirection = ref<SortDirection>('asc');
const fileSortField = ref<SortField>('name');
const fileSortDirection = ref<SortDirection>('asc');
const folderGroupBy = ref<GroupBy>(null);
const fileGroupBy = ref<GroupBy>(null);
const fileRecordsPlacement = ref<RecordsPlacement>('top');

const sortedFiles = computed(() =>
    sortFiles(props.files, fileSortField.value, fileSortDirection.value, fileRecordsPlacement.value),
);

const orderedItems = computed<SelectionIdentity[]>(() =>
    buildOrderedFromSorted(sortedFolders.value, sortedFiles.value),
);

const groupedFiles = computed(() =>
    viewMode.value === 'tiles' ? groupFiles(sortedFiles.value, fileGroupBy.value) : [],
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

const { handleDragStart, handleDragEnd, handleDragOver, handleDragLeave } = createDragHandlers({
    draggedItem,
    dropTargetId,
    draggedItems,
});


const props = defineProps<{
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

const sortedFolders = computed(() =>
    sortFolders(localFolders.value, folderSortField.value, folderSortDirection.value),
);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 300);
});

const updateDialogRef = ref<InstanceType<typeof UpdateFileDialog> | null>(null);
const folderDialogOpen = ref(false);
const uploadDialogOpen = ref(false);

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

const toDraggedItem = (item: FolderSchema | File): DraggedItem => {
    if ('file_name' in item) {
        return {
            id: item.id,
            type: 'file',
            name: item.file_name,
        } satisfies DraggedItem;
    }

    return {
        id: item.id,
        type: 'folder',
        name: item.name,
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

const handleItemDragStart = (item: FolderSchema | File, event: DragEvent): void => {
    const primary = toDraggedItem(item);
    draggedItems.value = buildDragCollection(primary);
    handleDragStart(primary, event);
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'All Records',

        href: '/records',
    },
];

const openFolder = (folderId: string) => {
    navigateToFolder(folderId, '/records');
};

const openFile = (fileId: string) => {
    const file = props.files.find(f => f.id === fileId);
    navigateToFile(fileId, file, error);
};

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

const selectedContexts = computed<SelectedItemType[]>(() =>
    buildSelectedContexts({
        selections: selectedItems.value,
        folders: localFolders.value,
        files: props.files,
        currentFolderId: null,
    })
);

const selectedFolderContexts = computed(() => selectedContexts.value.filter(i => i.type === 'folder'));
const selectedFileContexts = computed(() => selectedContexts.value.filter(i => i.type === 'file'));

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
    updateDialogRef.value?.prefillMetadata(buildFileMetadata(file));
};

const openFolderDialog = (): void => {
    folderDialogOpen.value = true;
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
    reloadOnly: ['folders'],
});

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

const{ hasPermission } = usePermissions();

const createDragProps = (item: FolderSchema | File) => {
    if (!hasPermission('organizeContent')) {
        return {
            isDragging: false,
            isDropTarget: false,
        };
    }
    return {
        isDragging: draggedItem.value?.id === item.id,
        isDropTarget: dropTargetId.value === item.id,
        onDragstart: (e: DragEvent) => handleItemDragStart(item, e),
        onDragend: handleDragEnd,
        onDragover: (e: DragEvent) => handleDragOver(e, item.id),
        onDragleave: handleDragLeave,
        onDrop: (e: DragEvent) => handleDrop(e, item.id),
    };
};
</script>

<template>

    <Head title="All Records" />

    <RecordsLayout :breadcrumbs="breadcrumbs">

        <ContextMenuRoot>
            <ContextMenuTrigger as-child>
                <div class="flex h-full flex-1 flex-col gap-6 p-6 pt-0" @click="clearSelection">
                    <div class="flex items-center justify-between pt-6">
                        <p class="text-muted-foreground">
                            {{ localFolders.length }} {{ pluralizeCount(localFolders.length, 'Folder', 'Folders') }}
                        </p>
                        <CreateNewButton :compact="true" class="cursor-pointer" />
                    </div>
                    <div v-if="localFolders.length > 0 || isLoading">
                        <ViewModeContainer :view-mode="folderViewMode">
                            <template #tiles>
                                <div class="space-y-4 pt-6 ">
                                    <RecordsSortBar
                                        :view-mode="folderViewMode"
                                        :sort-field="folderSortField"
                                        :sort-direction="folderSortDirection"
                                        :group-by="folderGroupBy"
                                        name-label="Folder Name"
                                        :show-grouping="true"
                                        :show-type-option="false"
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
                                            <FolderTile v-for="folder in sortedFolders" :key="folder.id" :folder="folder"
                                                :is-selected="isSelected(folder.id, 'folder')" :current-folder-id="null"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @unbookmarked="handleFolderUnbookmarked"
                                                @click="(e) => handleFolderClick(folder.id, e)"
                                                @contextmenu="handleFolderContextMenu(folder.id)"
                                                @dblclick="openFolder(folder.id)"
                                                @longpress="() => handleLongPress(folder.id, 'folder')"
                                                v-bind="createDragProps(folder)" />
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template #list>
                                <div class="w-full relative">
                                    <div class="mb-2 sticky top-0 pt-6 bg-background">
                                        <RecordsSortBar
                                            :view-mode="folderViewMode"
                                            :sort-field="folderSortField"
                                            :sort-direction="folderSortDirection"
                                            :group-by="folderGroupBy"
                                            name-label="Folder Name"
                                            :show-grouping="true"
                                            :show-type-option="false"
                                            :show-size-option="true"
                                            :show-size-column="true"
                                            :show-status-column="true"
                                            :show-status-label="false"
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
                                    </div>

                                    <div>
                                        <template v-if="isLoading">
                                            <FolderListItemSkeleton v-for="i in 6" :key="`folder-list-skeleton-${i}`" />
                                        </template>
                                        <template v-else>
                                            <FolderListItem v-for="folder in sortedFolders" :key="folder.id" :folder="folder"
                                                :is-selected="isSelected(folder.id, 'folder')" :current-folder-id="null"
                                                :show-status-spacer="true"
                                                :show-version-spacer="true"
                                                :selected-items="selectedContexts" :selection-count="selectionCount"
                                                @unbookmarked="handleFolderUnbookmarked"
                                                @click="(e) => handleFolderClick(folder.id, e)"
                                                @contextmenu="handleFolderContextMenu(folder.id)"
                                                @dblclick="openFolder(folder.id)"
                                                @longpress="() => handleLongPress(folder.id, 'folder')"
                                                v-bind="createDragProps(folder)" />
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>

                    <div v-if="files.length > 0">
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

                                    <template v-if="fileGroupBy && groupedFiles.length">
                                        <div v-for="group in groupedFiles" :key="group.key" class="space-y-2">
                                            <div class="text-xs font-semibold text-muted-foreground border-b border-sidebar-border/60 pb-1">
                                                {{ group.label }}
                                            </div>
                                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                                <FileTile v-for="file in group.items" :key="file.id" :file="file"
                                                    :is-selected="isSelected(file.id, 'file')" :current-folder-id="null"
                                                    :current-folder-name="null" :selected-items="selectedContexts"
                                                    :selection-count="selectionCount" @click="(e) => handleFileClick(file.id, e)"
                                                    @contextmenu="handleFileContextMenu(file.id)" @dblclick="openFile(file.id)"
                                                    @longpress="() => handleLongPress(file.id, 'file')"
                                                    @attachfile="handleAttachFile(file)" v-bind="createDragProps(file)" />
                                            </div>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
                                            <FileTile v-for="file in sortedFiles" :key="file.id" :file="file"
                                                :is-selected="isSelected(file.id, 'file')" :current-folder-id="null"
                                                :current-folder-name="null" :selected-items="selectedContexts"
                                                :selection-count="selectionCount" @click="(e) => handleFileClick(file.id, e)"
                                                @contextmenu="handleFileContextMenu(file.id)" @dblclick="openFile(file.id)"
                                                @longpress="() => handleLongPress(file.id, 'file')"
                                                @attachfile="handleAttachFile(file)" v-bind="createDragProps(file)" />
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
                                        <FileListItem v-for="file in sortedFiles" :key="file.id" :file="file"
                                            :is-selected="isSelected(file.id, 'file')" :current-folder-id="null"
                                            :current-folder-name="null" :selected-items="selectedContexts"
                                            :selection-count="selectionCount"
                                            @click="(e) => handleFileClick(file.id, e)"
                                            @contextmenu="handleFileContextMenu(file.id)" @dblclick="openFile(file.id)"
                                            @longpress="() => handleLongPress(file.id, 'file')"
                                            @attachfile="handleAttachFile(file)" v-bind="createDragProps(file)" />
                                    </div>
                                </div>
                            </template>
                        </ViewModeContainer>
                    </div>

                    <div v-if="localFolders.length === 0 && files.length === 0"
                        class="flex flex-col items-center justify-center py-20 text-center">
                        <Folder class="h-24 w-24 text-muted-foreground/50 mb-4" />
                        <h2 class="text-xl font-semibold mb-2">No items yet</h2>
                        <p class="text-muted-foreground max-w-md">
                            Create a folder or upload a file using the "New" button in the sidebar to get started.
                        </p>
                    </div>
                </div>
            </ContextMenuTrigger>

            <ContextMenuPortal>
                <ContextMenuContent class="z-50 w-56 rounded-md border bg-popover p-1 shadow-md text-sm outline-none" :side-offset="6">
                    <ContextMenuItem class="relative flex items-center gap-2 h-8 cursor-pointer select-none rounded px-2 text-left outline-none transition-colors data-[highlighted]:bg-muted" @click="openFolderDialog">
                        <FolderPlus class="h-4 w-4" />
                        <span>New Folder</span>
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>

        <CreateFolderDialog v-model:open="folderDialogOpen" :parent-id="null" />
        <UploadFileDialog v-model:open="uploadDialogOpen" :folder-id="null" />
        <UpdateFileDialog ref="updateDialogRef" :folder-id="null" />
    </RecordsLayout>
</template>
