<script setup lang="ts">
import { computed } from 'vue';
import { Bookmark, Download, FolderInput, Info, Paperclip, RotateCcw, SquarePen, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuPortal,
    ContextMenuRoot,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from 'reka-ui';
import ItemMetadataSheet from '@/components/records/ItemMetadataSheet.vue';
import MoveDialog from './MoveDialog.vue';
import RenameDialog from '@/components/records/RenameDialog.vue';
import { useItemActions, type ItemActionProps, type ItemActionType } from '@/composables/useItemActions';
import type { SelectedItemType } from '@/types';
import {
    deduplicateItems,
    deleteMultipleItems,
    handleDownload as handleSelectionDownload,
    restoreMultipleItems,
    toggleMultipleBookmarks,
} from '@/utils/actions';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';

const props = withDefaults(defineProps<ItemActionProps & {
    mode?: 'default' | 'trash';
    selectedItems?: SelectedItemType[];
    selectionCount?: number;
    triggerType?: 'button' | 'context';
    inline?: boolean;
}>(), {
    mode: 'default',
    selectedItems: () => [],
    selectionCount: 0,
    triggerType: 'button',
    inline: false,
});

const emit = defineEmits<{
    unbookmarked: [itemId: string, itemType: ItemActionType];
    renamed: [itemId: string, itemType: ItemActionType, newName: string];
    'attach-file': [itemId: string, itemType: ItemActionType];
    'show-versions': [itemId: string];
    restore: [{ id: string; type: ItemActionType }];
    'delete-permanent': [{ id: string; type: ItemActionType }];
}>();

const { confirm: showConfirm } = useConfirm?.() ?? { confirm: async () => true };
// useToast may not be available in context where this file is imported for tests
const { success, error } = useToast?.() ?? { success: () => { }, error: () => { } };

const toTitleCase = (value: string): string => value.replace(/\b\w+/g, word => word.charAt(0).toUpperCase() + word.slice(1));

const {
    showMoveDialog,
    showRenameDialog,
    showInfoSheet,
    hasMetadata,
    metadataEntries,
    attachLabel,
    handleAttach,
    handleDelete: handleDeleteSingle,
    handleDownload: handleDownloadSingle,
    handleToggleBookmark: handleToggleBookmarkSingle,
    onRenamed,
    can,
    isBookmarksPage,
} = useItemActions(props, {
    unbookmarked: (id, type) => emit('unbookmarked', id, type),
    renamed: (id, type, newName) => emit('renamed', id, type, newName),
    attachFile: (id, type) => emit('attach-file', id, type),
    showVersions: (id) => emit('show-versions', id),
});

const callbacks = {
    confirm: showConfirm,
    success,
    error,
};

const normalizedSelection = computed<SelectedItemType[]>(() => {
    const selection = props.selectedItems ?? [];
    if (selection.length === 0) {
        return [];
    }

    return deduplicateItems(selection);
});

const selectionScopeId = computed(() => props.currentFolderId ?? null);

const isCurrentItemSelected = computed(() =>
    normalizedSelection.value.some(item => item.id === props.itemId && item.type === props.itemType),
);

const hasMultiSelection = computed(() => normalizedSelection.value.length > 1 && isCurrentItemSelected.value);

const actionTargets = computed<SelectedItemType[]>(() => {
    if (hasMultiSelection.value) {
        const scoped = normalizedSelection.value.filter(
            item => (item.currentFolderId ?? null) === selectionScopeId.value,
        );

        return scoped.length > 0 ? scoped : normalizedSelection.value;
    }

    return [
        {
            id: props.itemId,
            type: props.itemType,
            name: props.itemName,
            isBookmarked: props.isBookmarked,
            bookmarkId: props.bookmarkId,
            currentFolderId: props.currentFolderId ?? null,
            hasFile: props.itemType === 'file' ? props.hasFile !== false : undefined,
        },
    ];
});

const moveItems = computed(() => {
    if (!hasMultiSelection.value) {
        return undefined;
    }

    return actionTargets.value.map(item => ({
        id: item.id,
        type: item.type,
        name: item.name,
        currentFolderId: item.currentFolderId ?? null,
    }));
});

const handleRestore = async (): Promise<void> => {
    if (hasMultiSelection.value) {
        await restoreMultipleItems(
            actionTargets.value,
            callbacks,
            props.selectionCount || actionTargets.value.length,
        );
        return;
    }

    emit('restore', { id: props.itemId, type: props.itemType });
};

const handleDeletePermanent = async (): Promise<void> => {
    if (hasMultiSelection.value) {
        await deleteMultipleItems(
            actionTargets.value,
            callbacks,
            props.selectionCount || actionTargets.value.length,
            { permanent: true },
        );
        return;
    }

    emit('delete-permanent', { id: props.itemId, type: props.itemType });
};

const contentClasses = computed(() => 'z-50 w-56 rounded-md border bg-popover p-1 shadow-md text-sm outline-none');
const itemClasses = computed(
    () =>
        'relative flex items-center gap-2 h-8 cursor-pointer select-none rounded px-2 text-left outline-none transition-colors data-[highlighted]:bg-muted',
);
const isTrashMode = computed(() => props.mode === 'trash');
const hasFile = computed(() => (props.itemType === 'file' ? props.hasFile !== false : true));
const allBookmarked = computed(() => actionTargets.value.every(item => item.isBookmarked));
const bookmarkLabel = computed(() => {
    if (hasMultiSelection.value) {
        return allBookmarked.value ? 'Unbookmark' : 'Bookmark';
    }

    return props.isBookmarked ? 'Unbookmark' : 'Bookmark';
});

const bookmarkFilled = computed(() => (hasMultiSelection.value ? allBookmarked.value : Boolean(props.isBookmarked)));

const handleBookmark = async (): Promise<void> => {
    if (hasMultiSelection.value) {
        await toggleMultipleBookmarks(actionTargets.value, callbacks);
        return;
    }

    await handleToggleBookmarkSingle();
};

const handleDownload = async (): Promise<void> => {
    if (hasMultiSelection.value) {
        await handleSelectionDownload(actionTargets.value, callbacks);
        return;
    }

    await handleDownloadSingle();
};

const handleDelete = async (): Promise<void> => {
    if (hasMultiSelection.value) {
        await deleteMultipleItems(actionTargets.value, callbacks, props.selectionCount || actionTargets.value.length);
        return;
    }

    await handleDeleteSingle();
};
</script>

<template>
    <template v-if="props.triggerType === 'context'">
        <ContextMenuRoot>
            <ContextMenuTrigger as-child>
                <slot />
            </ContextMenuTrigger>

            <ContextMenuPortal>
                <ContextMenuContent :class="contentClasses" :side-offset="6">
                    <template v-if="isTrashMode">
                        <ContextMenuItem v-if="hasMetadata && !hasMultiSelection" :class="itemClasses"
                            @click="showInfoSheet = true">
                            <Info class="h-4 w-4" />
                            <span>Info</span>
                        </ContextMenuItem>

                        <ContextMenuItem :class="itemClasses" @click="handleRestore">
                            <RotateCcw class="h-4 w-4" />
                            <span>Restore</span>
                        </ContextMenuItem>

                        <ContextMenuSeparator class="my-1 h-px bg-border" />

                        <ContextMenuItem :class="itemClasses" class="text-destructive focus:text-destructive"
                            @click="handleDeletePermanent">
                            <Trash2 class="h-4 w-4" />
                            <span>Delete</span>
                        </ContextMenuItem>
                    </template>

                    <template v-else>

                        <ContextMenuItem v-if="hasMetadata && !hasMultiSelection" :class="itemClasses"
                            @click="showInfoSheet = true">
                            <Info class="h-4 w-4" />
                            <span>Info</span>
                        </ContextMenuItem>

                        <ContextMenuItem v-if="!hasMultiSelection && props.itemType === 'file'" :class="itemClasses"
                            @click="handleAttach">
                            <Paperclip class="h-4 w-4" />
                            <span>{{ toTitleCase(attachLabel) }}</span>
                        </ContextMenuItem>

                        <ContextMenuItem :class="itemClasses" @click="handleBookmark">
                            <Bookmark class="h-4 w-4" :class="bookmarkFilled ? 'fill-current' : ''" />
                            <span>{{ toTitleCase(bookmarkLabel) }}</span>
                        </ContextMenuItem>

                        <ContextMenuItem :disabled="props.itemType === 'file' && !hasFile" :class="itemClasses"
                            @click="handleDownload">
                            <Download class="h-4 w-4" />
                            <span>Download</span>
                        </ContextMenuItem>

                        <ContextMenuItem v-if="can('organizeContent') && !hasMultiSelection" :class="itemClasses"
                            @click="showRenameDialog = true">
                            <SquarePen class="h-4 w-4" />
                            <span>Rename</span>
                        </ContextMenuItem>

                        <ContextMenuItem v-if="can('organizeContent') && (!isBookmarksPage || hasMultiSelection)"
                            :class="itemClasses" @click="showMoveDialog = true">
                            <FolderInput class="h-4 w-4" />
                            <span>Move</span>
                        </ContextMenuItem>

                        <ContextMenuSeparator class="my-1 h-px bg-border" />

                        <ContextMenuItem v-if="can('deleteContent')" :class="itemClasses"
                            class="text-destructive focus:text-destructive" @click="handleDelete">
                            <Trash2 class="h-4 w-4" />
                            <span>Trash</span>
                        </ContextMenuItem>
                    </template>
                </ContextMenuContent>
            </ContextMenuPortal>
        </ContextMenuRoot>
    </template>

    <template v-else>
        <DropdownMenu>
            <DropdownMenuTrigger as-child @click.stop>
                <slot name="trigger">
                    <Button variant="ghost" size="icon" :class="props.inline ? 'h-8 w-8' : 'h-8 w-8'">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12 7a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                    </Button>
                </slot>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" @click.stop>
                <template v-if="isTrashMode">
                    <DropdownMenuItem v-if="hasMetadata && !hasMultiSelection" @click="showInfoSheet = true">
                        <Info class="mr-2 h-4 w-4" />
                        <span>Info</span>
                    </DropdownMenuItem>

                    <DropdownMenuItem @click="handleRestore">
                        <RotateCcw class="mr-2 h-4 w-4" />
                        <span>Restore</span>
                    </DropdownMenuItem>

                    <DropdownMenuItem class="text-destructive focus:text-destructive"
                        @click="handleDeletePermanent">
                        <Trash2 class="mr-2 h-4 w-4" />
                        <span>Delete</span>
                    </DropdownMenuItem>
                </template>

                <template v-else>
                    <DropdownMenuItem v-if="hasMetadata" @click="showInfoSheet = true">
                        <Info class="mr-2 h-4 w-4" />
                        <span>Info</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="props.itemType === 'file'" @click="handleAttach">
                        <Paperclip class="mr-2 h-4 w-4" />
                        <span>{{ toTitleCase(attachLabel) }}</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="handleBookmark">
                        <Bookmark class="mr-2 h-4 w-4" :class="bookmarkFilled ? 'fill-current' : ''" />
                        <span>{{ toTitleCase(bookmarkLabel) }}</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem :disabled="props.itemType === 'file' && !hasFile" @click="handleDownload">
                        <Download class="mr-2 h-4 w-4" />
                        <span>Download</span>
                    </DropdownMenuItem>

                    <DropdownMenuItem v-if="can('organizeContent') && !hasMultiSelection"
                        @click="showRenameDialog = true">
                        <SquarePen class="mr-2 h-4 w-4" />
                        <span>Rename</span>
                    </DropdownMenuItem>


                    <DropdownMenuItem @click="showMoveDialog = true" v-if="can('organizeContent')">
                        <FolderInput class="mr-2 h-4 w-4" />
                        <span>Move</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem class="text-destructive focus:text-destructive" @click="handleDelete"
                        v-if="can('deleteContent')">
                        <Trash2 class="mr-2 h-4 w-4" />
                        <span>Trash</span>
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </template>

    <ItemMetadataSheet v-model:open="showInfoSheet" :item-name="props.itemName" :has-metadata="hasMetadata"
        :metadata-entries="metadataEntries" :file-id="props.itemType === 'file' ? props.itemId : null"
        :file-name="props.itemName" :has-file="hasFile" :current-version-id="props.metadata?.current_version_id ?? null"
        :uploaded-at="props.metadata?.created_at ?? null"
        :modified-at="props.metadata?.updated_at ?? props.metadata?.current_version?.created_at ?? null"
        :disable-version-actions="isTrashMode && props.itemType === 'file'" />

    <MoveDialog v-model:open="showMoveDialog" :item-id="props.itemId" :item-type="props.itemType"
        :item-name="props.itemName" :current-folder-id="props.currentFolderId" :items="moveItems" />
    <RenameDialog v-model:open="showRenameDialog" :item-id="props.itemId" :item-type="props.itemType"
        :initial-name="props.itemName" @renamed="onRenamed" />
</template>
