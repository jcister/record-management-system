<script setup lang="ts">
import { computed } from 'vue';
import { Bookmark, Folder } from 'lucide-vue-next';
import ItemMenu from './ItemMenu.vue';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useLongPress } from '@/utils/useLongPress';
import { type FolderSchema } from '@/types/schema';
import type { SelectedItemType } from '@/types';
import { formatDate } from '@/utils/format';
import Checkbox from '@/components/ui/checkbox/Checkbox.vue';

const props = withDefaults(defineProps<{
    folder: FolderSchema;
    isSelected?: boolean;
    currentFolderId?: string | null;
    isDragging?: boolean;
    isDropTarget?: boolean;
    draggable?: boolean;
    showActions?: boolean;
    showStatusSpacer?: boolean;
    showVersionSpacer?: boolean;
    contextMode?: 'default' | 'trash';
    selectedItems?: SelectedItemType[];
    selectionCount?: number;
}>(), {
    draggable: true,
    showActions: true,
    showStatusSpacer: false,
    showVersionSpacer: false,
    contextMode: 'default',
    selectedItems: () => [],
    selectionCount: 0,
});

const emit = defineEmits<{
    click: [event: MouseEvent];
    dblclick: [event: MouseEvent];
    unbookmarked: [itemId: string, itemType: 'folder' | 'file'];
    longpress: [];
    dragstart: [event: DragEvent];
    dragend: [event: DragEvent];
    dragover: [event: DragEvent];
    dragleave: [event: DragEvent];
    drop: [event: DragEvent];
    contextmenu: [event: MouseEvent];
    restore: [{ id: string; type: 'folder' | 'file' }];
    'delete-permanent': [{ id: string; type: 'folder' | 'file' }];
}>();


const { startPress, cancelPress, handleClick, handleDblClick } = useLongPress(emit);

const { isTouchDevice } = usePointerCapabilities();
const isDragEnabled = computed(() => !isTouchDevice.value && props.draggable);
const checkboxVisible = computed(() => isTouchDevice.value || Boolean(props.isSelected));
const checkboxClasses = computed(() => [
    'transition-opacity duration-150 transition-[width,margin] shrink-0 cursor-pointer',
    checkboxVisible.value
        ? 'opacity-100 pointer-events-auto w-4 mr-1'
        : 'opacity-0 pointer-events-none w-0 mr-0 group-hover:opacity-100 group-hover:pointer-events-auto group-hover:w-4 group-hover:mr-1',
]);

const itemCount = computed(() => (props.folder.files_count ?? 0) + (props.folder.children_count ?? 0));
const itemLabel = computed(() => {
    const count = itemCount.value;

    if (count === 0) {
        return '—';
    }

    if (count === 1) {
        return '1 Item';
    }

    return `${count} Items`;
});
const displayDate = computed(() => formatDate(props.folder.updated_at ?? props.folder.created_at));

</script>

<template>
    <ItemMenu :item-id="folder.id" item-type="folder" :item-name="folder.name" :is-bookmarked="folder.is_bookmarked"
        :bookmark-id="folder.bookmark_id" :current-folder-id="currentFolderId" :mode="contextMode"
        trigger-type="context" :selected-items="selectedItems" :selection-count="selectionCount"
        @restore="emit('restore', $event)" @delete-permanent="emit('delete-permanent', $event)"
        @unbookmarked="emit('unbookmarked', $event, 'folder')">
        <div class="flex flex-wrap sm:flex-nowrap sm:flex-row items-start sm:items-center gap-y-2 py-3 border-b last:border-b-0 cursor-pointer transition-colors group"
            :class="[
                'border-l-4',
                isSelected
                    ? 'border-l-blue-500 bg-blue-50 dark:bg-blue-900/40 hover:bg-blue-100 dark:hover:bg-blue-900/60'
                    : 'border-transparent hover:bg-accent/5',
                'sm:hover:bg-blue-50/60',
                {
                    'bg-blue-50 dark:bg-blue-950 border-l-blue-500': isDropTarget,
                    'opacity-50': isDragging,
                }
            ]" :draggable="isDragEnabled" @click.stop="handleClick" @dblclick.stop="handleDblClick"
            @touchstart.passive.stop="startPress" @touchend.passive.stop="cancelPress"
            @touchcancel.passive.stop="cancelPress" @mousedown.stop="startPress" @mouseup.stop="cancelPress"
            @mouseleave.stop="cancelPress" @dragstart="emit('dragstart', $event)" @dragend="emit('dragend', $event)"
            @dragover="emit('dragover', $event)" @dragleave="emit('dragleave', $event)" @drop="emit('drop', $event)"
            @contextmenu="emit('contextmenu', $event)">
            <!-- Mobile top row: name + actions -->
            <div class="w-full flex items-center justify-between sm:hidden">
                <div class="flex items-center gap-1 min-w-0 cursor-pointer">
                    <Checkbox :model-value="Boolean(isSelected)" :class="checkboxClasses" class="mt-0.5"
                        @click.stop="emit('click', $event)" @mousedown.stop @touchstart.stop />
                    <Folder class="h-6 w-6 shrink-0 text-blue-500" />
                    <div class="truncate">{{ folder.name }}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <slot name="actions" />
                    <ItemMenu v-if="showActions" :item-id="folder.id" item-type="folder" :item-name="folder.name"
                        :is-bookmarked="folder.is_bookmarked" :bookmark-id="folder.bookmark_id"
                        :current-folder-id="currentFolderId" :mode="contextMode" :inline="true" trigger-type="button"
                        :selected-items="selectedItems" :selection-count="selectionCount"
                        @restore="emit('restore', $event)" @delete-permanent="emit('delete-permanent', $event)"
                        @unbookmarked="emit('unbookmarked', $event, 'folder')" />
                </div>
            </div>

            <!-- Mobile meta row -->
            <div class="w-full mt-1 text-xs text-muted-foreground sm:hidden">
                {{ displayDate }} • {{ itemLabel }}
            </div>

            <!-- Desktop columns -->
            <div
                :class="[
                    'hidden sm:flex items-center min-w-0 gap-3',
                    showStatusSpacer ? 'sm:w-4/12' : (showVersionSpacer ? 'sm:w-5/12' : 'sm:w-6/12'),
                ]"
            >
                <Folder class="h-6 w-6 shrink-0 mx-1.5 text-blue-500" />
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="truncate flex-1 ">{{ folder.name }}</span>
                    <div class="ml-auto">
                        <Bookmark v-if="folder.is_bookmarked"
                            class="h-4 w-4 shrink-0 mx-5 text-yellow-500 fill-yellow-500" />
                        <Bookmark v-else class="h-4 w-4 shrink-0 mx-5 text-gray-500 " />
                    </div>

                </div>
            </div>

            <div class="hidden sm:block sm:w-1/6 text-xs text-muted-foreground">
                {{ displayDate }}
            </div>
            <div class="hidden sm:block sm:w-1/6 text-xs text-muted-foreground">
                {{ itemLabel }}
            </div>
            <div v-if="showStatusSpacer" class="hidden sm:block sm:w-1/6">&nbsp;</div>
            <div v-if="showVersionSpacer" class="hidden lg:block lg:w-1/12">&nbsp;</div>

            <div class="hidden sm:flex sm:flex-1 lg:w-1/12 sm:text-right items-center justify-end gap-2 shrink-0 ml-auto">
                <slot name="actions" />
                <ItemMenu v-if="showActions" :item-id="folder.id" item-type="folder" :item-name="folder.name"
                    :is-bookmarked="folder.is_bookmarked" :bookmark-id="folder.bookmark_id"
                    :current-folder-id="currentFolderId" :mode="contextMode" :inline="true" trigger-type="button"
                    :selected-items="selectedItems" :selection-count="selectionCount" @restore="emit('restore', $event)"
                    @delete-permanent="emit('delete-permanent', $event)"
                    @unbookmarked="emit('unbookmarked', $event, 'folder')" />
            </div>
        </div>
    </ItemMenu>
</template>
