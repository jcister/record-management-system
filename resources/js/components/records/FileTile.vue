<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Bookmark, Eye, ShieldCheck, Loader2, Ban } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import FilePreview from './FilePreview.vue';
import ItemMenu from './ItemMenu.vue';
import FileVersionHistoryDialog from './FileVersionHistoryDialog.vue';
import { useLongPress } from '@/utils/useLongPress';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { type File } from '@/types/schema';
import type { SelectedItemType } from '@/types';
import { formatFileSize, formatDate, formatRequestCode } from '@/utils/format';
import { retentionStatusBadgeClass, retentionStatusLabel } from '@/utils/retentionStatus';

const props = withDefaults(defineProps<{
    file: File;
    isSelected?: boolean;
    isHighlighted?: boolean;
    currentFolderId?: string | null;
    currentFolderName?: string | null;
    isDragging?: boolean;
    isDropTarget?: boolean;
    draggable?: boolean;
    showActions?: boolean;
    contextMode?: 'default' | 'trash';
    showPendingActions?: boolean;
    pendingRequestLink?: string | null;
    isApprovingPendingScan?: boolean;
    selectedItems?: SelectedItemType[];
    selectionCount?: number;
    showBookmark?: boolean;
    showStatus?: boolean;
}>(), {
    draggable: true,
    showActions: true,
    contextMode: 'default',
    showPendingActions: false,
    isHighlighted: false,
    pendingRequestLink: null,
    isApprovingPendingScan: false,
    selectedItems: () => [],
    selectionCount: 0,
    showBookmark: true,
    showStatus: true,
});

const emit = defineEmits<{
    click: [event: MouseEvent];
    dblclick: [event: MouseEvent];
    unbookmarked: [itemId: string, itemType: 'folder' | 'file'];
    attachfile: [file: File];
    showversions: [file: File];
    longpress: [];
    dragstart: [event: DragEvent];
    dragend: [event: DragEvent];
    dragover: [event: DragEvent];
    dragleave: [event: DragEvent];
    drop: [event: DragEvent];
    contextmenu: [event: MouseEvent];
    restore: [{ id: string; type: 'folder' | 'file' }];
    'delete-permanent': [{ id: string; type: 'folder' | 'file' }];
    'preview-pending': [file: File];
    'approve-pending': [file: File];
    'reject-pending': [file: File];
}>();



const { startPress, cancelPress, handleClick, handleDblClick } = useLongPress(emit);
const { isTouchDevice } = usePointerCapabilities();
const isDragEnabled = computed(() => !isTouchDevice.value && props.draggable);
const hasFile = computed(() => Boolean(props.file.file_path));
const versionDialogOpen = ref(false);
const currentVersionNumber = computed(() => props.file.current_version?.version_number ?? null);
const showVersionBadge = computed(() => (currentVersionNumber.value ?? 1) > 1);
const versionBadgeLabel = computed(() => {
    if (!showVersionBadge.value) {
        return null;
    }

    if (props.file.current_version?.version_label) {
        return props.file.current_version.version_label;
    }

    return `v${currentVersionNumber.value}`;
});

const pendingScanMetadata = computed(() => props.file.pending_scan_metadata ?? null);
const isPendingScan = computed(() => pendingScanMetadata.value !== null);
const isProcessing = computed(
    () => props.file.assembly_status === 'processing'
        || (hasFile.value && props.file.ocr_status === 'pending')
        || (hasFile.value && props.file.ocr_status === 'processing')
);
const showStoredBadge = computed(
    () => !hasFile.value && props.file.assembly_status !== 'processing'
);
const processingBadgeLabel = computed(() => {
    if (props.file.assembly_status === 'processing') {
        return 'File Processing';
    }

    if (props.file.ocr_status === 'processing') {
        return 'OCR Processing';
    }

    if (props.file.ocr_status === 'pending') {
        return 'OCR Pending';
    }

    return 'Processing';
});
const isAssemblyFailed = computed(() => props.file.assembly_status === 'failed');

const showPendingActions = computed(() => Boolean(props.showPendingActions && isPendingScan.value));
const pendingRequestCode = computed(() => {
    const meta = pendingScanMetadata.value;

    if (!meta) {
        return null;
    }

    return formatRequestCode(meta.request_number, meta.request_id);
});
const pendingRequestLink = computed(() => props.pendingRequestLink ?? null);

const pendingBadgeText = computed(() => {
    if (!isPendingScan.value) {
        return null;
    }

    const snippet = pendingRequestCode.value;
    const fileName = props.file.file_name;

    // limit filename to 20 characters
    const maxLength = 20;
    const truncatedFileName =
        fileName.length > maxLength
            ? fileName.slice(0, maxLength) + '...'
            : fileName;

    if (snippet) {
        return `Awaiting approval for ${truncatedFileName} via ${snippet}`;
    }

    return `Awaiting approval for ${truncatedFileName}`;
});

const displayDate = computed(() => formatDate(props.file.updated_at ?? props.file.created_at));

const pendingBadgeFullText = computed(() => {
    if (!isPendingScan.value) {
        return null;
    }

    const snippet = pendingRequestCode.value;
    const fileName = props.file.file_name;

    if (snippet) {
        return `Awaiting approval for ${fileName} via ${snippet}`;
    }

    return `Awaiting approval for ${fileName}`;
});

const pendingBadgeClasses = computed(() => {
    const classes = [
        'text-[10px]',
        'font-semibold',
        'tracking-wide',
        'leading-tight',
        'px-0.5',
        'py-0.5',
        'whitespace-nowrap',
        'sm:whitespace-normal',
        'sm:break-words',
        'text-center',
    ];

    if (pendingRequestLink.value) {
        classes.push(
            'cursor-pointer',
            'hover:bg-primary/10',
            'hover:text-primary',
            'dark:hover:bg-primary/20'
        );
    }

    return classes;
});

const pendingBadgeShortText = computed(() => {
    if (!pendingBadgeText.value) {
        return null;
    }

    return 'Awaiting approval…';
});

const displaySize = computed(() => {
    if (isProcessing.value && (!hasFile.value || !props.file.file_size)) {
        return 'Processing file...';
    }

    if (!hasFile.value || !props.file.file_size) {
        return 'No file attached';
    }

    return formatFileSize(props.file.file_size);
});

const retentionLabel = computed(() => retentionStatusLabel(props.file.retention_status));
const retentionClass = computed(() => retentionStatusBadgeClass(props.file.retention_status));

const handlePreviewPending = (): void => {
    emit('preview-pending', props.file);
};

const handleApprovePending = (): void => {
    emit('approve-pending', props.file);
};

const handleRejectPending = (): void => {
    emit('reject-pending', props.file);
};

const handleShowVersions = (): void => {
    emit('showversions', props.file);
    versionDialogOpen.value = true;
};

const handleVersionRestored = (): void => {
    emit('showversions', props.file);
};
</script>

<template>
    <ItemMenu
        :item-id="file.id"
        item-type="file"
        :item-name="file.file_name"
        :is-bookmarked="file.is_bookmarked"
        :bookmark-id="file.bookmark_id"
        :current-folder-id="currentFolderId"
        :current-folder-name="currentFolderName"
        :has-file="hasFile"
        :metadata="file"
        :mode="contextMode"
        trigger-type="context"
        :selected-items="selectedItems"
        :selection-count="selectionCount"
        @restore="emit('restore', $event)"
        @delete-permanent="emit('delete-permanent', $event)"
        @unbookmarked="emit('unbookmarked', $event, 'file')"
        @attach-file="emit('attachfile', file)"
        @show-versions="handleShowVersions"
    >
        <Card
            class="cursor-pointer transition-all hover:shadow-lg hover:scale-101 group relative h-full min-h-[10rem] md:min-h-[13rem]"
            :class="[
                (isSelected || isHighlighted) ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-blue-900/40' : '',
                {
                    'ring-4 ring-blue-500 bg-blue-50 dark:bg-blue-950': isDropTarget,
                    'opacity-50': isDragging,
                }
            ]"
            :data-file-id="file.id"
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
                    :item-id="file.id"
                    item-type="file"
                    :item-name="file.file_name"
                    :is-bookmarked="file.is_bookmarked"
                    :bookmark-id="file.bookmark_id"
                    :current-folder-id="currentFolderId"
                    :current-folder-name="currentFolderName"
                    :has-file="hasFile"
                    :metadata="file"
                    :mode="contextMode"
                    trigger-type="button"
                    :inline="true"
                    :selected-items="selectedItems"
                    :selection-count="selectionCount"
                    @restore="emit('restore', $event)"
                    @delete-permanent="emit('delete-permanent', $event)"
                    @unbookmarked="emit('unbookmarked', $event, 'file')"
                    @attach-file="emit('attachfile', file)"
                    @show-versions="handleShowVersions"
                />
            </div>
            <div
                v-if="pendingBadgeText"
                class="absolute top-2 left-1/2 -translate-x-1/2 z-10 flex w-full justify-center pointer-events-none"
            >
                <div class="pointer-events-auto max-w-[calc(100%-3rem)] w-full">
                    <TooltipProvider :delay-duration="200">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <component
                                    :is="pendingRequestLink ? Link : 'div'"
                                    v-bind="pendingRequestLink ? { href: pendingRequestLink } : {}"
                                    class="inline-flex max-w-full justify-center"
                                >
                                    <Badge variant="secondary"
                                        :class="['justify-center truncate max-w-full sm:max-w-[calc(100%-2.5rem)]', ...pendingBadgeClasses]">
                                        <span class="sm:hidden">{{ pendingBadgeShortText }}</span>
                                        <span class="hidden sm:inline">{{ pendingBadgeText }}</span>
                                    </Badge>
                                </component>
                            </TooltipTrigger>
                            <TooltipContent class="max-w-xs text-center">
                                {{ pendingBadgeFullText ?? pendingBadgeText }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
            <div v-if="showBookmark && file.is_bookmarked" class="absolute top-2 left-2 z-10">
                <div class="h-8 w-8 rounded-full bg-yellow-400 flex items-center justify-center shadow-md">
                    <Bookmark class="h-4 w-4 text-yellow-900 fill-yellow-900" />
                </div>
            </div>
            <slot name="overlay" />
            <CardContent class="h-full p-4 md:p-6">
                <div class="flex h-full flex-col items-center text-center space-y-3 md:space-y-4">
                    <FilePreview :file="file" />
                    <div class="w-full flex-1 flex flex-col items-center justify-center text-center">
                        <h3 class="font-semibold text-sm md:text-base truncate w-full">{{ file.file_name }}</h3>
                        <div class="flex flex-wrap items-center justify-center gap-1 mt-1">
                            <Badge v-if="isAssemblyFailed" variant="destructive" class="text-[10px]">
                                Upload failed
                            </Badge>
                            <Badge v-else-if="isProcessing" variant="secondary" class="text-[10px] gap-1">
                                <Loader2 class="h-3 w-3 animate-spin" />
                                {{ processingBadgeLabel }}
                            </Badge>
                            <Badge v-else-if="showStoredBadge" variant="secondary" class="text-[10px]">
                                Stored
                            </Badge>
                            <Badge v-if="showVersionBadge" variant="outline" class="text-[10px] font-semibold uppercase tracking-wide">
                                {{ versionBadgeLabel }}
                            </Badge>
                            <Badge v-if="showStatus" variant="outline" :class="['text-[10px] font-semibold', retentionClass]">
                                {{ retentionLabel }}
                            </Badge>
                            <Badge v-if="file.is_record === false" variant="secondary" class="text-[10px]">
                                Stored
                            </Badge>
                        </div>
                        <p class="text-[11px] md:text-xs text-muted-foreground mt-1">
                            {{ displayDate }}
                        </p>
                        <p
                            class="text-[11px] md:text-xs"
                            :class="hasFile ? 'text-muted-foreground' : 'text-amber-700 dark:text-amber-400'"
                        >
                            {{ displaySize }}
                        </p>
                        <p v-if="pendingScanMetadata && !showPendingActions"
                            class="text-[10px] md:text-xs text-amber-700 mt-1 dark:text-amber-400">
                            Awaiting approval for {{ file.file_name }}
                            <template v-if="pendingRequestCode">
                                <span class="mx-0.4">via</span>
                                <Link v-if="pendingRequestLink" :href="pendingRequestLink" class="text-primary hover:underline">
                                    {{ pendingRequestCode }}
                                </Link>
                                <span v-else>
                                    {{ pendingRequestCode }}
                                </span>
                            </template>
                        </p>
                    </div>
                    <div v-if="showPendingActions" class="w-full space-y-3">
                         <div class="flex flex-col items-center gap-2">
                            <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-xs whitespace-nowrap w-full"
                            @click.stop="handlePreviewPending"
                            >
                            <Eye class="mr-1.5 h-4 w-4" />
                            Preview
                            </Button>

                            <Button
                            type="button"
                            size="sm"
                            class="text-xs whitespace-nowrap w-full"
                            :disabled="isApprovingPendingScan"
                            @click.stop="handleApprovePending"
                            >
                            <Loader2
                                v-if="isApprovingPendingScan"
                                class="mr-1.5 h-4 w-4 animate-spin"
                            />
                            <ShieldCheck v-else class="mr-1.5 h-4 w-4" />
                            {{ isApprovingPendingScan ? 'Approving...' : 'Approve' }}
                            </Button>

                            <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            class="text-xs whitespace-nowrap w-full"
                            @click.stop="handleRejectPending"
                            >
                            <Ban class="mr-1.5 h-4 w-4" />
                             Reject
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
            <FileVersionHistoryDialog
                v-model:open="versionDialogOpen"
                :file-id="file.id"
                :file-name="file.file_name"
                :current-version-id="file.current_version_id"
                @restored="handleVersionRestored"
            />
        </Card>
    </ItemMenu>
</template>
