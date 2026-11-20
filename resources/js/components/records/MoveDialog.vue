<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { apiClient } from '@/lib/axios';
import { Folder, FolderOpen, FolderPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as listBookmarks } from '@/routes/api/bookmarks';
import { store as storeFolderRoute } from '@/routes/api/folders';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';
import { usePermissions } from '@/utils/permission';

const { error, success } = useToast();
const { confirm: showConfirm } = useConfirm();
const { canAny } = usePermissions();

const canReadFolders = computed(() =>
    canAny('organizeContent', 'deleteContent', 'manageRequests', 'reviewContent')
);

interface Folder {
    id: string;
    name: string;
    parent_id: string | null;
    created_at?: string;
    updated_at?: string;
    is_bookmarked?: boolean;
}

interface MoveItem {
    id: string;
    type: 'folder' | 'file';
    name: string;
    currentFolderId?: string | null;
}

interface BookmarkEntry {
    id: string;
    bookmarkable_id: string;
    bookmarkable_type: string;
    bookmarkable?: Folder | null;
}

interface BreadcrumbSegment {
    folder: Folder;
    index: number;
}

const props = defineProps<{
    itemId?: string;
    itemType?: 'folder' | 'file';
    itemName?: string;
    currentFolderId?: string | null;
    items?: MoveItem[];
}>();

type TabKey = 'bookmarked' | 'all';

const open = defineModel<boolean>('open', { default: false });
const folders = ref<Folder[]>([]);
const selectedFolderId = ref<string | null>(null);
const currentPath = ref<Folder[]>([]);
const loading = ref(false);
const moving = ref(false);
const activeTab = ref<TabKey>('all');
const showNewFolderForm = ref(false);
const creatingFolder = ref(false);
const newFolderName = ref('');
const newFolderError = ref<string | null>(null);
const bookmarkedFolders = ref<Folder[]>([]);
const bookmarkedLoading = ref(false);
const bookmarkedError = ref<string | null>(null);
const hasLoadedBookmarks = ref(false);

const itemsToMove = computed<MoveItem[]>(() => {
    if (props.items && props.items.length > 0) {
        return props.items.map(item => ({
            id: item.id,
            type: item.type,
            name: item.name,
            currentFolderId: item.currentFolderId ?? null,
        }));
    }

    if (props.itemId && props.itemType && props.itemName) {
        return [
            {
                id: props.itemId,
                type: props.itemType,
                name: props.itemName,
                currentFolderId: props.currentFolderId ?? null,
            },
        ];
    }

    return [];
});

const dialogTitle = computed(() => {
    const count = itemsToMove.value.length;
    if (count === 0) return 'Move items';
    if (count === 1) return `Move ${itemsToMove.value[0].name}`;
    return `Move ${count} items`;
});

const dialogDescription = computed(() => {
    return itemsToMove.value.length > 1
        ? 'Select a destination folder for the selected items'
        : 'Select a destination folder';
});

const loadFolders = async (parentId: string | null = null): Promise<Folder[]> => {
    if (!canReadFolders.value) {
        console.warn('User does not have permission to read folders');
        folders.value = [];
        return [];
    }

    loading.value = true;
    try {
        const params = parentId ? `?parent_id=${parentId}&per_page=all` : '?parent_id=null&per_page=all';
        const response = await apiClient.get<{ data?: Folder[] } | Folder[]>(`/api/folders${params}`);
        folders.value = (response.data as any).data || response.data;
        return folders.value;
    } catch (err: any) {
        console.error('Failed to load folders:', err);
        error('Failed to load folders', err?.response?.data?.message || 'Please try again.');
        folders.value = [];
        return [];
    } finally {
        loading.value = false;
    }
};

const loadBookmarkedFolders = async () => {
    bookmarkedLoading.value = true;
    bookmarkedError.value = null;
    try {
        const response = await apiClient.get<{ data?: any } | any>(listBookmarks({ query: { per_page: 100 } }).url);
        const payload = (response.data as any).data || response.data;
        const entries: BookmarkEntry[] = Array.isArray(payload)
            ? payload
            : (payload?.data ?? []);

        bookmarkedFolders.value = entries
            .filter(entry => entry.bookmarkable && entry.bookmarkable_type.includes('Folder'))
            .map(entry => ({
                ...(entry.bookmarkable as Folder),
                is_bookmarked: true,
                bookmark_id: entry.id,
            }));
        hasLoadedBookmarks.value = true;
    } catch (err: any) {
        console.error('Bookmarks load error:', err);
        bookmarkedError.value = err.response?.data?.message || 'Failed to load bookmarks.';
        bookmarkedFolders.value = [];
    } finally {
        bookmarkedLoading.value = false;
    }
};

const updateSelectionAfterLoad = (folderId: string | null, childrenCount: number) => {
    if (!folderId) {
        selectedFolderId.value = null;
        return;
    }

    selectedFolderId.value = childrenCount === 0 ? folderId : null;
};

const fetchFolderById = async (id: string): Promise<Folder | null> => {
    if (!canReadFolders.value) {
        return null;
    }

    try {
        const response = await apiClient.get<{ data?: Folder } | Folder>(`/api/folders/${id}`);
        return (response.data as any).data || response.data;
    } catch (err) {
        console.error('Failed to fetch folder', err);
        return null;
    }
};

const buildFolderPath = async (folder: Folder): Promise<Folder[]> => {
    const path: Folder[] = [];
    const visited = new Set<string>();
    let current: Folder | null = folder;

    while (current) {
        if (visited.has(current.id)) {
            break;
        }
        visited.add(current.id);
        path.unshift(current);

        if (!current.parent_id) {
            break;
        }

        current = await fetchFolderById(current.parent_id);
    }

    return path;
};

const openFolder = async (folder: Folder) => {
    currentPath.value.push(folder);
    const children = await loadFolders(folder.id);
    updateSelectionAfterLoad(folder.id, children.length);
};

const goBack = async () => {
    currentPath.value.pop();
    const parent = currentPath.value[currentPath.value.length - 1];
    const children = await loadFolders(parent?.id || null);
    updateSelectionAfterLoad(parent?.id || null, children.length);
};

const goToRoot = async () => {
    currentPath.value = [];
    const children = await loadFolders(null);
    updateSelectionAfterLoad(null, children.length);
};

const goToBreadcrumb = async (index: number, folder: Folder) => {
    currentPath.value = currentPath.value.slice(0, index + 1);
    const children = await loadFolders(folder.id);
    updateSelectionAfterLoad(folder.id, children.length);
};

const jumpToFolder = async (folder: Folder) => {
    const path = await buildFolderPath(folder);
    currentPath.value = path;
    const children = await loadFolders(folder.id);
    updateSelectionAfterLoad(folder.id, children.length);
    activeTab.value = 'all';
};

const currentFolder = computed(() => currentPath.value[currentPath.value.length - 1] || null);
const currentDestinationParentId = computed(() => currentFolder.value?.id ?? null);
const allTabLabel = computed(() => currentFolder.value?.name ? `Inside ${currentFolder.value.name}` : 'All folders');

const destinationIsRoot = computed(() => selectedFolderId.value === null);

const pathSegments = computed<BreadcrumbSegment[]>(() =>
    currentPath.value.map((folder, index) => ({ folder, index }))
);

const showCollapsedBreadcrumbs = computed(() => pathSegments.value.length > 2);

const collapsedSegments = computed<BreadcrumbSegment[]>(() => {
    if (!showCollapsedBreadcrumbs.value) {
        return [];
    }

    return pathSegments.value.slice(0, -2);
});

const visibleSegments = computed<BreadcrumbSegment[]>(() => {
    if (pathSegments.value.length <= 2) {
        return pathSegments.value;
    }

    return pathSegments.value.slice(-2);
});

const movingFolderIds = computed(() =>
    new Set(
        itemsToMove.value
            .filter(item => item.type === 'folder')
            .map(item => item.id)
    )
);

const availableFolders = computed(() =>
    folders.value.filter(folder => !movingFolderIds.value.has(folder.id))
);

const filteredBookmarkedFolders = computed(() =>
    bookmarkedFolders.value.filter(folder => !movingFolderIds.value.has(folder.id))
);

const displayedFolders = computed(() => {
    if (activeTab.value === 'bookmarked') {
        return filteredBookmarkedFolders.value;
    }

    return availableFolders.value;
});

const tabEmptyMessage = computed(() => {
    if (activeTab.value === 'bookmarked') {
        return 'No bookmarked folders here';
    }

    if (!canReadFolders.value) {
        return 'You don\'t have permission to view folders';
    }

    if (currentPath.value.length === 0) {
        return 'No folders available. Create a new folder to get started.';
    }

    return 'No subfolders here';
});

const isTabLoading = computed(() => activeTab.value === 'bookmarked' ? bookmarkedLoading.value : loading.value);

const resetNewFolderForm = () => {
    newFolderName.value = '';
    newFolderError.value = null;
    creatingFolder.value = false;
};

const buildNextFileName = (currentName: string, existingFiles: any[]): string => {
    const parts = currentName.match(/^(.+?)(\.[^.]+)?$/);
    const baseName = parts?.[1] ?? currentName;
    const extension = parts?.[2] ?? '';

    let counter = 1;
    let candidate = `${baseName} (${counter})${extension}`;

    while (existingFiles.some((f: any) => f.file_name === candidate)) {
        counter++;
        candidate = `${baseName} (${counter})${extension}`;
    }

    return candidate;
};

const handleFileMoveApiDuplicate = async (
    err: any,
    item: MoveItem,
    destinationFolderId: string | null,
    folderName: string,
    existingFiles: any[],
): Promise<boolean> => {
    if (err?.response?.data?.code !== 'duplicate_filename') {
        throw err;
    }

    const suggestedName = err.response?.data?.suggested_name as string | undefined;
    const duplicateId = err.response?.data?.duplicate_id as string | undefined;

    const choice = await showConfirm({
        title: 'Duplicate File Name',
        description: `A file named "${item.name}" already exists in ${folderName}. What would you like to do?`,
        replaceText: 'Replace existing',
        confirmText: 'Rename automatically',
        cancelText: 'Cancel'
    });

    if (choice === 'replace' && duplicateId) {
        await apiClient.patch(`/api/files/${item.id}/move`, {
            folder_id: destinationFolderId,
            strategy: 'replace'
        });

        const existingIndex = existingFiles.findIndex((f: any) => f.id === duplicateId);
        if (existingIndex !== -1) {
            existingFiles.splice(existingIndex, 1);
        }

        return true;
    }

    if (choice === 'confirm') {
        const nextName = suggestedName ?? buildNextFileName(item.name, existingFiles);

        await apiClient.patch(`/api/files/${item.id}/move`, {
            folder_id: destinationFolderId,
            strategy: 'rename',
            new_name: nextName
        });

        const existingIndex = existingFiles.findIndex((f: any) => f.id === item.id);
        if (existingIndex !== -1) {
            existingFiles[existingIndex].file_name = nextName;
        } else {
            existingFiles.push({ id: item.id, file_name: nextName });
        }

        item.name = nextName;
        return true;
    }

    return false;
};

const toggleNewFolderForm = () => {
    if (creatingFolder.value) {
        return;
    }

    if (showNewFolderForm.value) {
        showNewFolderForm.value = false;
        resetNewFolderForm();
        return;
    }

    showNewFolderForm.value = true;
    newFolderName.value = '';
    newFolderError.value = null;
};

const createDestinationFolder = async () => {
    const trimmedName = newFolderName.value.trim();

    if (trimmedName === '') {
        newFolderError.value = 'Folder name is required.';
        return;
    }

    creatingFolder.value = true;
    newFolderError.value = null;

    try {
        const response = await apiClient.post<{ id?: string }>(storeFolderRoute().url, {
            name: trimmedName,
            parent_id: currentDestinationParentId.value,
        });

        await loadFolders(currentDestinationParentId.value);
        selectedFolderId.value = response.data?.id ?? null;
        showNewFolderForm.value = false;
        newFolderName.value = '';
    } catch (err: any) {
        newFolderError.value = err.response?.data?.message
            || err.response?.data?.errors?.name?.[0]
            || 'Unable to create folder.';
    } finally {
        creatingFolder.value = false;
    }
};

const movableItems = computed(() => {
    return itemsToMove.value.filter(item => {
        if (item.type === 'file' && destinationIsRoot.value) return false;
        if (item.type === 'folder' && item.id === selectedFolderId.value) return false;
        return item.currentFolderId !== selectedFolderId.value;
    });
});

const canMove = computed(() => movableItems.value.length > 0);

const pluralize = (count: number, word: string): string => (count === 1 ? word : `${word}s`);

const moveItem = async () => {
    if (!canMove.value) return;

    moving.value = true;
    const destinationFolderId = selectedFolderId.value;
    const folderName = currentPath.value.length > 0
        ? currentPath.value[currentPath.value.length - 1].name
        : 'All Records';

    try {
        for (const item of movableItems.value) {
            if (item.type === 'file') {
                const params = destinationFolderId ? `?folder_id=${destinationFolderId}` : '?folder_id=null';
                const response = await apiClient.get<{ data?: any[] } | any[]>(`/api/files${params}`);
                const existingFiles = (response.data as any).data || response.data;

                const duplicateFile = existingFiles.find((f: any) =>
                    f.file_name === item.name &&
                    f.id !== item.id
                );

                if (duplicateFile) {
                    const userChoice = await showConfirm({
                        title: 'Duplicate File Name',
                        description: `A file named "${item.name}" already exists in ${folderName}. What would you like to do?`,
                        replaceText: 'Replace existing',
                        confirmText: 'Rename automatically',
                        cancelText: 'Cancel'
                    });

                    if (userChoice === 'cancel') {
                        continue;
                    }

                    if (userChoice === 'replace') {
                        await apiClient.delete(`/api/files/${duplicateFile.id}`);
                        await apiClient.patch(`/api/files/${item.id}/move`, {
                            folder_id: destinationFolderId
                        });
                        continue;
                    }

                    const nameParts = item.name.match(/^(.+?)(\.[^.]*)?$/);
                    const baseName = nameParts ? nameParts[1] : item.name;
                    const extension = nameParts && nameParts[2] ? nameParts[2] : '';

                    let counter = 1;
                    let newFileName = `${baseName} (${counter})${extension}`;

                    while (existingFiles.some((f: any) => f.file_name === newFileName)) {
                        counter++;
                        newFileName = `${baseName} (${counter})${extension}`;
                    }

                    await apiClient.patch(`/api/files/${item.id}/move`, {
                        folder_id: destinationFolderId,
                        new_name: newFileName
                    });
                    continue;
                }

                try {
                    await apiClient.patch(`/api/files/${item.id}/move`, {
                        folder_id: destinationFolderId
                    });
                } catch (err: any) {
                    const resolved = await handleFileMoveApiDuplicate(err, item, destinationFolderId, folderName, existingFiles);

                    if (!resolved) {
                        continue;
                    }
                }
            } else {
                const params = destinationFolderId ? `?parent_id=${destinationFolderId}&per_page=all` : '?parent_id=null&per_page=all';
                const response = await apiClient.get<{ data?: Folder[] } | Folder[]>(`/api/folders${params}`);
                const existingFolders = (response.data as any).data || response.data;

                const duplicateFolder = existingFolders.find((f: any) =>
                    f.name === item.name &&
                    f.id !== item.id
                );

                if (duplicateFolder) {
                    const userChoice = await showConfirm({
                        title: 'Duplicate Folder Name',
                        description: `A folder named "${item.name}" already exists in ${folderName}. What would you like to do?`,
                        confirmText: 'Rename automatically',
                        cancelText: 'Cancel'
                    });

                    if (userChoice === 'cancel') {
                        continue;
                    }

                    if (userChoice === 'confirm') {
                        let counter = 1;
                        let newFolderName = `${item.name} (${counter})`;

                        while (existingFolders.some((f: any) => f.name === newFolderName)) {
                            counter++;
                            newFolderName = `${item.name} (${counter})`;
                        }

                        await apiClient.patch(`/api/folders/${item.id}/move`, {
                            parent_id: destinationFolderId,
                            new_name: newFolderName
                        });
                        continue;
                    }
                }

                await apiClient.patch(`/api/folders/${item.id}/move`, {
                    parent_id: destinationFolderId
                });
            }
        }

        const movedCount = movableItems.value.length;
        success('Moved successfully', `${movedCount} ${pluralize(movedCount, 'item')} moved to ${folderName}.`);
        open.value = false;
        router.reload({ only: ['folders', 'files'] });
    } catch (err: any) {
        console.error('Move error:', err);
        console.error('Response:', err.response);
        const errorMessage = err.response?.data?.message || err.message || 'Please try again.';
        error('Failed to move', errorMessage);
    } finally {
        moving.value = false;
    }
};

watch(open, async (isOpen) => {
    if (isOpen) {
        currentPath.value = [];
        selectedFolderId.value = null;
        showNewFolderForm.value = false;
        resetNewFolderForm();
        activeTab.value = 'all';
        bookmarkedFolders.value = [];
        hasLoadedBookmarks.value = false;
        await Promise.all([loadFolders(null), loadBookmarkedFolders()]);
    }
});

watch(activeTab, (tab) => {
    if (tab !== 'all' && showNewFolderForm.value) {
        showNewFolderForm.value = false;
        resetNewFolderForm();
    }

    if (tab === 'bookmarked' && !hasLoadedBookmarks.value && !bookmarkedLoading.value) {
        loadBookmarkedFolders();
    }

    if (tab === 'bookmarked') {
        selectedFolderId.value = null;
    }
});

const selectCurrentLocation = () => {
    selectedFolderId.value = currentFolder.value?.id || null;
};

const handleFolderClick = async (folder: Folder) => {
    if (activeTab.value === 'bookmarked') {
        try {
            await jumpToFolder(folder);
        } catch (err) {
            console.error('Failed to jump to bookmarked folder', err);
            error('Unable to open folder', 'Please try again.');
        }
        return;
    }

    await openFolder(folder);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ dialogDescription }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Breadcrumb + new folder action -->
                <div class="flex flex-wrap items-center gap-2">
                    <Breadcrumb class="flex-1 min-w-0">
                        <BreadcrumbList class="text-sm text-muted-foreground">
                            <BreadcrumbItem>
                                <BreadcrumbLink as-child>
                                    <button type="button" class="hover:text-foreground transition-colors"
                                        @click="goToRoot">
                                        All Records
                                    </button>
                                </BreadcrumbLink>
                            </BreadcrumbItem>

                            <BreadcrumbSeparator v-if="pathSegments.length > 0" />

                            <template v-if="showCollapsedBreadcrumbs">
                                <BreadcrumbItem>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <BreadcrumbEllipsis
                                                class="text-muted-foreground hover:text-foreground transition-colors" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start" class="min-w-48">
                                            <DropdownMenuItem v-for="segment in collapsedSegments"
                                                :key="segment.folder.id" class="cursor-pointer"
                                                @click="goToBreadcrumb(segment.index, segment.folder)">
                                                {{ segment.folder.name }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </BreadcrumbItem>

                                <BreadcrumbSeparator />
                            </template>

                            <template v-for="(segment, index) in visibleSegments" :key="segment.folder.id">
                                <BreadcrumbItem v-if="index < visibleSegments.length - 1">
                                    <BreadcrumbLink as-child>
                                        <button type="button" class="hover:text-foreground transition-colors"
                                            @click="goToBreadcrumb(segment.index, segment.folder)">
                                            {{ segment.folder.name }}
                                        </button>
                                    </BreadcrumbLink>
                                </BreadcrumbItem>
                                <BreadcrumbItem v-else>
                                    <BreadcrumbPage class="text-foreground font-medium">
                                        {{ segment.folder.name }}
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                                <BreadcrumbSeparator v-if="index < visibleSegments.length - 1" />
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                <!-- Destination tabs -->
                <div class="flex items-center gap-6 border-b border-border text-sm font-medium">
                    <button type="button" class="relative pb-2 text-muted-foreground transition-colors"
                        :class="{ 'text-primary': activeTab === 'bookmarked' }" @click="activeTab = 'bookmarked'">
                        Bookmarked
                        <span v-if="activeTab === 'bookmarked'"
                            class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-primary" />
                    </button>
                    <button type="button" class="relative pb-2 text-muted-foreground transition-colors"
                        :class="{ 'text-primary': activeTab === 'all' }" @click="activeTab = 'all'">
                        {{ allTabLabel }}
                        <span v-if="activeTab === 'all'"
                            class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-primary" />
                    </button>
                </div>

                <!-- Inline new folder form -->
                <form v-if="activeTab === 'all' && showNewFolderForm" class="space-y-3 rounded-lg border p-3"
                    @submit.prevent="createDestinationFolder">
                    <Input v-model="newFolderName" placeholder="Folder name" :disabled="creatingFolder"
                        @keyup.enter.prevent="createDestinationFolder" />
                    <p v-if="newFolderError" class="text-sm text-destructive">
                        {{ newFolderError }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="ghost" size="sm" :disabled="creatingFolder"
                            @click="toggleNewFolderForm">
                            Cancel
                        </Button>
                        <Button type="submit" size="sm" :disabled="creatingFolder">
                            {{ creatingFolder ? 'Creating...' : 'Create' }}
                        </Button>
                    </div>
                </form>

                <!-- Current location button -->
                <Button v-if="activeTab === 'all'" variant="outline" class="w-full justify-start"
                    :class="{ 'ring-2 ring-primary': selectedFolderId === (currentFolder?.id || null) }"
                    @click="selectCurrentLocation">
                    <Folder class="mr-2 h-4 w-4" />
                    Select this location
                </Button>

                <!-- Folder list -->
                <div class="border rounded-lg max-h-96 overflow-y-auto">
                    <div v-if="isTabLoading" class="p-8 text-center text-muted-foreground">
                        Loading {{ activeTab === 'bookmarked' ? 'bookmarks' : 'folders' }}...
                    </div>
                    <div v-else-if="activeTab === 'bookmarked' && bookmarkedError"
                        class="p-8 text-center text-destructive">
                        {{ bookmarkedError }}
                    </div>
                    <div v-else-if="displayedFolders.length === 0" class="p-8 text-center text-muted-foreground">
                        {{ tabEmptyMessage }}
                    </div>
                    <div v-else class="divide-y">
                        <button v-for="folder in displayedFolders" :key="folder.id"
                            class="w-full flex items-center gap-3 p-3 hover:bg-accent transition-colors text-left group"
                            :class="{
                                'bg-accent ring-1 ring-primary': selectedFolderId === folder.id
                            }" @click="handleFolderClick(folder)">
                            <Folder class="h-5 w-5 text-blue-500 group-hover:hidden" />
                            <FolderOpen class="h-5 w-5 text-blue-600 hidden group-hover:block" />
                            <span class="flex-1 font-medium">{{ folder.name }}</span>
                            <span class="text-xs text-muted-foreground">›</span>
                        </button>
                    </div>
                </div>

                <!-- Back button -->
                <Button v-if="currentPath.length > 0" variant="ghost"
                    class="px-0 text-sm font-medium text-muted-foreground hover:text-foreground" @click="goBack">
                    ← Go back
                </Button>
            </div>

            <DialogFooter class="flex w-full flex-wrap items-center gap-3">
                <Button v-if="activeTab === 'all'" type="button" variant="outline" size="sm" class="shrink-0"
                    :disabled="creatingFolder" @click="toggleNewFolderForm">
                    <FolderPlus class="mr-2 h-4 w-4" />
                    {{ showNewFolderForm ? 'Cancel new folder' : 'New folder' }}
                </Button>
                <div class="ml-auto flex gap-2">
                    <Button variant="outline" @click="open = false">
                        Cancel
                    </Button>
                    <Button :disabled="!canMove || moving" @click="moveItem">
                        {{ moving ? 'Moving...' : 'Move here' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
