<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Upload } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

import UploadFileDialog from '@/components/records/UploadFileDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import { detectFileCategory } from '@/utils/fileTypes';
import { ensureFolderUploadTarget } from '@/utils/upload';
import { update as updateFile } from '@/routes/api/files';
import { type File as FileRecord } from '@/types/schema';
import { useDuplicateFileResolver } from '@/composables/useDuplicateFileResolver';
import { useRecordModalGuard } from '@/composables/useRecordModalGuard';

interface DepartmentOption {
    id: string | number;
    name: string;
}

const isDraggingGlobal = ref(false);
const dragCounter = ref(0);
const showUploadDialog = ref(false);
const dialogFolderId = ref<string | null>(null);
const dialogMode = ref<'record' | 'fileOnly'>('record');
const uploadDialogRef = ref<InstanceType<typeof UploadFileDialog> | null>(null);

const page = usePage();
const departments = computed<DepartmentOption[]>(() => ((page.props as any)?.departments ?? []) as DepartmentOption[]);

const { error: showError, success: showSuccess } = useToast();
const { confirm: showConfirm } = useConfirm();
const { isCreateRecordModalOpen } = useRecordModalGuard();

const handleGlobalDragOver = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();

    if (isCreateRecordModalOpen.value) {
        return;
    }
};

const handleGlobalDragEnter = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (isCreateRecordModalOpen.value) {
        isDraggingGlobal.value = false;
        dragCounter.value = 0;
        return;
    }
    dragCounter.value++;

    if (event.dataTransfer?.types.includes('Files')) {
        isDraggingGlobal.value = true;
    }
};

const handleGlobalDragLeave = (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (isCreateRecordModalOpen.value) {
        isDraggingGlobal.value = false;
        dragCounter.value = 0;
        return;
    }
    dragCounter.value--;

    if (dragCounter.value === 0) {
        isDraggingGlobal.value = false;
    }
};

const resolveUploadTarget = (): { folderId: string | null; allowed: boolean } => {
    const currentPath = window.location.pathname;
    const folderId = ensureFolderUploadTarget(page.props as any, currentPath, showError);

    return { folderId: folderId ?? null, allowed: Boolean(folderId) };
};

const containsDirectory = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer?.items) {
        return false;
    }

    return Array.from(dataTransfer.items).some((item) => {
        const entry = (item as any).webkitGetAsEntry?.();
        return entry?.isDirectory === true;
    });
};

const uploadReplacementVersion = async (file: File, existingFile: FileRecord): Promise<void> => {
    const payload = new FormData();
    payload.append('_method', 'patch');
    payload.append('file', file);

    try {
        await apiClient.post(updateFile(existingFile.id).url, payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        showSuccess('Version uploaded', `"${file.name}" replaced the previous copy.`);
        router.reload();
    } catch (error: any) {
        const message = error.response?.data?.message || error.message || 'Failed to replace the existing file.';
        showError('Replace failed', message);
        throw error;
    }
};

const { checkDuplicateFileName } = useDuplicateFileResolver({
    showConfirm,
    showError,
    onReplace: uploadReplacementVersion,
    skipRecordPredicate: (record: FileRecord) => Boolean(record.pending_scan_metadata),
});

const openDialogWithFile = async (file: File, folderId: string | null) => {
    dialogFolderId.value = folderId;
    const category = detectFileCategory(file.type, file.name);
    dialogMode.value = category !== 'pdf' && category !== 'image' ? 'fileOnly' : 'record';
    showUploadDialog.value = true;

    await nextTick();
    if (uploadDialogRef.value) {
        await uploadDialogRef.value.prefillFile(file, { folderId });
    }
};

const handleGlobalDrop = async (event: DragEvent) => {
    event.preventDefault();
    event.stopPropagation();

    if (isCreateRecordModalOpen.value) {
        isDraggingGlobal.value = false;
        dragCounter.value = 0;
        return;
    }

    isDraggingGlobal.value = false;
    dragCounter.value = 0;

    const targetElement = event.target instanceof Element ? event.target : null;
    if (targetElement?.closest('[data-upload-drop-zone]')) {
        return;
    }

    const files = event.dataTransfer?.files;
    if (!files || files.length === 0) {
        return;
    }

    const currentPath = window.location.pathname;
    const { folderId, allowed } = resolveUploadTarget();

    if (!allowed) {
        return;
    }

    if (containsDirectory(event.dataTransfer) && currentPath === '/records/pending-unorganized') {
        showError('Folder Upload Not Supported', 'Folder uploads are not supported on this page. Please drop files only.');
        return;
    }

    if (files.length > 0) {
        const file = files[0];

        try {
            const resolution = await checkDuplicateFileName(file, folderId);

            if (resolution?.handled) {
                return;
            }

            await openDialogWithFile(resolution?.file ?? file, folderId);
        } catch {
            // User cancelled duplicate prompt
        }
    }
};

onMounted(() => {
    window.addEventListener('drop', handleGlobalDrop);
    window.addEventListener('dragover', handleGlobalDragOver);
    window.addEventListener('dragenter', handleGlobalDragEnter);
    window.addEventListener('dragleave', handleGlobalDragLeave);
});

onBeforeUnmount(() => {
    window.removeEventListener('drop', handleGlobalDrop);
    window.removeEventListener('dragover', handleGlobalDragOver);
    window.removeEventListener('dragenter', handleGlobalDragEnter);
    window.removeEventListener('dragleave', handleGlobalDragLeave);
});
</script>

<template>
    <div v-if="isDraggingGlobal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm">
        <div
            class="flex flex-col items-center justify-center space-y-4 rounded-lg border-2 border-dashed border-primary bg-background/50 p-12">
            <Upload class="h-20 w-20 text-primary" />
            <h2 class="text-2xl font-semibold">Drop files to upload</h2>
            <p class="text-muted-foreground">Release to open the uploader</p>
        </div>
    </div>

    <UploadFileDialog ref="uploadDialogRef" v-model:open="showUploadDialog" :folder-id="dialogFolderId"
        :mode="dialogMode" :departments="departments" />
</template>
