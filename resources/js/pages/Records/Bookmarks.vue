<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { FolderX as FolderIcon } from 'lucide-vue-next';

import FolderTile from '@/components/records/FolderTile.vue';
import FolderListItem from '@/components/records/FolderListItem.vue';
import FileTile from '@/components/records/FileTile.vue';
import FileListItem from '@/components/records/FileListItem.vue';
import FolderTileSkeleton from '@/components/records/FolderTileSkeleton.vue';
import FolderListItemSkeleton from '@/components/records/FolderListItemSkeleton.vue';
import FileTileSkeleton from '@/components/records/FileTileSkeleton.vue';
import FileListItemSkeleton from '@/components/records/FileListItemSkeleton.vue';
import UploadFileDialog from '@/components/records/UploadFileDialog.vue';
import UpdateFileDialog from '@/components/records/UpdateFileDialog.vue';

import RecordsLayout from '@/layouts/RecordsLayout.vue';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';
import RecordsSortBar from '@/components/records/RecordsSortBar.vue';
import { type BreadcrumbItem, type SelectedItemType } from '@/types';
import { useSelectionState } from '@/utils/selection';
import type { SelectionIdentity } from '@/utils/selection';
import { useToast } from '@/composables/useToast';
import { type Bookmark, type FolderSchema, type File } from '@/types/schema';
import { isMobileDevice } from '@/utils/itemClickHandlers';
import { openFolder as navigateToFolder, openFile as navigateToFile } from '@/utils/itemNavigation';
import { buildFileMetadata } from '@/utils/fileAttachment';
import { sortFolders, sortFiles, groupFiles } from '@/utils/sorting';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import { useViewMode } from '@/composables/useViewMode';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useSelectionHelpers } from '@/composables/useSelectionHelpers';


const props = defineProps<{
    bookmarks: Bookmark[];
}>();

const isLoading = ref(true);
const bookmarksList = ref<Bookmark[]>(props.bookmarks);

console.log('[Bookmarks] Component created, isLoading initial value:', isLoading.value, 'bookmarks count:', props.bookmarks.length);

const updateDialogRef = ref<InstanceType<typeof UpdateFileDialog> | null>(null);
const { error } = useToast();

onMounted(() => {
    console.log('[Bookmarks] Component mounted, isLoading:', isLoading.value);
    setTimeout(() => {
        console.log('[Bookmarks] 300ms passed, setting isLoading to false');
        isLoading.value = false;
    }, 300);
});

// Sync bookmarksList when props change (e.g., from external reloads)
watch(() => props.bookmarks, (newBookmarks) => {
    bookmarksList.value = newBookmarks;
});

const handleUnbookmarked = (itemId: string, itemType: 'folder' | 'file') => {
    bookmarksList.value = bookmarksList.value.filter(bookmark => {
        if (itemType === 'folder') {
            return !bookmark.bookmarkable_type.includes('Folder') || bookmark.bookmarkable_id !== itemId;
        }
        return !bookmark.bookmarkable_type.includes('File') || bookmark.bookmarkable_id !== itemId;
    });
};

const orderedItems = computed<SelectionIdentity[]>(() => [
    ...folderBookmarks.value.map(({ folder }) => ({ id: folder.id, type: 'folder' as const })),
    ...fileBookmarks.value.map(({ file }) => ({ id: file.id, type: 'file' as const })),
]);

const {
    selectMode,
    selectedItems,
    selectionCount,
    toggleSelectMode,
    toggleSelection: toggleSelectionIdentity,
    isSelected: isIdentitySelected,
    selectOnly,
    clearSelection,
    lastSelected,
} = useSelectionState(() => orderedItems.value);

const toSelectionIdentity = (bookmark: Bookmark): SelectionIdentity | null => {
    if (!bookmark.bookmarkable) {
        return null;
    }

    return {
        id: bookmark.bookmarkable_id,
        type: isFolder(bookmark) ? 'folder' : 'file',
    } satisfies SelectionIdentity;
};

const toggleBookmarkSelection = (bookmark: Bookmark): void => {
    const identity = toSelectionIdentity(bookmark);
    if (!identity) {
        return;
    }

    toggleSelectionIdentity(identity);
};

const handleLongPressBookmark = (bookmark: Bookmark) => {
    const identity = toSelectionIdentity(bookmark);
    if (!identity) return;

    if (!selectMode.value) toggleSelectMode();
    toggleSelectionIdentity(identity);
};

const handleBookmarkClick = (bookmark: Bookmark, event?: Event): void => {
    if (event) event.stopPropagation();

    if (selectMode.value) {
        const wasSelected = isBookmarkSelected(bookmark);
        toggleBookmarkSelection(bookmark);

        if (wasSelected) {
            toggleSelectMode();
        }

        return;
    }

    if (isMobileDevice()) {
        if (bookmark.bookmarkable) {
            if (isFolder(bookmark)) {
                openFolder(bookmark.bookmarkable.id);
            } else {
                openBookmark(bookmark);
            }
        }
    } else {
        toggleBookmarkSelection(bookmark);
    }
};

const isBookmarkSelected = (bookmark: Bookmark): boolean => {
    const identity = toSelectionIdentity(bookmark);
    if (!identity) {
        return false;
    }

    return isIdentitySelected(identity);
};

const selectBookmarkExclusively = (bookmark: Bookmark): void => {
    const identity = toSelectionIdentity(bookmark);
    if (!identity) {
        return;
    }

    if (isIdentitySelected(identity)) {
        return;
    }

    selectOnly(identity);
};

const selectedContexts = computed<SelectedItemType[]>(() => {
    return selectedItems.value
        .map(selection => {
            const bookmark = validBookmarks.value.find(bm => bm.bookmarkable && bm.bookmarkable_id === selection.id && (isFolder(bm) ? 'folder' : 'file') === selection.type);
            if (!bookmark || !bookmark.bookmarkable) return null;

            if (selection.type === 'folder') {
                const folder = bookmark.bookmarkable as FolderSchema;
                return {
                    id: folder.id,
                    type: 'folder' as const,
                    name: folder.name,
                    isBookmarked: true,
                    bookmarkId: bookmark.id,
                    currentFolderId: null,
                } satisfies SelectedItemType;
            }

            const file = bookmark.bookmarkable as File;
            return {
                id: file.id,
                type: 'file' as const,
                name: file.file_name,
                isBookmarked: true,
                bookmarkId: bookmark.id,
                currentFolderId: null,
                hasFile: Boolean(file.file_path),
            } satisfies SelectedItemType;
        })
        .filter(Boolean) as SelectedItemType[];
});

const selectedFolderContexts = computed(() => selectedContexts.value.filter(i => i.type === 'folder'));
const selectedFileContexts = computed(() => selectedContexts.value.filter(i => i.type === 'file'));

const isFolder = (bookmark: Bookmark): boolean => {
    return bookmark.bookmarkable !== null && bookmark.bookmarkable_type.includes('Folder');
};

// Filter out bookmarks where the bookmarkable item has been deleted
const validBookmarks = computed(() => {
    return bookmarksList.value.filter(bookmark => bookmark.bookmarkable !== null);
});

const folderBookmarks = computed(() => {
    return validBookmarks.value
        .filter(isFolder)
        .map(bookmark => {
            const folder = bookmark.bookmarkable as FolderSchema;

            return {
                bookmark,
                folder: {
                    ...folder,
                    is_bookmarked: true,
                    bookmark_id: bookmark.id,
                },
            };
        });
});

type BookmarkFile = File & { folder?: { id: string; name: string } };

const fileBookmarks = computed(() => {
    return validBookmarks.value
        .filter(bookmark => !isFolder(bookmark))
        .map(bookmark => {
            const file = bookmark.bookmarkable as BookmarkFile;

            return {
                bookmark,
                file: {
                    ...file,
                    is_bookmarked: true,
                    bookmark_id: bookmark.id,
                },
                folderName: file.folder?.name ?? null,
            };
        });
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bookmarks',
        description: 'All of your bookmarks',
        href: '/records/bookmarks',
    },
];

const { viewMode: folderViewMode, setViewMode: setFolderViewMode } = useViewMode('folderViewMode');
const { viewMode: fileViewMode, setViewMode: setFileViewMode } = useViewMode('fileViewMode');
const { isTouchDevice } = usePointerCapabilities();

const folderSortField = ref<SortField>('name');
const folderSortDirection = ref<SortDirection>('asc');
const fileSortField = ref<SortField>('name');
const fileSortDirection = ref<SortDirection>('asc');
const fileRecordsPlacement = ref<RecordsPlacement>('top');
const groupBy = ref<GroupBy>(null);

const sortedFolderEntries = computed(() => {
    const folders = folderBookmarks.value.map(entry => entry.folder);
    const sorted = sortFolders(folders, folderSortField.value, folderSortDirection.value);

    return sorted
        .map(f => folderBookmarks.value.find(entry => entry.folder.id === f.id))
        .filter(Boolean) as (typeof folderBookmarks.value)[0][];
});

const sortedFileEntries = computed(() => {
    const files = fileBookmarks.value.map(entry => entry.file);
    const sorted = sortFiles(files, fileSortField.value, fileSortDirection.value, fileRecordsPlacement.value);

    return sorted
        .map(f => fileBookmarks.value.find(entry => entry.file.id === f.id))
        .filter(Boolean) as (typeof fileBookmarks.value)[0][];
});

const { resolveSelectState, applySectionSelection } = useSelectionHelpers({
    isIdentitySelected,
    selectOnly,
    selectedItems,
    selectMode,
    lastSelected,
    selectionCount,
    toggleSelectionIdentity,
    toggleSelectMode,
    isTouchDevice,
});

const folderSelectState = computed(() =>
    resolveSelectState(sortedFolderEntries.value.map(entry => ({ id: entry!.folder.id, type: 'folder' as const })))
);

const fileSelectState = computed(() =>
    resolveSelectState(sortedFileEntries.value.map(entry => ({ id: entry!.file.id, type: 'file' as const })))
);

const toggleSelectAllFolders = (): void => {
    if (sortedFolderEntries.value.length === 0) return;

    if (folderSelectState.value === 'checked') {
        applySectionSelection('folder', []);
        return;
    }

    const identities = sortedFolderEntries.value.map(entry => ({ id: entry!.folder.id, type: 'folder' as const }));
    applySectionSelection('folder', identities);
};

const toggleSelectAllFiles = (): void => {
    if (sortedFileEntries.value.length === 0) return;

    if (fileSelectState.value === 'checked') {
        applySectionSelection('file', []);
        return;
    }

    const identities = sortedFileEntries.value.map(entry => ({ id: entry!.file.id, type: 'file' as const }));
    applySectionSelection('file', identities);
};

const sortedFilesArray = computed(() => sortedFileEntries.value.map(e => e!.file));

const groupedFiles = computed(() => (fileViewMode.value === 'tiles' ? groupFiles(sortedFilesArray.value, groupBy.value) : []));

const findBookmarkForFile = (fileId: string) => {
    return validBookmarks.value.find(b => b.bookmarkable && !isFolder(b) && (b.bookmarkable as File).id === fileId);
};


const openBookmark = (bookmark: Bookmark) => {
    if (!bookmark.bookmarkable) return;

    if (isFolder(bookmark)) {
        openFolder(bookmark.bookmarkable.id);
        return;
    }

    const file = bookmark.bookmarkable as File;
    navigateToFile(file.id, file, error);
};

const openFolder = (folderId: string | undefined) => {
    if (folderId) {
        navigateToFolder(folderId, '/records');
    }
};

const handleAttachFile = (file: File) => {
    updateDialogRef.value?.prefillMetadata(buildFileMetadata(file));
};

const handleFolderContextMenu = (bookmark: Bookmark): void => {
    selectBookmarkExclusively(bookmark);
};

const handleFileContextMenu = (bookmark: Bookmark): void => {
    selectBookmarkExclusively(bookmark);
};

</script>

<template>

    <Head title="Bookmarks" />

    <RecordsLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6" @click="clearSelection">
            <div v-if="folderBookmarks.length > 0 || fileBookmarks.length > 0 || isLoading" class="flex flex-col gap-8">
                <div v-if="folderBookmarks.length > 0 || isLoading">
                    <h2 class="text-lg font-semibold mb-3">Folders</h2>
                    <ViewModeContainer :view-mode="folderViewMode">
                        <template #tiles>
                            <div class="space-y-4 pt-6 ">
                                <RecordsSortBar
                                :view-mode="folderViewMode"
                                :sort-field="folderSortField"
                                :sort-direction="folderSortDirection"
                                :group-by="groupBy"
                                name-label="Folder Name"
                                :show-grouping="true"
                                :show-size-option="false"
                                :show-size-column="false"
                                :show-type-option="false"
                                :show-actions-column="false"
                                :show-version-column="false"
                                :show-select-control="true"
                                size-label="Items"
                                :select-state="folderSelectState"
                                :force-select-visible="isTouchDevice"
                                :selections="selectedFolderContexts"
                                :selection-count="selectedFolderContexts.length"
                                @update:sort-field="folderSortField = $event"
                                @update:sort-direction="folderSortDirection = $event"
                                @update:group-by="groupBy = $event"
                                @update:view-mode="setFolderViewMode"
                                @toggle-select-all="toggleSelectAllFolders"
                                @toggle-select="toggleSelectMode"
                            />
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    <template v-if="isLoading">
                                        <FolderTileSkeleton v-for="i in 8" :key="`folder-skeleton-${i}`" />
                                    </template>
                                    <template v-else>
                                        <FolderTile v-for="entry in sortedFolderEntries" :key="entry.bookmark.id"
                                            :folder="entry.folder" :is-selected="isBookmarkSelected(entry.bookmark)"
                                            :current-folder-id="null" :selected-items="selectedContexts"
                                            :selection-count="selectionCount"
                                            @click="(e) => handleBookmarkClick(entry.bookmark, e)"
                                            @contextmenu="handleFolderContextMenu(entry.bookmark)"
                                            @dblclick="openFolder(entry.folder.id)" @unbookmarked="handleUnbookmarked"
                                            @longpress="() => handleLongPressBookmark(entry.bookmark)">

                                        </FolderTile>
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
                                        :group-by="groupBy"
                                        name-label="Folder Name"
                                        :show-grouping="true"
                                        :show-size-option="true"
                                        :show-size-column="true"
                                        :show-type-option="false"
                                        :show-actions-column="true"
                                        :show-status-column="true"
                                        :show-status-label="false"
                                        :show-version-column="true"
                                        :show-version-label="false"
                                        :show-select-control="false"
                                        size-label="Items"
                                        :force-select-visible="isTouchDevice"
                                        :selections="selectedFolderContexts"
                                        :selection-count="selectedFolderContexts.length"
                                        @update:sort-field="folderSortField = $event"
                                        @update:sort-direction="folderSortDirection = $event"
                                        @update:group-by="groupBy = $event"
                                        @update:view-mode="setFolderViewMode"
                                        @toggle-select="toggleSelectMode"
                                    />
                                </div>

                                <div>
                                    <template v-if="isLoading">
                                        <FolderListItemSkeleton v-for="i in 6" :key="`folder-list-skeleton-${i}`" />
                                    </template>
                                    <template v-else>
                                        <FolderListItem v-for="entry in sortedFolderEntries" :key="entry.bookmark.id"
                                            :folder="entry.folder" :is-selected="isBookmarkSelected(entry.bookmark)"
                                            :current-folder-id="null" :selected-items="selectedContexts"
                                            :selection-count="selectionCount"
                                            :show-status-spacer="true"
                                            :show-version-spacer="true"
                                            @click="(e) => handleBookmarkClick(entry.bookmark, e)"
                                            @contextmenu="handleFolderContextMenu(entry.bookmark)"
                                            @dblclick="openFolder(entry.folder.id)" @unbookmarked="handleUnbookmarked"
                                            @longpress="() => handleLongPressBookmark(entry.bookmark)">

                                        </FolderListItem>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </ViewModeContainer>
                </div>

                <div v-if="fileBookmarks.length > 0 || isLoading">
                    <h2 class="text-lg font-semibold mb-3">Files</h2>
                    <ViewModeContainer :view-mode="fileViewMode">
                        <template #tiles>
                            <div class="space-y-4">
                                <RecordsSortBar
                                :view-mode="fileViewMode"
                                :sort-field="fileSortField"
                                :sort-direction="fileSortDirection"
                                :group-by="groupBy"
                                :show-records-placement="true"
                                :records-placement="fileRecordsPlacement"
                                :show-select-control="true"
                                :select-state="fileSelectState"
                                :force-select-visible="isTouchDevice"
                                :selections="selectedFileContexts"
                                :selection-count="selectedFileContexts.length"
                                @update:sort-field="fileSortField = $event"
                                @update:sort-direction="fileSortDirection = $event"
                                @update:group-by="groupBy = $event"
                                @update:records-placement="fileRecordsPlacement = $event"
                                @update:view-mode="setFileViewMode"
                                @toggle-select-all="toggleSelectAllFiles"
                                @toggle-select="toggleSelectMode"
                            />
                                <template v-if="isLoading">
                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        <FileTileSkeleton v-for="i in 8" :key="`file-skeleton-${i}`" />
                                    </div>
                                </template>

                                <template v-else-if="groupBy && groupedFiles.length">
                                    <div v-for="group in groupedFiles" :key="group.key" class="space-y-2">
                                        <div class="text-sm font-medium text-muted-foreground">{{ group.key }}</div>
                                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                            <FileTile
                                                v-for="file in group.items"
                                                :key="file.id"
                                                :file="file"
                                                :is-selected="isBookmarkSelected(findBookmarkForFile(file.id)!)"
                                                :current-folder-id="null"
                                                :current-folder-name="(findBookmarkForFile(file.id)?.bookmarkable as any)?.folder?.name ?? null"
                                                :selected-items="selectedContexts"
                                                :selection-count="selectionCount"
                                                @click="(e) => handleBookmarkClick(findBookmarkForFile(file.id)!, e)"
                                                @contextmenu="handleFileContextMenu(findBookmarkForFile(file.id)!)"
                                                @dblclick="openBookmark(findBookmarkForFile(file.id)!)"
                                                @unbookmarked="handleUnbookmarked"
                                                @longpress="() => handleLongPressBookmark(findBookmarkForFile(file.id)!)"
                                                @attachfile="handleAttachFile(file)">
                                            </FileTile>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        <FileTile v-for="entry in sortedFileEntries" :key="entry.bookmark.id" :file="entry.file"
                                            :is-selected="isBookmarkSelected(entry.bookmark)" :current-folder-id="null"
                                            :current-folder-name="entry.folderName" :selected-items="selectedContexts"
                                            :selection-count="selectionCount"
                                            @click="(e) => handleBookmarkClick(entry.bookmark, e)"
                                            @contextmenu="handleFileContextMenu(entry.bookmark)"
                                            @dblclick="openBookmark(entry.bookmark)" @unbookmarked="handleUnbookmarked"
                                            @longpress="() => handleLongPressBookmark(entry.bookmark)"
                                            @attachfile="handleAttachFile(entry.file)">
                                        </FileTile>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template #list>
                            <div class="w-full relative">
                                <div class="mb-2 sticky top-0 pt-6 bg-background">
                                    <RecordsSortBar
                                        :view-mode="fileViewMode"
                                        :sort-field="fileSortField"
                                        :sort-direction="fileSortDirection"
                                        :group-by="groupBy"
                                        :show-records-placement="true"
                                        :records-placement="fileRecordsPlacement"
                                        :show-status-column="true"
                                        :show-grouping="false"
                                        :show-type-option="false"
                                        :show-actions-column="false"
                                        :show-version-column="true"
                                        :show-select-control="false"
                                        :force-select-visible="isTouchDevice"
                                        :selections="selectedFileContexts"
                                        :selection-count="selectedFileContexts.length"
                                        @update:sort-field="fileSortField = $event"
                                        @update:sort-direction="fileSortDirection = $event"
                                        @update:group-by="groupBy = $event"
                                        @update:records-placement="fileRecordsPlacement = $event"
                                        @update:view-mode="setFileViewMode"
                                        @toggle-select="toggleSelectMode"
                                    />
                                </div>

                                <div>
                                    <template v-if="isLoading">
                                        <FileListItemSkeleton v-for="i in 6" :key="`file-list-skeleton-${i}`" />
                                    </template>
                                    <template v-else>
                                        <FileListItem v-for="entry in sortedFileEntries" :key="entry.bookmark.id"
                                            :file="entry.file" :is-selected="isBookmarkSelected(entry.bookmark)"
                                            :current-folder-id="null" :current-folder-name="entry.folderName"
                                            :selected-items="selectedContexts" :selection-count="selectionCount"
                                            @click="(e) => handleBookmarkClick(entry.bookmark, e)"
                                            @contextmenu="handleFileContextMenu(entry.bookmark)"
                                            @dblclick="openBookmark(entry.bookmark)" @unbookmarked="handleUnbookmarked"
                                            @longpress="() => handleLongPressBookmark(entry.bookmark)"
                                            @attachfile="handleAttachFile(entry.file)">
                                        </FileListItem>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </ViewModeContainer>
                </div>
            </div>

            <div v-else-if="!isLoading" class="flex flex-col items-center justify-center py-20 text-center">
                <FolderIcon class="h-24 w-24 text-muted-foreground/50 mb-4 " />
                <h2 class="text-xl font-semibold mb-2">No Bookmarks Yet</h2>
                <p class="text-muted-foreground max-w-md">
                    Bookmark your favorite folders and files for quick access. Use the three-dot menu on any item to add
                    it to
                    bookmarks.
                </p>
            </div>
        </div>

        <UploadFileDialog ref="uploadDialogRef" :folder-id="null" />
        <UpdateFileDialog ref="updateDialogRef" :folder-id="null" />
    </RecordsLayout>
</template>
