<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { PDFViewer } from '@embedpdf/vue-pdf-viewer';
import { Download, X } from 'lucide-vue-next';
import FileTypeIcon from '@/components/icons/FileTypeIcon.vue';

import { detectFileCategory, getExtension } from '@/utils/fileTypes';

import { formatDate, formatFileSize } from '@/utils/format';

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
    ocr_error?: string | null;
};

type Props = {
    file: File;
    previewUrl: string;
    downloadUrl: string;
    previewAvailable: boolean;
    previewUnavailableReason?: 'disposed' | 'missing' | 'no_file' | null;
    onClose: () => void;
};

const props = defineProps<Props>();

const debugSessionId = 'debug-session';
const debugRunId = 'pre-fix';
const debugEndpoint: string = (import.meta.env as any).VITE_DEBUG_ENDPOINT ?? '';

const LARGE_PDF_THRESHOLD_BYTES = 20 * 1024 * 1024;

const fileType = computed(() => detectFileCategory(props.file.mime_type, props.file.file_name));

const sendDebugLog = (hypothesisId: string, location: string, message: string, data: Record<string, unknown> = {}): void => {
    // Only send debug logs when a debug endpoint is configured.
    if (!debugEndpoint) {
        return;
    }

    try {
        void fetch(debugEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sessionId: debugSessionId,
                runId: debugRunId,
                hypothesisId,
                location,
                message,
                data,
                timestamp: Date.now(),
            }),
        }).catch(() => {});
    } catch {
        // swallow any sync errors
    }
};

const fileTypeDisplayLabel = computed(() => {
    const ext = getExtension(props.file.file_name);
    if (ext) {
        return ext.toUpperCase();
    }
    const categoryLabels: Record<string, string> = {
        video: 'Video',
        audio: 'Audio',
        document: 'Document',
        spreadsheet: 'Spreadsheet',
        presentation: 'Presentation',
        text: 'Text',
        archive: 'Archive',
        unknown: 'File',
    };
    return categoryLabels[fileType.value] ?? 'File';
});

const isLargePdf = computed(
    () => fileType.value === 'pdf' && typeof props.file.file_size === 'number' && props.file.file_size > LARGE_PDF_THRESHOLD_BYTES
);

const hasFile = computed(() => Boolean(props.file.file_path));
const hasOcrText = computed(() => props.file.ocr_status === 'completed' && props.file.ocr_text);
const isAssemblyProcessing = computed(() => props.file.assembly_status === 'processing');
const isOcrProcessing = computed(() => hasFile.value && props.file.ocr_status === 'processing');
const isOcrFailed = computed(() => hasFile.value && props.file.ocr_status === 'failed');
const isOcrPending = computed(() => hasFile.value && props.file.ocr_status === 'pending');
const isNoFileAttached = computed(() => props.previewUnavailableReason === 'no_file');
const isPreviewUnavailable = computed(() => !props.previewAvailable);
const previewStateLabel = computed(() => {
    if (isAssemblyProcessing.value) {
        return 'Processing';
    }

    if (isOcrProcessing.value || isOcrPending.value) {
        return 'Queued';
    }

    if (isNoFileAttached.value) {
        return 'No file';
    }

    if (isPreviewUnavailable.value) {
        return 'Unavailable';
    }

    return fileTypeDisplayLabel.value;
});
const isDisposedRecord = computed(
    () => props.previewUnavailableReason === 'disposed' || props.file.retention_status === 'disposed'
);
const previewUnavailableTitle = computed(() => {
    if (isDisposedRecord.value) {
        return 'Disposed record';
    }

    if (isNoFileAttached.value) {
        return 'No file attached';
    }

    return 'File unavailable';
});
const previewUnavailableMessage = computed(() => {
    if (isDisposedRecord.value) {
        return 'This record has been disposed. The electronic file is no longer available for preview or download.';
    }

    if (isNoFileAttached.value) {
        return 'This record does not have a file attached yet. Upload a file to enable preview and OCR processing.';
    }

    return 'The file cannot be found in storage right now. Please contact an administrator if this persists.';
});


const handleDownload = () => {
    if (isPreviewUnavailable.value) {
        return;
    }

    const link = document.createElement('a');
    link.href = props.downloadUrl;
    link.download = props.file.file_name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const handleOpenPdfInNewTab = () => {
    if (fileType.value !== 'pdf') {
        return;
    }

    sendDebugLog('H2', 'FileViewer.vue:handleOpenPdfInNewTab', 'Opening PDF in new tab', {
        fileId: props.file.id,
        fileSize: props.file.file_size,
        isLargePdf: isLargePdf.value,
        previewUrl: props.previewUrl,
    });

    window.open(props.previewUrl, '_blank', 'noopener,noreferrer');
};

onMounted(() => {
    if (fileType.value === 'pdf') {
        sendDebugLog('H1', 'FileViewer.vue:onMounted', 'PDF viewer mounted', {
            fileId: props.file.id,
            fileSize: props.file.file_size,
            mimeType: props.file.mime_type,
            previewUrl: props.previewUrl,
            isLargePdf: isLargePdf.value,
        });

        if (isLargePdf.value) {
            sendDebugLog('H2', 'FileViewer.vue:onMounted', 'Large PDF detected in viewer', {
                fileId: props.file.id,
                fileSize: props.file.file_size,
            });
        }
    }
});

onBeforeUnmount(() => {
    if (fileType.value === 'pdf') {
        sendDebugLog('H1', 'FileViewer.vue:onBeforeUnmount', 'PDF viewer unmounted', {
            fileId: props.file.id,
        });
    }
});
</script>

<template>
    <!-- Modal Overlay -->
    <div class="fixed inset-0 z-50 flex flex-col bg-black/90" @click.self="onClose">
        <!-- Header -->
        <header
            class="flex items-center justify-between gap-4 border-b px-4 py-3 sm:px-6 border-gray-200 bg-white/95 backdrop-blur dark:border-neutral-800 dark:bg-neutral-900/95"
        >
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <button
                    type="button"
                    @click="onClose"
                    class="shrink-0 rounded-full p-1.5 transition text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                    aria-label="Close"
                >
                    <X class="h-5 w-5" />
                </button>
                <div class="min-w-0 flex-1">
                    <h1
                        class="truncate text-sm font-semibold text-gray-900 sm:text-base dark:text-neutral-100"
                        :title="file.file_name"
                    >
                        {{ file.file_name }}
                    </h1>
                    <p class="truncate text-xs text-gray-500 dark:text-neutral-400">
                        {{ formatFileSize(file.file_size) }} &middot; {{ formatDate(file.created_at) }}
                    </p>
                </div>
            </div>
        </header>

        <!-- Preview Area -->
        <main
            :class="[
                'flex flex-1 items-center justify-center overflow-hidden',
                fileType === 'pdf' ? '' : 'p-4 sm:p-6',
            ]"
            @click.stop
        >
            <!-- Image -->
            <template v-if="fileType === 'image'">
                <div class="flex h-full w-full items-center justify-center">
                    <img
                        :src="previewUrl"
                        :alt="file.file_name"
                        class="max-h-full max-w-full rounded-lg object-contain shadow-2xl"
                    />
                </div>
            </template>

            <!-- PDF -->
            <template v-else-if="fileType === 'pdf' && !isPreviewUnavailable">
                <div class="h-full w-full overflow-hidden">
                    <PDFViewer
                        :config="{
                            src: previewUrl,
                            theme: { preference: 'system' },
                            disabledCategories: isLargePdf
                                ? [
                                      'annotation',
                                      'redaction',
                                      'annotation-shape',
                                      'panel-comment',
                                      'document-open',
                                      'document-close',
                                      'document-capture',
                                  ]
                                : [
                                      'annotation',
                                      'redaction',
                                      'annotation-shape',
                                      'panel-comment',
                                      'document-open',
                                      'document-close',
                                      'document-capture',
                                  ],
                        }"
                        :style="{ width: '100%', height: '100%' }"
                    />
                </div>
            </template>

            <!-- All other types: file-type card + Download (and OCR if present) -->
            <template v-else>
                <div
                    class="flex max-w-3xl flex-col items-center gap-6 rounded-xl bg-white/90 p-8 text-center shadow-2xl backdrop-blur dark:bg-neutral-900/90"
                >
                    <div
                        class="flex h-24 w-24 items-center justify-center rounded-2xl bg-gray-200 dark:bg-neutral-700"
                    >
                        <FileTypeIcon
                            :mime-type="file.mime_type"
                            :filename="file.file_name"
                            class="h-12 w-12 text-gray-400 dark:text-neutral-400"
                        />
                    </div>

                    <div class="max-w-md space-y-2">
                        <p class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-neutral-300">
                            {{ previewStateLabel }}
                        </p>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-neutral-100">
                            <template v-if="isAssemblyProcessing">
                                <div class="flex items-center gap-2">
                                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-500 dark:border-neutral-600 dark:border-t-blue-400"></div>
                                    <span>Processing file...</span>
                                </div>
                            </template>
                            <template v-else-if="isOcrProcessing">
                                <div class="flex items-center gap-2">
                                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-blue-500 dark:border-neutral-600 dark:border-t-blue-400"></div>
                                    <span>Processing OCR...</span>
                                </div>
                            </template>
                            <template v-else-if="isOcrPending">
                                <div class="flex items-center gap-2">
                                    <div class="h-3 w-3 rounded-full bg-amber-400 animate-pulse dark:bg-amber-500"></div>
                                    <span>Queued for OCR</span>
                                </div>
                            </template>
                            <template v-else-if="isPreviewUnavailable">
                                {{ previewUnavailableTitle }}
                            </template>
                            <template v-else-if="isOcrFailed">
                                OCR Processing Failed
                            </template>
                            <template v-else-if="hasOcrText">
                                Extracted Text Preview
                            </template>
                            <template v-else>
                                No preview for this file type
                            </template>
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-neutral-400">
                            <template v-if="isAssemblyProcessing">
                                The file upload is still being finalized. Preview will be available shortly.
                            </template>
                            <template v-else-if="isOcrProcessing">
                                The file is being analyzed using optical character recognition.
                            </template>
                            <template v-else-if="isOcrPending">
                                This file is waiting to be processed. OCR will begin shortly.
                            </template>
                            <template v-else-if="isPreviewUnavailable">
                                {{ previewUnavailableMessage }}
                            </template>
                            <template v-else-if="isOcrFailed">
                                <div class="space-y-1">
                                    <p v-if="file.ocr_error" class="font-mono text-xs text-red-600 dark:text-red-400">
                                        {{ file.ocr_error }}
                                    </p>
                                    <p v-else>Unable to extract text from this file.</p>
                                </div>
                            </template>
                            <template v-else>
                                {{ file.mime_type || 'Unknown file type' }}
                            </template>
                        </p>
                    </div>

                    <!-- OCR Text Preview -->
                    <div
                        v-if="hasOcrText"
                        class="w-full max-w-2xl"
                    >
                        <div
                            class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-left font-mono text-sm text-gray-800 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                        >
                            <pre class="whitespace-pre-wrap wrap-break-word">{{ file.ocr_text }}</pre>
                        </div>
                        <p class="mt-2 text-xs text-gray-400 dark:text-neutral-500">
                            Text extracted via OCR
                        </p>
                    </div>

                    <button
                        v-if="!isPreviewUnavailable && !isNoFileAttached"
                        type="button"
                        @click="handleDownload"
                        class="flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2.5 font-medium text-white shadow-lg transition hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-500"
                    >
                        <Download class="h-4 w-4" />
                        Download to view
                    </button>
                </div>
            </template>
        </main>
    </div>
</template>
