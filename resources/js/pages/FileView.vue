<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, watch } from 'vue';
import FileViewer from '@/components/records/FileViewer.vue';

type File = {
    id: string;
    file_name: string;
    file_size: number;
    mime_type: string;
    created_at: string;
    file_path?: string | null;
    assembly_status?: string | null;
    retention_status?: 'active' | 'ready_for_disposal' | 'disposed' | 'archived' | null;
    ocr_text?: string | null;
    ocr_status?: string | null;
};

const props = defineProps<{
    file: File;
    previewUrl: string;
    downloadUrl: string;
    previewAvailable: boolean;
    previewUnavailableReason?: 'disposed' | 'missing' | 'no_file' | null;
}>();

const handleClose = () => {
    window.history.back();
};

const hasFile = computed(() => Boolean(props.file.file_path));
const isDisposedRecord = computed(
    () => props.previewUnavailableReason === 'disposed' || props.file.retention_status === 'disposed'
);
const isProcessing = computed(
    () =>
        props.file.assembly_status === 'processing' ||
        (hasFile.value && props.file.ocr_status === 'pending') ||
        (hasFile.value && props.file.ocr_status === 'processing'),
);
const shouldStream = computed(() => !isDisposedRecord.value && (isProcessing.value || !props.previewAvailable));

let eventSource: EventSource | null = null;

const closeEventSource = (): void => {
    if (eventSource !== null) {
        eventSource.close();
        eventSource = null;
    }
};

const openEventSource = (): void => {
    closeEventSource();

    eventSource = new EventSource(`/api/sse/files/${props.file.id}/updates`);

    eventSource.onmessage = () => {
        router.reload({
            only: ['file', 'previewUrl', 'downloadUrl', 'previewAvailable', 'previewUnavailableReason'],
            preserveState: true,
            preserveScroll: true,
        });
    };

    eventSource.onerror = () => {
        // EventSource will automatically reconnect; close only when no longer needed.
        if (!shouldStream.value) {
            closeEventSource();
        }
    };
};

watch(
    shouldStream,
    (value) => {
        if (value) {
            openEventSource();
        } else {
            closeEventSource();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    closeEventSource();
});
</script>

<template>
    <Head :title="file.file_name" />
    <FileViewer
        :file="file"
        :preview-url="previewUrl"
        :download-url="downloadUrl"
        :preview-available="previewAvailable"
        :preview-unavailable-reason="previewUnavailableReason"
        :on-close="handleClose"
    />
</template>
