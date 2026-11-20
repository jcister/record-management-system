<script setup lang="ts">
import axios from '@/lib/axios';
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';
import RecordsSortBar from '@/components/records/RecordsSortBar.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SelectedItemType } from '@/types';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useSelectionState } from '@/utils/selection';
import type { SelectionIdentity, SelectionType } from '@/utils/selection';
import FolderTile from '@/components/records/FolderTile.vue';
import FolderListItem from '@/components/records/FolderListItem.vue';
import FileTile from '@/components/records/FileTile.vue';
import FileListItem from '@/components/records/FileListItem.vue';
import FolderTileSkeleton from '@/components/records/FolderTileSkeleton.vue';
import FolderListItemSkeleton from '@/components/records/FolderListItemSkeleton.vue';
import FileTileSkeleton from '@/components/records/FileTileSkeleton.vue';
import FileListItemSkeleton from '@/components/records/FileListItemSkeleton.vue';
import { type FolderSchema, type File } from '@/types/schema';
import { buildOrderedItems } from '@/utils/itemOrdering';
import { sortFolders, sortFiles } from '@/utils/sorting';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import { buildSelectedContexts } from '@/utils/selectionContext';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useSelectionHelpers } from '@/composables/useSelectionHelpers';
import { openFile as navigateToFile } from '@/utils/itemNavigation';
import { useViewMode } from '@/composables/useViewMode';


const { confirm: showConfirm } = useConfirm();
const { error, success } = useToast();
const { isTouchDevice } = usePointerCapabilities();

const { viewMode: folderViewMode, setViewMode: setFolderViewMode } = useViewMode('folderViewMode');
const { viewMode: fileViewMode, setViewMode: setFileViewMode } = useViewMode('fileViewMode');
const folderSortField = ref<SortField>('name');
const folderSortDirection = ref<SortDirection>('asc');
const fileSortField = ref<SortField>('name');
const fileSortDirection = ref<SortDirection>('asc');
const groupBy = ref<GroupBy>(null);
const recordsPlacement = ref<RecordsPlacement>('top');

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
    folders: FolderSchema[];
    files: File[];
    folder?: FolderData;
    ancestors?: FolderAncestor[];
    departments?: { id: string; name: string }[];
}>();

const isLoading = ref(true);
let loadingTimeout: ReturnType<typeof setTimeout> | undefined;

const stopLoading = () => {
    if (loadingTimeout) {
        clearTimeout(loadingTimeout);
    }

    loadingTimeout = setTimeout(() => {
        isLoading.value = false;
    }, 300);
};

const startLoading = () => {
    if (loadingTimeout) {
        clearTimeout(loadingTimeout);
    }

    isLoading.value = true;
};

const handleRouteStart = () => startLoading();
const handleRouteFinish = () => stopLoading();

onMounted(() => {
    startLoading();
    stopLoading();

    router.on?.('start', handleRouteStart);
    router.on?.('finish', handleRouteFinish);
});

onUnmounted(() => {
    router.off?.('start', handleRouteStart);
    router.off?.('finish', handleRouteFinish);

    if (loadingTimeout) {
        clearTimeout(loadingTimeout);
    }
});

const orderedItems = computed<SelectionIdentity[]>(() =>
    buildOrderedItems(props.folders, props.files)
);

const {
    selectMode,
    selectedItems,
    selectionCount,
    toggleSelectMode,
    toggleSelection: toggleSelectionIdentity,
    isSelected: isIdentitySelected,
    clearSelection,
    selectOnly,
    lastSelected,
} = useSelectionState(() => orderedItems.value);

const openFolder = (folderId: string) => {
    router.visit(`/records/trash/${folderId}`);
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

const pluralizeCount = (count: number, singular: string, plural: string): string =>
    `${count} ${count === 1 ? singular : plural}`;

const handleContextRestore = (itemIdentity: { id: string; type: SelectionType }) => {
    if (itemIdentity.type === 'folder') {
        restoreFolder(itemIdentity.id);
        return;
    }

    if (itemIdentity.type === 'file') {
        restoreFile(itemIdentity.id);
    }
};

const handleContextDelete = (itemIdentity: { id: string; type: SelectionType }) => {
    if (itemIdentity.type === 'folder') {
        permanentlyDeleteFolder(itemIdentity.id);
        return;
    }

    if (itemIdentity.type === 'file') {
        permanentlyDeleteFile(itemIdentity.id);
    }
};

const buildRenamedFileName = (currentName: string): string => {
    const parts = currentName.match(/^(.+?)(\.[^.]+)?$/);
    const baseName = parts?.[1] ?? currentName;
    const extension = parts?.[2] ?? '';

    let counter = 1;
    let candidate = `${baseName} (${counter})${extension}`;

    while (props.files.some(existing => existing.file_name === candidate)) {
        counter++;
        candidate = `${baseName} (${counter})${extension}`;
    }

    return candidate;
};

const selectedContexts = computed<SelectedItemType[]>(() =>
    buildSelectedContexts({
        selections: selectedItems.value,
        folders: props.folders,
        files: props.files,
        currentFolderId: props.folder?.id ?? null,
    }).map(item => ({
        ...item,
        isBookmarked: false,
        bookmarkId: undefined,
    }))
);

const sortedFolders = computed(() => sortFolders(props.folders, folderSortField.value, folderSortDirection.value));
const sortedFiles = computed(() =>
    sortFiles(props.files, fileSortField.value, fileSortDirection.value, recordsPlacement.value),
);

const showFileVersionColumn = computed(() => fileViewMode.value === 'list');
const showFolderVersionColumn = computed(() => folderViewMode.value === 'list');

const selectedFolderContexts = computed(() => selectedContexts.value.filter(i => i.type === 'folder'));
const selectedFileContexts = computed(() => selectedContexts.value.filter(i => i.type === 'file'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base: BreadcrumbItem[] = [
        {
            title: 'Trash',
            href: '/records/trash',
        },
    ];

    if (props.folder && props.ancestors) {
        return [
            ...base,
            ...props.ancestors.map(ancestor => ({
                title: ancestor.name,
                href: `/records/trash/${ancestor.id}`,
            })),
        ];
    }

    return base;
});

const restoreFolder = async (folderId: string, event?: Event) => {
    event?.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Restore Folder',
        description: 'Are you sure you want to restore this folder?',
        confirmText: 'Restore',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.post(`/api/folders/${folderId}/restore`);
        router.reload({ only: ['folders'] });
        success('Folder restored successfully');
    } catch {
        error('Failed to restore folder', 'Please try again.');
    }
};

const restoreFile = async (fileId: string, event?: Event) => {
    event?.stopPropagation();

    const file = props.files.find(entry => entry.id === fileId);
    const fileName = file?.file_name ?? 'file';

    const confirmed = await showConfirm({
        title: 'Restore File',
        description: 'Are you sure you want to restore this file?',
        confirmText: 'Restore',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.post(`/api/files/${fileId}/restore`);
        router.reload({ only: ['files'] });
        success('File restored successfully');
    } catch (err: any) {
        const errorCode = err?.response?.data?.code;

        if (errorCode === 'duplicate_filename') {
            const duplicateId = err.response?.data?.duplicate_id as string | undefined;
            const suggestedName = err.response?.data?.suggested_name as string | undefined;

            const choice = await showConfirm({
                title: 'Duplicate File Name',
                description: `A file named "${fileName}" already exists in this folder. What would you like to do?`,
                replaceText: 'Replace existing',
                confirmText: 'Rename automatically',
                cancelText: 'Cancel'
            });

            if (choice === 'replace' && duplicateId) {
                await axios.post(`/api/files/${fileId}/restore`, {
                    strategy: 'replace'
                });
                router.reload({ only: ['files'] });
                success('File restored successfully', `${fileName} replaced the existing file.`);
                return;
            }

            if (choice === 'confirm') {
                const nextName = suggestedName ?? buildRenamedFileName(fileName);

                await axios.post(`/api/files/${fileId}/restore`, {
                    strategy: 'rename',
                    new_name: nextName
                });
                router.reload({ only: ['files'] });
                success('File restored successfully', `${fileName} restored as ${nextName}.`);
                return;
            }
        }

        const message = err?.response?.data?.message || 'Please try again.';
        error('Failed to restore file', message);
    }
};

const permanentlyDeleteFolder = async (folderId: string, event?: Event) => {
    event?.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Delete Folder',
        description: 'Are you sure you want to delete this folder?',
        confirmText: 'Delete',
        cancelText: 'Cancel',
        variant: 'destructive'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.delete(`/api/folders/${folderId}/force`);
        router.reload({ only: ['folders'] });
        success('Folder deleted successfully');
    } catch {
        error('Failed to delete folder', 'Please try again.');
    }
};

const permanentlyDeleteFile = async (fileId: string, event?: Event) => {
    event?.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Delete File',
        description: 'Are you sure you want to delete this file?',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.delete(`/api/files/${fileId}/force`);
        router.reload({ only: ['files'] });
        success('File deleted successfully');
    } catch {
        error('Failed to delete file', 'Please try again.');
    }
};
</script>

<template>

    <Head :title="folder?.name ?? 'Trash'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6" @click="clearSelection">
            <div v-if="!isLoading && folder && (folders.length > 0 || files.length > 0)">
                <p class="text-muted-foreground mt-2">
                    <span v-if="folders.length > 0">{{ pluralizeCount(folders.length, 'Folder', 'Folders') }}</span>
                    <span v-if="folders.length > 0 && files.length > 0"> • </span>
                    <span v-if="files.length > 0">{{ pluralizeCount(files.length, 'File', 'Files') }}</span>
                </p>
            </div>

            <!-- Trashed Folders Section -->
            <div v-if="folders.length > 0 || isLoading">
                <h2 class="text-lg font-semibold mb-3">Trashed Folders</h2>
                <ViewModeContainer :view-mode="folderViewMode">
                    <template #tiles>
                        <div class="space-y-4">
                            <RecordsSortBar
                                :view-mode="folderViewMode"
                                :sort-field="folderSortField"
                                :sort-direction="folderSortDirection"
                                :group-by="groupBy"
                                name-label="Folder Name"
                                :show-grouping="false"
                                :show-actions-column="false"
                                :show-version-column="showFolderVersionColumn"
                                :show-size-option="false"
                                :show-size-column="false"
                                size-label="Items"
                                :show-select-control="false"
                                :selections="selectedFolderContexts"
                                :selection-count="selectedFolderContexts.length"
                                @update:sort-field="folderSortField = $event"
                                @update:sort-direction="folderSortDirection = $event"
                                @update:view-mode="setFolderViewMode"
                                @toggle-select="toggleSelectMode"
                            />
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <template v-if="isLoading">
                                    <FolderTileSkeleton v-for="i in 8" :key="`folder-skeleton-${i}`" />
                                </template>
                                <template v-else>
                                    <FolderTile v-for="folder in sortedFolders" :key="folder.id" :folder="folder"
                                    :is-selected="isSelected(folder.id, 'folder')" :current-folder-id="null"
                                    :draggable="false" context-mode="trash"
                                    :selected-items="selectedContexts" :selection-count="selectionCount"
                                    @click="(e) => handleFolderClick(folder.id, e)"
                                    @contextmenu="handleFolderContextMenu(folder.id)"
                                    @dblclick="openFolder(folder.id)"
                                    @longpress="() => handleLongPress(folder.id, 'folder')"
                                    @restore="handleContextRestore" @delete-permanent="handleContextDelete">
                                    </FolderTile>
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
                                    :group-by="groupBy"
                                    name-label="Folder Name"
                                    :show-grouping="false"
                                    :show-actions-column="true"
                                    :show-version-column="showFolderVersionColumn"
                                    :show-size-option="true"
                                    :show-size-column="true"
                                    :show-version-label="false"
                                    size-label="Items"
                                    :show-select-control="false"
                                    :selections="selectedFolderContexts"
                                    :selection-count="selectedFolderContexts.length"
                                    @update:sort-field="folderSortField = $event"
                                    @update:sort-direction="folderSortDirection = $event"
                                    @update:view-mode="setFolderViewMode"
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
                                            :draggable="false" context-mode="trash" :show-version-spacer="showFolderVersionColumn"
                                            :selected-items="selectedContexts" :selection-count="selectionCount"
                                            @click="(e) => handleFolderClick(folder.id, e)" @contextmenu="handleFolderContextMenu(folder.id)" @dblclick="openFolder(folder.id)"
                                            @longpress="() => handleLongPress(folder.id, 'folder')"
                                            @restore="handleContextRestore" @delete-permanent="handleContextDelete">
                                        </FolderListItem>
                                    </template>
                            </div>
                        </div>
                    </template>
                </ViewModeContainer>
            </div>

            <!-- Trashed Files Section -->
            <div v-if="files.length > 0 || isLoading">
                <h2 class="text-lg font-semibold mb-3">Trashed Files</h2>

                <ViewModeContainer :view-mode="fileViewMode">
                    <template #tiles>
                        <div class="space-y-4">
                            <RecordsSortBar
                                :view-mode="fileViewMode"
                                :sort-field="fileSortField"
                                :sort-direction="fileSortDirection"
                                :group-by="groupBy"
                                :show-grouping="false"
                                :show-actions-column="false"
                                :show-version-column="showFileVersionColumn"
                                :show-records-placement="true"
                                :records-placement="recordsPlacement"
                                :show-select-control="false"
                                :selections="selectedFileContexts"
                                :selection-count="selectedFileContexts.length"
                                @update:sort-field="fileSortField = $event"
                                @update:sort-direction="fileSortDirection = $event"
                                @update:records-placement="recordsPlacement = $event"
                                @update:view-mode="setFileViewMode"
                                @toggle-select="toggleSelectMode"
                            />
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                <template v-if="isLoading">
                                    <FileTileSkeleton v-for="i in 8" :key="`file-skeleton-${i}`" />
                                </template>
                                <template v-else>
                                    <FileTile v-for="file in sortedFiles" :key="file.id" :file="file"
                                    :is-selected="isSelected(file.id, 'file')" :current-folder-id="null" :draggable="false"
                                    context-mode="trash" :metadata="file" :selected-items="selectedContexts" :selection-count="selectionCount" @click="(e) => handleFileClick(file.id, e)"
                                    @dblclick.stop="openFile(file.id)"
                                    @contextmenu="handleFileContextMenu(file.id)"
                                    @longpress="() => handleLongPress(file.id, 'file')"
                                    @restore="handleContextRestore" @delete-permanent="handleContextDelete">
                                </FileTile>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template #list>
                        <div class="w-full">
                            <div class="mb-2">
                                <RecordsSortBar
                                    :view-mode="fileViewMode"
                                    :sort-field="fileSortField"
                                    :sort-direction="fileSortDirection"
                                    :group-by="groupBy"
                                    :show-grouping="false"
                                    :show-actions-column="true"
                                    :show-version-column="showFileVersionColumn"
                                    :show-status-column="showFileVersionColumn"
                                    :show-records-placement="true"
                                    :records-placement="recordsPlacement"
                                    :show-select-control="false"
                                    :selections="selectedFileContexts"
                                    :selection-count="selectedFileContexts.length"
                                    @update:sort-field="fileSortField = $event"
                                    @update:sort-direction="fileSortDirection = $event"
                                    @update:records-placement="recordsPlacement = $event"
                                    @update:view-mode="setFileViewMode"
                                    @toggle-select="toggleSelectMode"
                                />
                            </div>

                            <div>
                                    <template v-if="isLoading">
                                        <FileListItemSkeleton v-for="i in 6" :key="`file-list-skeleton-${i}`" />
                                    </template>
                                    <template v-else>
                                        <FileListItem v-for="file in sortedFiles" :key="file.id" :file="file"
                                            :is-selected="isSelected(file.id, 'file')" :current-folder-id="null"
                                            :draggable="false" context-mode="trash" :metadata="file"
                                            :selected-items="selectedContexts" :selection-count="selectionCount"
                                            @click="(e) => handleFileClick(file.id, e)"
                                            @dblclick.stop="openFile(file.id)" @contextmenu="handleFileContextMenu(file.id)"
                                            @longpress="() => handleLongPress(file.id, 'file')"
                                            @restore="handleContextRestore" @delete-permanent="handleContextDelete">
                                        </FileListItem>
                                    </template>
                            </div>
                        </div>
                    </template>
                </ViewModeContainer>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && folders.length === 0 && files.length === 0"
                class="flex flex-col items-center justify-center py-20 text-center">
                <Trash2 class="h-24 w-24 fill-none stroke-current text-muted-foreground/50 mb-4" />
                <h2 class="text-xl font-semibold mb-2">No Trashed Items</h2>
                <p class="text-muted-foreground max-w-md">
                    Items you trash will appear here and can be restored or permanently deleted.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
