<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Bookmark, Eye, ShieldCheck, Loader2, Ban } from 'lucide-vue-next';
import FileTypeIcon from '@/components/icons/FileTypeIcon.vue';
import ItemMenu from '@/components/records/ItemMenu.vue';
import FileVersionHistoryDialog from '@/components/records/FileVersionHistoryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
// import Checkbox from '@/components/ui/checkbox/Checkbox.vue';
import { useLongPress } from '@/utils/useLongPress';
import { usePointerCapabilities } from '@/composables/usePointerCapabilities';
import { type File } from '@/types/schema';
import type { SelectedItemType } from '@/types';
import { formatDate, formatFileSize, formatRequestCode } from '@/utils/format';
import { retentionStatusBadgeClass, retentionStatusLabel } from '@/utils/retentionStatus';
import requestsRoutes from '@/routes/requests';

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
    showVersion?: boolean;
    showDate?: boolean;
    showSize?: boolean;
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
    showVersion: true,
    showDate: true,
    showSize: true,
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
// const checkboxVisible = computed(() => isTouchDevice.value || Boolean(props.isSelected));
// const checkboxClasses = computed(() => [
//     'transition-opacity duration-150 transition-[width,margin] shrink-0 cursor-pointer',
//     checkboxVisible.value
//         ? 'opacity-100 pointer-events-auto w-4 mr-1'
//         : 'opacity-0 pointer-events-none w-0 mr-0 group-hover:opacity-100 group-hover:pointer-events-auto group-hover:w-4 group-hover:mr-1',
// ]);
const versionDialogOpen = ref(false);
const currentVersionNumber = computed(() => props.file.current_version?.version_number ?? 1);
const versionLabel = computed(() => {
    if (props.file.current_version?.version_label) {
        return props.file.current_version.version_label;
    }

    return `v${currentVersionNumber.value}`;
});

const displayDate = computed(() => formatDate(props.file.updated_at ?? props.file.created_at));

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
const showPendingActionButtons = computed(() => Boolean(props.showPendingActions && isPendingScan.value));
const displayVersionLabel = computed(() => (isPendingScan.value ? '—' : versionLabel.value));

type PendingScanDetails = {
    targetLabel: string;
    requestLabel: string | null;
    requestUrl: string | null;
};

const buildRequestHighlightUrl = (requestNumber?: string | null): string | null => {
    if (!requestNumber) {
        return null;
    }

    return requestsRoutes.index.url({
        query: { highlight: requestNumber },
    });
};

const pendingRequestUrl = computed(() => {
    if (props.pendingRequestLink) {
        return props.pendingRequestLink;
    }

    return buildRequestHighlightUrl(pendingScanMetadata.value?.request_number);
});

const pendingRequestCode = computed(() => {
    const meta = pendingScanMetadata.value;

    if (!meta) {
        return null;
    }

    return formatRequestCode(meta.request_number, meta.request_id);
});

const pendingBadgeText = computed(() => {
    if (!isPendingScan.value) {
        return null;
    }

    const snippet = pendingRequestCode.value;
    const fileName = props.file.file_name;

    const maxLength = 20;
    const truncatedFileName = fileName.length > maxLength
        ? `${fileName.slice(0, maxLength)}...`
        : fileName;

    if (snippet) {
        return `Awaiting approval for ${truncatedFileName} via ${snippet}`;
    }

    return `Awaiting approval for ${truncatedFileName}`;
});

const pendingBadgeClasses = computed(() => {
    const classes = ['shrink-0', 'text-[10px]', 'font-semibold', 'tracking-wide', 'whitespace-nowrap'];

    if (pendingRequestUrl.value) {
        classes.push(
            'cursor-pointer',
            'hover:bg-primary/10',
            'hover:text-primary',
            'dark:hover:bg-primary/20'
        );
    }

    return classes;
});

const pendingScanDetails = computed<PendingScanDetails | null>(() => {
    const meta = pendingScanMetadata.value;

    if (!meta) {
        return null;
    }

    return {
        targetLabel: meta.target_file_name ?? 'linked record',
        requestLabel: pendingRequestCode.value,
        requestUrl: pendingRequestUrl.value,
    };
});

const showPendingBadge = computed(() => Boolean(pendingBadgeText.value));
const showPendingDetails = computed(() => Boolean(pendingScanDetails.value && !showPendingActionButtons.value));

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
    <ItemMenu :item-id="file.id" item-type="file" :item-name="file.file_name" :is-bookmarked="file.is_bookmarked"
        :bookmark-id="file.bookmark_id" :current-folder-id="currentFolderId" :current-folder-name="currentFolderName"
        :has-file="hasFile" :metadata="file" :mode="contextMode" trigger-type="context" :selected-items="selectedItems"
        :selection-count="selectionCount" @restore="emit('restore', $event)"
        @delete-permanent="emit('delete-permanent', $event)" @unbookmarked="emit('unbookmarked', $event, 'file')"
        @attach-file="emit('attachfile', file)" @show-versions="handleShowVersions">
        <div class="flex w-full min-w-0 flex-wrap overflow-hidden sm:flex-nowrap sm:flex-row items-start sm:items-center gap-y-2 py-3 border-b last:border-b-0 cursor-pointer transition-colors group sm:overflow-hidden"
            :class="[
                'border-l-4',
                (isSelected || isHighlighted)
                    ? 'border-l-blue-500 ring-0 bg-blue-50 dark:bg-blue-900/40 hover:bg-blue-100 dark:hover:bg-blue-900/60'
                    : 'border-transparent hover:bg-accent/5',
                'sm:hover:bg-blue-50/60',
                {
                    'ring-inset bg-blue-50 dark:bg-blue-950': isDropTarget,
                    'opacity-50': isDragging,
                }
            ]"
            :data-file-id="file.id"
            :draggable="isDragEnabled" @click.stop="handleClick" @dblclick.stop="handleDblClick"
            @touchstart.passive.stop="startPress" @touchend.passive.stop="cancelPress"
            @touchcancel.passive.stop="cancelPress" @mousedown.stop="startPress" @mouseup.stop="cancelPress"
            @mouseleave.stop="cancelPress" @dragstart="emit('dragstart', $event)" @dragend="emit('dragend', $event)"
            @dragover="emit('dragover', $event)" @dragleave="emit('dragleave', $event)" @drop="emit('drop', $event)"
            @contextmenu="emit('contextmenu', $event)">
            <!-- Mobile layout -->
            <div class="w-full sm:hidden space-y-3">
                <div class="flex gap-1 min-w-0">
                    <div class="flex items-start gap-1 min-w-0 flex-1">
                        <!-- <Checkbox
                        :model-value="Boolean(isSelected)"
                        :class="checkboxClasses"
                        class="mt-0.5"
                        @click.stop="emit('click', $event)"
                        @mousedown.stop
                        @touchstart.stop
                    /> -->
                        <FileTypeIcon :mime-type="file.mime_type" class="h-6 w-6 shrink-0" />
                        <div class="min-w-0 flex-1 flex flex-col gap-2">
                            <span
                                class="block max-w-[32ch] truncate font-medium text-sm sm:max-w-[44ch]"
                                :title="file.file_name"
                            >{{ file.file_name }}</span>
                            <div class="flex flex-wrap items-center gap-2 pr-12">
                                <Badge v-if="isProcessing" variant="secondary" class="shrink-0 text-[10px] px-2 gap-1">
                                    <Loader2 class="h-3 w-3 animate-spin" />
                                    {{ processingBadgeLabel }}
                                </Badge>
                                <Badge v-else-if="isAssemblyFailed" variant="destructive" class="shrink-0 text-[10px] px-2">
                                    Upload failed
                                </Badge>
                                <Badge v-else-if="showStoredBadge" variant="secondary" class="shrink-0 text-[10px] px-2">
                                    Stored
                                </Badge>
                                <template v-if="showPendingBadge">
                                    <Link v-if="pendingRequestUrl" :href="pendingRequestUrl" class="inline-flex">
                                        <Badge variant="secondary" :class="pendingBadgeClasses">
                                            {{ pendingBadgeText }}
                                        </Badge>
                                    </Link>
                                    <Badge v-else variant="secondary" :class="pendingBadgeClasses">
                                        {{ pendingBadgeText }}
                                    </Badge>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <slot name="actions" />
                        <ItemMenu v-if="showActions" :item-id="file.id" item-type="file" :item-name="file.file_name"
                            :is-bookmarked="file.is_bookmarked" :bookmark-id="file.bookmark_id"
                            :current-folder-id="currentFolderId" :current-folder-name="currentFolderName"
                            :has-file="hasFile" :metadata="file" :mode="contextMode" trigger-type="button"
                            :inline="true" :selected-items="selectedItems" :selection-count="selectionCount"
                            @restore="emit('restore', $event)" @delete-permanent="emit('delete-permanent', $event)"
                            @unbookmarked="emit('unbookmarked', $event, 'file')" @attach-file="emit('attachfile', file)"
                            @show-versions="handleShowVersions" />
                    </div>
                </div>
                <div v-if="showPendingActionButtons" class="flex items-center gap-2 flex-nowrap justify-end w-full">
                    <Button type="button" size="icon" variant="outline" class="h-9 w-9"
                        @click.stop="handlePreviewPending">
                        <Eye class="h-4 w-4" />
                        <span class="sr-only">Preview</span>
                    </Button>
                    <Button type="button" size="icon" class="h-9 w-9" :disabled="props.isApprovingPendingScan"
                        @click.stop="handleApprovePending">
                        <Loader2 v-if="props.isApprovingPendingScan" class="h-4 w-4 animate-spin" />
                        <ShieldCheck v-else class="h-4 w-4" />
                        <span class="sr-only">{{ props.isApprovingPendingScan ? 'Approving...' : 'Approve' }}</span>
                    </Button>
                    <Button type="button" size="icon" variant="destructive" class="h-9 w-9"
                        @click.stop="handleRejectPending">
                        <Ban class="h-4 w-4" />
                        <span class="sr-only">Reject Record</span>
                    </Button>
                </div>
            </div>

            <!-- Mobile meta row -->
            <div class="w-full mt-1 text-xs text-muted-foreground sm:hidden">
                <template v-if="props.showDate">
                    <span>{{ displayDate }}</span>
                </template>
                <template v-if="props.showDate && props.showSize"> • </template>
                <template v-if="props.showSize">
                    <span>{{ displaySize }}</span>
                </template>
                <template v-if="(props.showDate || props.showSize) && props.showVersion"> • </template>
                <template v-if="props.showVersion">
                    <span>{{ displayVersionLabel }}</span>
                </template>
                <template v-if="props.showStatus && (props.showDate || props.showSize || props.showVersion)"> • </template>
                <span v-if="props.showStatus">Status: {{ retentionLabel }}</span>
            </div>

            <!-- Desktop columns -->
            <div class="hidden sm:flex sm:w-4/12 items-start gap-3 min-w-0">
                <FileTypeIcon :mime-type="file.mime_type" class="h-6 w-6 shrink-0 mx-1.5" />
                <div class="min-w-0 flex flex-col gap-1 w-full">
                    <div class="flex items-center gap-2 min-w-0 flex-nowrap">
                        <span
                            :class="[
                                'block truncate flex-1 min-w-0',
                                isPendingScan ? 'min-w-[14rem] max-w-[28ch] sm:max-w-[32ch]' : 'max-w-[32ch] sm:max-w-[44ch]'
                            ]"
                            :title="file.file_name"
                        >{{ file.file_name }}</span>
                        <div class="flex items-center gap-2 shrink-0">
                            <Badge v-if="isProcessing" variant="secondary" class="shrink-0 text-[10px] mx-1 gap-1">
                                <Loader2 class="h-3 w-3 animate-spin" />
                                {{ processingBadgeLabel }}
                            </Badge>
                            <template v-if="showPendingBadge">
                                <Link v-if="pendingRequestUrl" :href="pendingRequestUrl" class="inline-flex">
                                    <Badge variant="secondary" :class="pendingBadgeClasses">
                                        {{ pendingBadgeText }}
                                    </Badge>
                                </Link>
                                <Badge v-else variant="secondary" :class="pendingBadgeClasses">
                                    {{ pendingBadgeText }}
                                </Badge>
                            </template>
                        </div>
                        <!-- <Badge v-if="file.is_record === false" variant="secondary" class="shrink-0 text-[10px] mx-5">
                            Stored
                        </Badge> -->
                        <div v-if="props.showBookmark" class="ml-auto">
                            <Bookmark v-if="file.is_bookmarked"
                                class="h-4 w-4 shrink-0 mx-5 text-yellow-500 fill-yellow-500" />
                            <Bookmark v-else class="h-4 w-4 shrink-0 mx-5 text-gray-500 " />
                        </div>

                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge v-if="isAssemblyFailed" variant="destructive" class="shrink-0 text-[10px]">
                            Upload failed
                        </Badge>
                    </div>
                </div>
            </div>

            <div v-if="props.showDate" class="hidden sm:block sm:w-1/6 text-xs text-muted-foreground">
                {{ displayDate }}
            </div>
            <div v-if="props.showSize" class="hidden sm:block sm:w-1/6 text-xs text-muted-foreground">
                {{ displaySize }}
            </div>

            <div v-if="props.showStatus" class="hidden sm:block sm:w-1/6 text-xs text-muted-foreground">
                <Badge variant="outline" :class="['text-[10px] font-semibold', retentionClass]">
                    {{ retentionLabel }}
                </Badge>
            </div>

            <div v-if="props.showVersion" class="hidden lg:block lg:w-1/12 text-xs text-muted-foreground">
                {{ displayVersionLabel }}
            </div>

            <div class="hidden sm:flex sm:flex-1 lg:w-1/12 sm:text-right items-center justify-end gap-2 ml-auto">
                <template v-if="showPendingActionButtons">
                    <Button type="button" size="sm" variant="outline" class="text-xs w-full sm:w-auto"
                        @click.stop="handlePreviewPending">
                        <Eye class="h-4 w-4" />
                        <span class="sr-only">Preview</span>
                    </Button>
                    <Button type="button" size="sm" class="text-xs w-full sm:w-auto"
                        :disabled="props.isApprovingPendingScan" @click.stop="handleApprovePending">
                        <Loader2 v-if="props.isApprovingPendingScan" class="h-4 w-4 animate-spin" />
                        <ShieldCheck v-else class="h-4 w-4" />
                        <span class="sr-only">{{ props.isApprovingPendingScan ? 'Approving...' : 'Approve' }}</span>
                    </Button>
                    <Button type="button" size="sm" variant="destructive" class="text-xs w-full sm:w-auto"
                        @click.stop="handleRejectPending">
                        <Ban class="h-4 w-4" />
                        <span class="sr-only">Reject</span>
                    </Button>
                </template>
                <slot name="actions" />
                <ItemMenu v-if="showActions" :item-id="file.id" item-type="file" :item-name="file.file_name"
                    :is-bookmarked="file.is_bookmarked" :bookmark-id="file.bookmark_id"
                    :current-folder-id="currentFolderId" :current-folder-name="currentFolderName" :has-file="hasFile"
                    :metadata="file" :mode="contextMode" trigger-type="button" :inline="true"
                    :selected-items="selectedItems" :selection-count="selectionCount" @restore="emit('restore', $event)"
                    @delete-permanent="emit('delete-permanent', $event)"
                    @unbookmarked="emit('unbookmarked', $event, 'file')" @attach-file="emit('attachfile', file)"
                    @show-versions="handleShowVersions" />
            </div>
            <div v-if="showPendingDetails" class="w-full text-xs text-amber-700 dark:text-amber-400 mt-1">
                Awaiting approval for
                <span class="font-medium text-foreground dark:text-white">{{ pendingScanDetails?.targetLabel }}</span>
                <template v-if="pendingScanDetails?.requestLabel">
                    via
                    <Link v-if="pendingScanDetails?.requestUrl" :href="pendingScanDetails?.requestUrl"
                        class="font-semibold text-primary hover:underline">
                        {{ pendingScanDetails?.requestLabel }}
                    </Link>
                    <span v-else class="font-semibold text-foreground dark:text-white">
                        {{ pendingScanDetails?.requestLabel }}
                    </span>
                </template>
            </div>
            <FileVersionHistoryDialog v-model:open="versionDialogOpen" :file-id="file.id" :file-name="file.file_name"
                :current-version-id="file.current_version_id" @restored="handleVersionRestored" />
        </div>
    </ItemMenu>
</template>
