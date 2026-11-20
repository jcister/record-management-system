<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import FileTypeIcon from '@/components/icons/FileTypeIcon.vue';
import { detectFileCategory, getExtension } from '@/utils/fileTypes';

interface FilePreviewProps {
    file: {
        id: string;
        file_name: string;
        mime_type?: string | null;
        current_version_id?: string | number | null;
        latest_version_number?: number | null;
        updated_at?: string | null;
        assembly_status?: 'processing' | 'completed' | 'failed' | null;
        ocr_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    };
    dense?: boolean;
}

const props = defineProps<FilePreviewProps>();

const fileType = computed(() => detectFileCategory(props.file.mime_type, props.file.file_name));
const thumbnailFailed = ref(false);
const thumbnailRetryCount = ref(0);

const MAX_THUMBNAIL_RETRIES = 6;
const THUMBNAIL_RETRY_DELAY_MS = 2500;
let thumbnailRetryTimer: ReturnType<typeof setTimeout> | null = null;

const isProcessing = computed(
    () => props.file.assembly_status === 'processing'
        || props.file.ocr_status === 'pending'
        || props.file.ocr_status === 'processing'
);

const cacheBustingToken = computed(() => {
    const candidate = [
        props.file.current_version_id,
        props.file.latest_version_number,
        props.file.updated_at,
    ].find((value): value is string | number => {
        if (value === undefined || value === null) {
            return false;
        }

        return String(value).trim() !== '';
    });

    return candidate ? String(candidate) : null;
});

const baseUrl = computed(() => buildUrl(`/api/files/${props.file.id}/view`));
const absoluteFileUrl = computed(() => toAbsoluteUrl(baseUrl.value));
const thumbnailUrl = computed(() => {
    if (fileType.value !== 'pdf') {
        return null;
    }

    let url = buildUrl(`/api/files/${props.file.id}/thumbnail`);

    if (thumbnailRetryCount.value > 0) {
        const separator = url.includes('?') ? '&' : '?';
        url += `${separator}retry=${thumbnailRetryCount.value}`;
    }

    return toAbsoluteUrl(url);
});

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

const containerClasses = computed(() => props.dense ? 'h-20 rounded-lg' : 'aspect-[4/3] rounded-xl');

const showThumbnail = computed(() => thumbnailUrl.value !== null && !thumbnailFailed.value);

function clearThumbnailRetryTimer(): void {
    if (thumbnailRetryTimer !== null) {
        clearTimeout(thumbnailRetryTimer);
        thumbnailRetryTimer = null;
    }
}

function scheduleThumbnailRetry(): void {
    if (!isProcessing.value || thumbnailRetryCount.value >= MAX_THUMBNAIL_RETRIES || thumbnailRetryTimer !== null) {
        return;
    }

    thumbnailRetryTimer = setTimeout(() => {
        thumbnailRetryTimer = null;
        thumbnailRetryCount.value += 1;
        thumbnailFailed.value = false;
    }, THUMBNAIL_RETRY_DELAY_MS);
}

function buildUrl(path: string): string {
    let url = path;

    if (cacheBustingToken.value) {
        const separator = path.includes('?') ? '&' : '?';
        url += `${separator}v=${encodeURIComponent(cacheBustingToken.value)}`;
    }

    return url;
}

function toAbsoluteUrl(url: string): string {
    if (typeof window === 'undefined') {
        return url;
    }

    try {
        return new URL(url, window.location.origin).toString();
    } catch (error) {
        console.error('Failed to build preview URL', error);
        return url;
    }
}

function handleThumbnailError(): void {
    thumbnailFailed.value = true;
    scheduleThumbnailRetry();
}

watch(
    () => [props.file.current_version_id, props.file.latest_version_number, props.file.updated_at],
    () => {
        clearThumbnailRetryTimer();
        thumbnailRetryCount.value = 0;
        thumbnailFailed.value = false;
    }
);

watch(
    isProcessing,
    (processing) => {
        if (!processing) {
            clearThumbnailRetryTimer();
        }
    }
);

onBeforeUnmount(() => {
    clearThumbnailRetryTimer();
});
</script>

<template>
    <div
        class="relative w-full overflow-hidden border border-border/60 bg-muted/40"
        :class="containerClasses"
    >
        <template v-if="fileType === 'image'">
            <img
                :src="absoluteFileUrl"
                :alt="file.file_name"
                class="h-full w-full object-cover object-top"
                loading="lazy"
                decoding="async"
            />
        </template>

        <template v-else-if="fileType === 'pdf'">
            <img
                v-if="showThumbnail"
                :src="thumbnailUrl ?? undefined"
                :alt="file.file_name"
                class="h-full w-full object-cover object-top"
                loading="lazy"
                decoding="async"
                @error="handleThumbnailError"
            />
            <div v-else class="flex h-full flex-col items-center justify-center gap-2 bg-red-50 text-center">
                <FileTypeIcon :mime-type="file.mime_type" :filename="file.file_name" class="h-12 w-12 text-red-500" />
                <p class="text-xs font-semibold text-red-600">PDF</p>
            </div>
        </template>

        <template v-else>
            <div class="flex h-full flex-col items-center justify-center gap-2 bg-muted/50 p-4 text-center">
                <FileTypeIcon :mime-type="file.mime_type" :filename="file.file_name" class="h-12 w-12" />
                <p class="text-xs font-semibold text-muted-foreground">{{ fileTypeDisplayLabel }}</p>
            </div>
        </template>
    </div>
</template>
