<script setup lang="ts">
import { FolderPlus, Plus, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';

import CreateFolderDialog from './CreateFolderDialog.vue';
import UploadFileDialog from './UploadFileDialog.vue';

const props = defineProps<{
    parentId?: string | null;
    folderId?: string | null;
    compact?: boolean;
}>();

const showFolderDialog = ref(false);
const showFileDialog = ref(false);
const uploadMode = ref<'record' | 'fileOnly'>('record');
const { error } = useToast();

const handleFolderClick = () => {
    const currentPath = window.location.pathname;

    if (currentPath === '/records/pending-unorganized') {
        error('Cannot Upload Here', 'Files can only be uploaded in this page.');
        return;
    }

    showFolderDialog.value = true;
};

const ensureCanUpload = (): boolean => {
    const page = usePage();
    const currentFolder = (page.props as any).folder;
    const currentPath = window.location.pathname;
    const isImportPage = currentPath === '/records/pending-unorganized';

    if (isImportPage) {
        error('Cannot Upload Here', 'Uploads are not allowed on the Import Records page.');
        return false;
    }

    if (!props.folderId && (!currentFolder?.id || currentPath === '/dashboard' || currentPath === '/records')) {
        error('Cannot Upload Here', 'Files can only be uploaded inside folders. Please open a folder first.');
        return false;
    }

    return true;
};

const handleUploadClick = () => {
    if (!ensureCanUpload()) return;
    uploadMode.value = 'record';
    showFileDialog.value = true;
};

const handleUploadFileClick = () => {
    if (!ensureCanUpload()) return;
    uploadMode.value = 'fileOnly';
    showFileDialog.value = true;
};
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    v-if="compact"
                    size="icon"
                    variant="default"
                    class="size-12 rounded-lg shadow-sm cursor-pointer bg:hover:bg-secondary/10"
                >
                    <Plus class="size-5" />
                </Button>
                <SidebarMenuButton
                    v-else
                    size="lg"
                    class="shadow-sm group-data-[state=collapsed]:justify-center"
                >
                    <Plus />
                    <span class="group-data-[state=collapsed]:hidden ">New</span>
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuItem @click="handleFolderClick" class="cursor-pointer py-3">
                    <FolderPlus class="mr-3 h-5 w-5" />
                    <span class="text-base">Create New Folder</span>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="handleUploadClick" class="cursor-pointer py-3">
                    <Upload class="mr-3 h-5 w-5" />
                    <span class="text-base">Create New Record</span>
                </DropdownMenuItem>
                <DropdownMenuItem @click="handleUploadFileClick" class="cursor-pointer py-3">
                    <Upload class="mr-3 h-5 w-5" />
                    <span class="text-base">Upload File</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Dialogs -->
        <CreateFolderDialog v-model:open="showFolderDialog" :parent-id="parentId" />
        <UploadFileDialog v-model:open="showFileDialog" :folder-id="folderId" :mode="uploadMode" />
    </div>
</template>
