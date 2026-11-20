<script setup lang="ts">
import { computed } from 'vue';
import { Bookmark, Folder, FolderOpen } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import ItemMenu from './ItemMenu.vue';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { useLongPress } from '@/utils/useLongPress';
import { type FolderSchema } from '@/types/schema';
import type { SelectedItemType } from '@/types';
import { formatDate } from '@/utils/format';

const pluralizeCount = (count: number, singular: string, plural: string): string =>
    count >= 2 ? plural : singular;
const props = withDefaults(defineProps<{
    folder: FolderSchema;
    isSelected?: boolean;
    isDragging?: boolean;
    isDropTarget?: boolean;
    currentFolderId?: string | null;
    draggable?: boolean;
    showActions?: boolean;
    contextMode?: 'default' | 'trash';
    selectedItems?: SelectedItemType[];
    selectionCount?: number;
}>(), {
    draggable: true,
    showActions: true,
    contextMode: 'default',
    selectedItems: () => [],
    selectionCount: 0,
});

const emit = defineEmits<{
    click: [event: MouseEvent];
    dblclick: [event: MouseEvent];
    dragstart: [event: DragEvent];
    dragend: [event: DragEvent];
    dragover: [event: DragEvent];
    dragleave: [event: DragEvent];
    drop: [event: DragEvent];
    unbookmarked: [itemId: string, itemType: 'folder' | 'file'];
    longpress: [];
    contextmenu: [event: MouseEvent];
    restore: [{ id: string; type: 'folder' | 'file' }];
    'delete-permanent': [{ id: string; type: 'folder' | 'file' }];
}>();



const { startPress, cancelPress, handleClick, handleDblClick } = useLongPress(emit);

const { isTouchDevice } = usePointerCapabilities();
const isDragEnabled = computed(() => !isTouchDevice.value && props.draggable);

</script>

<template>
    <ItemMenu
        :item-id="folder.id"
        item-type="folder"
        :item-name="folder.name"
        :is-bookmarked="folder.is_bookmarked"
        :bookmark-id="folder.bookmark_id"
        :current-folder-id="currentFolderId"
        :mode="contextMode"
        trigger-type="context"
        :selected-items="selectedItems"
        :selection-count="selectionCount"
        @restore="emit('restore', $event)"
        @delete-permanent="emit('delete-permanent', $event)"
        @unbookmarked="emit('unbookmarked', $event, 'folder')"
    >
        <Card
            class="cursor-pointer transition-all hover:shadow-lg hover:scale-101 group relative h-full min-h-[10rem] md:min-h-[13rem]"
            :class="{
                'ring-4 ring-blue-500 bg-blue-50 dark:bg-blue-950': isDropTarget,
                'opacity-50': isDragging,
                'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/40': isSelected
            }"
            :draggable="isDragEnabled"
            @click.stop="handleClick"
            @dblclick.stop="handleDblClick"
            @touchstart.passive.stop="startPress"
            @touchend.passive.stop="cancelPress"
            @touchcancel.passive.stop="cancelPress"
            @mousedown.stop="startPress"
            @mouseup.stop="cancelPress"
            @mouseleave.stop="cancelPress"
            @dragstart="emit('dragstart', $event)"
            @dragend="emit('dragend', $event)"
            @dragover="emit('dragover', $event)"
            @dragleave="emit('dragleave', $event)"
            @drop="emit('drop', $event)"
            @contextmenu="emit('contextmenu', $event)"
        >
            <div v-if="showActions" class="absolute top-2 right-2 z-10">
                <ItemMenu
                    :item-id="folder.id"
                    item-type="folder"
                    :item-name="folder.name"
                    :is-bookmarked="folder.is_bookmarked"
                    :bookmark-id="folder.bookmark_id"
                    :current-folder-id="currentFolderId"
                    :mode="contextMode"
                    trigger-type="button"
                    :inline="true"
                    :selected-items="selectedItems"
                    :selection-count="selectionCount"
                    @restore="emit('restore', $event)"
                    @delete-permanent="emit('delete-permanent', $event)"
                    @unbookmarked="emit('unbookmarked', $event, 'folder')"
                />
            </div>
            <div v-if="folder.is_bookmarked" class="absolute top-2 left-2 z-10">
                <div class="h-8 w-8 rounded-full bg-yellow-400 flex items-center justify-center shadow-md">
                    <Bookmark class="h-4 w-4 text-yellow-900 fill-yellow-900" />
                </div>
            </div>
            <slot name="overlay" />
            <CardContent class="h-full p-2 md:p-4">
                <div class="flex h-full flex-col items-center text-center space-y-0.5 md:space-y-4">
                    <div class="relative">
                        <Folder class="h-14 w-14 md:h-20 md:w-20 text-blue-500 group-hover:hidden" />
                        <FolderOpen class="h-14 w-14 md:h-20 md:w-20 text-blue-600 hidden group-hover:block" />
                    </div>
                    <div class="w-full flex-1 flex flex-col justify-center">
                        <h3 class="font-semibold text-sm md:text-lg truncate">{{ folder.name }}</h3>
                        <p class="text-[11px] md:text-xs text-muted-foreground mt-1">
                            {{ formatDate(folder.created_at) }}
                        </p>
                        <div
                            v-if="(folder.children_count ?? 0) > 0 || (folder.files_count ?? 0) > 0"
                            class="flex gap-1.5 md:gap-2 justify-center mt-2 text-[11px] md:text-xs text-muted-foreground"
                        >
                            <span v-if="(folder.children_count ?? 0) > 0">
                                {{ folder.children_count }} {{ pluralizeCount(folder.children_count, 'Folder', 'Folders') }}
                            </span>
                            <span v-if="(folder.children_count ?? 0) > 0 && (folder.files_count ?? 0) > 0">
                                •
                            </span>
                            <span v-if="(folder.files_count ?? 0) > 0">
                                {{ folder.files_count }} {{ pluralizeCount(folder.files_count, 'File', 'Files') }}
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </ItemMenu>
</template>
