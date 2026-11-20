<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Folder, FolderOpen, RotateCcw, Trash2 } from 'lucide-vue-next';
import { type FolderSchema } from '@/types/schema';
import { formatDate } from '@/utils/format';
const props = defineProps<{
    folder: FolderSchema;
    isSelected?: boolean;
}>();

const emit = defineEmits<{
    click: [event: MouseEvent];
    dblclick: [event: MouseEvent];
    restore: [folderId: string, event: Event];
    delete: [folderId: string, event: Event];
}>();


</script>

<template>
    <Card
        :class="[
            'transition-all hover:shadow-lg group relative cursor-pointer',
            isSelected ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/40' : '',
        ]"
        @click.stop="emit('click', $event)"
        @dblclick.stop="emit('dblclick', $event)"
    >
        <div class="absolute top-2 right-2 flex gap-1 z-10">
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity bg-background"
                @click.stop="(e: Event) => emit('restore', folder.id, e)"
            >
                <RotateCcw class="h-4 w-4" />
            </Button>
            <Button
                variant="outline"
                size="icon"
                class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                @click.stop="(e: Event) => emit('delete', folder.id, e)"
            >
                <Trash2 class="h-4 w-4 fill-none stroke-current text-destructive" />
            </Button>
        </div>

        <CardContent class="p-6">
            <div class="flex flex-col items-center text-center space-y-4">
                <div class="relative">
                    <Folder class="h-20 w-20 text-blue-500 group-hover:hidden" />
                    <FolderOpen class="h-20 w-20 text-blue-600 hidden group-hover:block" />
                </div>
                <div class="w-full">
                    <h3 class="font-semibold text-lg truncate">{{ folder.name }}</h3>
                    <p class="text-xs text-muted-foreground mt-1">
                        Trashed {{ formatDate(folder.deleted_at) }}
                    </p>
                    <div
                        v-if="folder.children_count || folder.files_count"
                        class="flex gap-2 justify-center mt-2 text-xs text-muted-foreground"
                    >
                        <span v-if="folder.children_count">{{ folder.children_count }} folders</span>
                        <span v-if="folder.children_count && folder.files_count">•</span>
                        <span v-if="folder.files_count">{{ folder.files_count }} files</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
