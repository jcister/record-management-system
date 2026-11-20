<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import versionsRoutes from '@/routes/api/files/versions';
import { type FileVersion } from '@/types/schema';
import { formatDate, formatFileSize } from '@/utils/format';

const props = withDefaults(defineProps<{
    fileId: string | null;
    fileName?: string;
    currentVersionId?: string | null;
}>(), {
    fileName: 'Untitled record',
    currentVersionId: null,
});

const emit = defineEmits<{ restored: [] }>();
const open = defineModel<boolean>('open', { default: false });
const versions = ref<FileVersion[]>([]);
const loading = ref(false);
const errorMessage = ref<string | null>(null);

const { error: showError, success: showSuccess } = useToast();

const dialogTitle = computed(() => `Version history — ${props.fileName}`);

const fetchVersions = async () => {
    if (!props.fileId) {
        versions.value = [];
        return;
    }

    loading.value = true;
    errorMessage.value = null;

    try {
        const response = await apiClient.get(versionsRoutes.index({ file: props.fileId }).url);
        versions.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Failed to load versions.';
        showError('Unable to load versions', errorMessage.value);
    } finally {
        loading.value = false;
    }
};

const handleDownload = (version: FileVersion) => {
    if (!props.fileId) return;

    window.open(versionsRoutes.download({ file: props.fileId, version: version.id }).url, '_blank');
};

const handleRestore = async (version: FileVersion) => {
    if (!props.fileId) {
        return;
    }

    try {
        await apiClient.post(versionsRoutes.restore({ file: props.fileId, version: version.id }).url);
        showSuccess('Version restored', 'This version is now the active copy.');
        emit('restored');
        await fetchVersions();
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to restore this version.';
        showError('Restore failed', message);
    }
};

watch(
    () => [open.value, props.fileId],
    ([isOpen]) => {
        if (isOpen) {
            fetchVersions();
        }
    }
);

</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="w-full max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    Track every revision of this file, download past copies, or restore a previous version.
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-[12rem] max-h-[65vh] overflow-y-auto pr-1 sm:pr-2">
                <div v-if="loading" class="flex items-center justify-center py-8">
                    <Spinner class="h-5 w-5" />
                </div>
                <div v-else-if="errorMessage" class="text-sm text-destructive">
                    {{ errorMessage }}
                </div>
                <div v-else-if="versions.length === 0" class="text-sm text-muted-foreground py-4">
                    No versions have been uploaded for this record yet.
                </div>
                <ul v-else class="flex flex-col gap-3">
                    <li v-for="version in versions" :key="version.id"
                        class="rounded-2xl border border-border/70 bg-background/90 p-4 shadow-sm ring-1 ring-black/0 transition hover:shadow-md dark:bg-muted/60">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ version.version_label ?? `v${version.version_number}` }}
                                    </span>
                                    <span>{{ formatDate(version.created_at) }}</span>
                                    <span>{{ formatFileSize(version.file_size ?? 0) }}</span>
                                    <Badge v-if="version.id === props.currentVersionId" variant="secondary">
                                        Current
                                    </Badge>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    <p class="font-medium">Uploaded by {{ version.uploader?.name ?? 'Unknown user' }}</p>
                                    <p v-if="version.change_summary" class="mt-0.5">
                                        {{ version.change_summary }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <Button variant="outline" size="sm" class="w-full sm:w-auto" @click="handleDownload(version)">
                                    Download
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    :disabled="version.id === props.currentVersionId"
                                    @click="handleRestore(version)"
                                >
                                    Restore
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
