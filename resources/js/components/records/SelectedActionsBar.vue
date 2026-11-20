<script setup lang="ts">
import { Bookmark, Download, FolderInput, MoreVertical, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

import MoveDialog from './MoveDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import type { SelectedItemType } from '@/types';
import {
    deduplicateItems,
    handleDownload as performDownload,
    deleteMultipleItems,
    restoreMultipleItems,
    toggleMultipleBookmarks,
    type ActionCallbacks,
} from '@/utils/actions';
import { usePermissions } from '@/utils/permission';

interface Props {
    items: SelectedItemType[];
    selectionCount?: number;
}


const props = withDefaults(defineProps<Props>(), {
    items: () => [],
    selectionCount: 0,
});

const { confirm: showConfirm } = useConfirm();
const { error, success } = useToast();
const { can } = usePermissions();
const page = usePage();

const isTrashPage = computed(() => page.url?.startsWith('/records/trash'));
const isPendingPage = computed(() => page.url?.startsWith('/records/pending-unorganized'));

const showMoveDialog = ref(false);

const dedupedItems = computed(() => deduplicateItems(props.items));
const bookmarkTargets = computed(() => dedupedItems.value.filter(item => !item.isBookmarked));
const allBookmarked = computed(() => props.items.length > 0 && bookmarkTargets.value.length === 0);
const firstItem = computed(() => props.items[0]);
const hasSelection = computed(() => props.items.length > 0);
const hasPendingSelection = computed(() =>
    dedupedItems.value.some(item =>
        item.type === 'file' && (item.pendingScanMetadata || item.status === 'pending')
    )
);
const hideActions = computed(() => isPendingPage.value && hasPendingSelection.value);

const callbacks: ActionCallbacks = {
    error,
    success,
    confirm: showConfirm,
};

const handleDelete = async (): Promise<void> => {
    await deleteMultipleItems(props.items, callbacks, props.selectionCount, {
        permanent: isTrashPage.value,
    });
};

const handleRestore = async (): Promise<void> => {
    if (!isTrashPage.value) return;
    await restoreMultipleItems(props.items, callbacks, props.selectionCount);
};

const handleToggleBookmark = async (): Promise<void> => {
    await toggleMultipleBookmarks(props.items, callbacks);
};

const handleDownload = async (): Promise<void> => {
    const downloadable = props.items.filter(item => item.type === 'folder' || item.hasFile !== false);

    if (downloadable.length === 0) {
        error('No files to download', 'Attach a file to the selected record(s) first.');
        return;
    }

    if (downloadable.length !== props.items.length) {
        error('Some items skipped', 'Items without files were skipped. Attach files to download them.');
    }

    await performDownload(downloadable, callbacks);
};
</script>

<template>
    <div v-if="!hideActions" class="flex items-center gap-2">
        <DropdownMenu>
            <DropdownMenuTrigger as-child @click.stop>
                <Button variant="ghost" size="icon" class="h-9 w-9 sm:hidden" :disabled="!hasSelection">
                    <MoreVertical class="h-5 w-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-[min(10rem,calc(30vw-0.3rem))] sm:hidden p-0.3 text-sm"
                @click.stop>
                <template v-if="!isTrashPage">
                    <DropdownMenuItem :disabled="!hasSelection" @click="handleToggleBookmark">
                        <Bookmark :class="['h-2 w-2 shrink-0', allBookmarked ? 'text-blue-600 fill-current' : '']" />
                        <span class="text-sm leading-tight">{{ allBookmarked ? 'Remove bookmark' : 'Bookmark' }}</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem :disabled="!hasSelection" @click="handleDownload">
                        <Download class="h-2 w-2 shrink-0" />
                        <span class="text-sm leading-tight">Download</span>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="can('organizeContent')" :disabled="!hasSelection"
                        @click="() => (showMoveDialog = true)">
                        <FolderInput class="h-2 w-2 shrink-0" />
                        <span class="text-sm leading-tight">Move</span>
                    </DropdownMenuItem>
                </template>

                <DropdownMenuItem v-if="can('deleteContent')" :disabled="!hasSelection" variant="destructive"
                    @click="handleDelete">
                    <Trash2 class="h-2 w-2 shrink-0" />
                    <span class="text-sm leading-tight">{{ isTrashPage ? 'Delete permanently' : 'Trash' }}</span>
                </DropdownMenuItem>
                <DropdownMenuItem v-if="can('deleteContent') && isTrashPage" :disabled="!hasSelection"
                    @click="handleRestore">
                    <RotateCcw class="h-2 w-2 shrink-0" />
                    <span class="text-sm leading-tight">Restore</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <div class="hidden items-center gap-2 sm:flex">
            <template v-if="!isTrashPage">
                <Button variant="ghost" size="icon" class="h-9 w-9" :disabled="!hasSelection"
                    @click.stop="handleToggleBookmark">
                    <Bookmark :class="['h-5 w-5', allBookmarked ? 'text-blue-600 fill-current' : '']" />
                </Button>

                <Button variant="ghost" size="icon" class="h-9 w-9" :disabled="!hasSelection"
                    @click.stop="handleDownload">
                    <Download class="h-5 w-5" />
                </Button>

                <Button v-if="can('organizeContent')" variant="ghost" size="icon" class="h-9 w-9"
                    :disabled="!hasSelection" @click.stop="() => (showMoveDialog = true)">
                    <FolderInput class="h-5 w-5" />
                </Button>
            </template>

            <template v-if="isTrashPage">
                <Button v-if="can('deleteContent')" variant="ghost" size="icon" class="h-9 w-9"
                    :disabled="!hasSelection" @click.stop="handleRestore">
                    <RotateCcw class="h-5 w-5" />
                </Button>

                <Button v-if="can('deleteContent')" variant="ghost" size="icon" class="h-9 w-9 text-destructive"
                    :disabled="!hasSelection" @click.stop="handleDelete">
                    <Trash2 class="h-5 w-5" />
                </Button>
            </template>
            <template v-else>
                <Button v-if="can('deleteContent')" variant="ghost" size="icon" class="h-9 w-9 text-destructive"
                    :disabled="!hasSelection" @click.stop="handleDelete">
                    <Trash2 class="h-5 w-5" />
                </Button>
            </template>
        </div>
    </div>

    <MoveDialog v-if="!isTrashPage && !hideActions" v-model:open="showMoveDialog" :items="items"
        :item-id="firstItem?.id" :item-type="firstItem?.type" :item-name="firstItem?.name"
        :current-folder-id="firstItem?.currentFolderId" />
</template>
