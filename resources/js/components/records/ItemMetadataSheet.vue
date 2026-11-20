<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import Spinner from '@/components/ui/spinner/Spinner.vue';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Download, MoreVertical, RotateCcw } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import { apiClient } from '@/lib/axios';
import versionsRoutes from '@/routes/api/files/versions';
import type { FileVersion } from '@/types/schema';
import { computed, ref, watch } from 'vue';

type MetadataEntry = {
    label: string;
    value: string;
};

const props = withDefaults(defineProps<{
    open: boolean;
    itemName: string;
    hasMetadata: boolean;
    metadataEntries: MetadataEntry[];
    fileId?: string | null;
    fileName?: string;
    hasFile?: boolean;
    currentVersionId?: string | null;
    disableVersionActions?: boolean;
    uploadedAt?: string | null;
    modifiedAt?: string | null;
}>(), {
    disableVersionActions: false,
    metadataEntries: () => [],
    uploadedAt: null,
    modifiedAt: null,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const versions = ref<FileVersion[]>([]);
const versionsLoading = ref(false);
const versionsError = ref<string | null>(null);

const hasFileAttached = computed(() => props.hasFile !== false);
const showVersionHistory = computed(() => Boolean(props.fileId));
const versionTargetName = computed(() => props.fileName ?? props.itemName);
const versionActionsEnabled = computed(() => !props.disableVersionActions);
const versionItemClasses = computed(() => versionActionsEnabled.value
    ? 'rounded-xl border border-border/70 bg-background/90 p-3 shadow-sm ring-1 ring-black/0 transition hover:shadow-md dark:bg-muted/60'
    : 'p-0 border-0 bg-transparent shadow-none ring-0');

const dateMetadataEntries = computed<MetadataEntry[]>(() => {
    const entries: MetadataEntry[] = [];

    if (props.uploadedAt) {
        entries.push({ label: 'Date Uploaded', value: formatDateOnly(props.uploadedAt) });
    }

    if (props.modifiedAt) {
        entries.push({ label: 'Last Updated', value: formatDateOnly(props.modifiedAt) });
    }

    return entries;
});

const combinedMetadataEntries = computed<MetadataEntry[]>(() => {
    const entries = [...props.metadataEntries];
    const dates = dateMetadataEntries.value;

    if (dates.length === 0) {
        return entries;
    }

    const insertAfterIndex = entries.findIndex(entry => entry.label.trim().toLowerCase() === 'date released');

    if (insertAfterIndex >= 0) {
        entries.splice(insertAfterIndex + 1, 0, ...dates);
    } else {
        entries.push(...dates);
    }

    return entries;
});

const hasAnyMetadata = computed(() => combinedMetadataEntries.value.length > 0);

const { error: showError, success: showSuccess } = useToast();

const formatMetadataLabel = (label: string): string => {
    if (label.trim().toLowerCase() === 'record id') {
        return label;
    }

    return label.replace(/\b\w+/g, word => word.charAt(0).toUpperCase() + word.slice(1));
};

const formatDateTime = (value?: string | null): string => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return parsed.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatDateOnly = (value?: string | null): string => {
    if (!value) {
        return '-';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const handleOpenChange = (value: boolean): void => {
    emit('update:open', value);
};

const fetchVersions = async (): Promise<void> => {
    if (!props.open || !props.fileId) {
        return;
    }

    if (!hasFileAttached.value) {
        versions.value = [];
        versionsError.value = null;
        versionsLoading.value = false;
        return;
    }

    versionsLoading.value = true;
    versionsError.value = null;

    try {
        const response = await apiClient.get(versionsRoutes.index({ file: props.fileId }).url);
        versions.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch (error: any) {
        versionsError.value = error.response?.data?.message || 'Failed to load versions.';
        showError('Unable to load versions', versionsError.value);
    } finally {
        versionsLoading.value = false;
    }
};

const handleDownload = (version: FileVersion): void => {
    if (!props.fileId) return;

    window.open(versionsRoutes.download({ file: props.fileId, version: version.id }).url, '_blank');
};

const handleRestore = async (version: FileVersion): Promise<void> => {
    if (!props.fileId) {
        return;
    }

    try {
        await apiClient.post(versionsRoutes.restore({ file: props.fileId, version: version.id }).url);
        showSuccess('Version restored', 'This version is now the active copy.');
        await fetchVersions();
        router.reload({ preserveScroll: true, preserveState: true });
    } catch (error: any) {
        const message = error.response?.data?.message || 'Failed to restore this version.';
        versionsError.value = message;
        showError('Restore failed', message);
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            fetchVersions();
        }
    },
);

watch(
    () => [props.fileId, props.hasFile],
    () => {
        if (props.open) {
            fetchVersions();
        }
    },
);
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent side="right" class="flex h-full flex-col sm:max-w-md">
            <SheetHeader class="border-b px-4 pb-4">
                <SheetTitle class="text-2xl font-semibold">Info</SheetTitle>
                <SheetDescription class="text-base">
                    {{ itemName }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto py-4 space-y-6">
                <section>
                    <div v-if="hasAnyMetadata" class="grid grid-cols-[120px_1fr] gap-x-6 gap-y-3 px-4">
                        <template v-for="entry in combinedMetadataEntries" :key="entry.label">
                            <div class="text-sm text-muted-foreground">
                                {{ formatMetadataLabel(entry.label) }}
                            </div>
                            <div class="text-sm wrap-break-words">
                                {{ entry.value }}
                            </div>
                        </template>
                    </div>

                    <p v-else class="px-4 text-sm text-muted-foreground">
                        No metadata available.
                    </p>
                </section>

                <section v-if="showVersionHistory" class="border-t pt-4">
                    <div class="px-4">
                        <h3 class="text font-semibold">Version History</h3>
                    </div>

                    <div class="px-4 pb-2">
                        <div v-if="!hasFileAttached" class="text-sm text-muted-foreground">
                            No file is attached to this record yet.
                        </div>

                        <div v-else-if="versionsLoading" class="flex items-center justify-center py-6">
                            <Spinner class="h-5 w-5" />
                        </div>

                        <div v-else-if="versionsError" class="text-sm text-destructive">
                            {{ versionsError }}
                        </div>

                        <div v-else-if="versions.length === 0" class="text-sm text-muted-foreground">
                            No versions have been uploaded for this record yet.
                        </div>

                        <ul v-else class="mt-3 flex flex-col gap-3">
                            <li v-for="version in versions" :key="version.id" :class="versionItemClasses">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                            <span
                                                class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                {{ version.version_label ?? `v${version.version_number}` }}
                                            </span>
                                            <span>{{ formatDateTime(version.created_at) }}</span>
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
                                    <div v-if="versionActionsEnabled" class="flex justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button type="button"
                                                    class="h-8 w-8 inline-flex items-center justify-center rounded-md border border-transparent text-muted-foreground transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                                    <MoreVertical class="h-4 w-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="w-44">
                                                <DropdownMenuItem :disabled="version.id === props.currentVersionId"
                                                    class="cursor-pointer" @click="handleRestore(version)">
                                                    <RotateCcw class="mr-2 h-4 w-4" />
                                                    <span>Restore</span>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem class="cursor-pointer"
                                                    @click="handleDownload(version)">
                                                    <Download class="mr-2 h-4 w-4" />
                                                    <span>Download</span>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </SheetContent>
    </Sheet>
</template>
