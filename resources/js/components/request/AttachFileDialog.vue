<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Badge } from '@/components/ui/badge';
import { apiClient } from '@/lib/axios';
import { usePage } from '@inertiajs/vue3';
import requestsApi from '@/routes/api/requests';
import { useToast } from '@/composables/useToast';
import { Search, Folder, FolderOpen, FileText, ChevronLeft, Home, Eye, Info, ScanText, Loader2 } from 'lucide-vue-next';
import type { File, FolderSchema } from '@/types/schema';
import { formatFileSize } from '@/utils/format';
import { highlight } from '@/utils/text';
import ItemMetadataSheet from '@/components/records/ItemMetadataSheet.vue';
import { openFile } from '@/utils/itemNavigation';
import { retentionStatusBadgeClass, retentionStatusLabel } from '@/utils/retentionStatus';

interface SearchableFile extends File {
    snippet?: string | null;
    ocr_status?: string | null;
    ocr_language?: string | null;
}

const props = defineProps<{
    requestId: number;
    blockedFileIds?: string[];
}>();

const emit = defineEmits<{
    attached: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const { success, error } = useToast();

const searchQuery = ref('');
const searching = ref(false);
const attaching = ref(false);
const loading = ref(false);

const folders = ref<FolderSchema[]>([]);
const files = ref<SearchableFile[]>([]);
const currentPath = ref<FolderSchema[]>([]);
const selectedFileIds = ref<Set<string>>(new Set());
const blockedIds = computed<Set<string>>(() => new Set(props.blockedFileIds ?? []));

// Metadata / preview state
const showMetadataSheet = ref(false);
const metadataItemName = ref('');
const selectedMetadataEntries = ref<{ label: string; value: string }[]>([]);
const hasMetadataEntries = computed(() => selectedMetadataEntries.value.length > 0);
const metadataFileId = ref<string | null>(null);
const metadataHasFile = ref(false);
const metadataCurrentVersionId = ref<string | null>(null);
const page = usePage();

const searchResults = ref<SearchableFile[]>([]);
const hasMoreSearchResults = ref(false);
const isSearchMode = computed(() => searchQuery.value.trim().length >= 2);
const MIN_SEARCH_LENGTH = 2;

const getDepartmentName = (id: string | number | undefined | null): string => {
    const props = (page.props as any) || {};
    const deps = props.departments ?? props.departments_list ?? null;

    if (id === undefined || id === null) return '—';

    if (Array.isArray(deps)) {
        const found = deps.find((d: any) => String(d.id) === String(id));
        return found?.name ?? String(id);
    }

    if (deps && typeof deps === 'object') {
        return deps[id] ?? deps[String(id)] ?? String(id);
    }

    return String(id);
};

const displayedFiles = computed(() => isSearchMode.value ? searchResults.value : files.value);
const selectedCount = computed(() => selectedFileIds.value.size);
const searchQueryTrimmed = computed(() => searchQuery.value.trim());
const disposedAcknowledged = ref(false);

const selectedFile = computed<SearchableFile | null>(() => {
    const selectedId = Array.from(selectedFileIds.value)[0];

    if (!selectedId) {
        return null;
    }

    return displayedFiles.value.find((file) => file.id === selectedId) ?? null;
});

const selectedFileIsDisposed = computed(() => selectedFile.value?.retention_status === 'disposed');

const isOcrMatch = (file: SearchableFile): boolean => {
    if (!file.snippet || !searchQueryTrimmed.value) return false;
    const lowerSnippet = (file.snippet || '').toLowerCase();
    const lowerQuery = searchQueryTrimmed.value.toLowerCase();
    const lowerFileName = (file.file_name || '').toLowerCase();

    // Check if the match is in snippet but not in filename
    return lowerSnippet.includes(lowerQuery) && !lowerFileName.includes(lowerQuery);
};

const buildFolderParams = (folderId: string | null) => ({ folder_id: folderId ?? 'null', per_page: 'all' });

const isScanRecord = (file: File): boolean => file.record_id?.startsWith('SCAN-') ?? false;
const hasParentFolder = (file: File): boolean => Boolean(file.folder_id ?? (file as { folder?: { id?: string | null } }).folder?.id);
const filterLinkableFiles = (list: File[]): File[] =>
    list.filter(file => !isScanRecord(file) && Boolean(file.is_record) && hasParentFolder(file));

const loadRecords = async (folderId: string | null = null) => {
    loading.value = true;
    try {
        const params = buildFolderParams(folderId);

        const [foldersResponse, filesResponse] = await Promise.all([
            apiClient.get<{ data?: FolderSchema[] } | FolderSchema[]>('/api/folders', { params }),
            apiClient.get<{ data?: File[] } | File[]>('/api/files', { params }),
        ]);

        folders.value = (foldersResponse.data as any).data || foldersResponse.data || [];
        const fetchedFiles = (filesResponse.data as any).data || filesResponse.data || [];
        files.value = filterLinkableFiles(fetchedFiles);
    } catch {
        error('Failed to load folder contents');
        folders.value = [];
        files.value = [];
    } finally {
        loading.value = false;
    }
};

const performSearch = async () => {
    const trimmed = searchQueryTrimmed.value;

    if (!trimmed || trimmed.length < MIN_SEARCH_LENGTH) {
        searchResults.value = [];
        hasMoreSearchResults.value = false;
        return;
    }

    searching.value = true;
    try {
        const response = await apiClient.get<{ data: SearchableFile[]; has_more: boolean }>(
            `/api/requests/${props.requestId}/searchable-files`,
            { params: { search: trimmed, limit: 20 } }
        );

        const fetchedResults = response.data.data || [];
        searchResults.value = filterLinkableFiles(fetchedResults) as SearchableFile[];
        hasMoreSearchResults.value = response.data.has_more || false;
    } catch {
        error('Failed to search files');
        searchResults.value = [];
        hasMoreSearchResults.value = false;
    } finally {
        searching.value = false;
    }
};

const openFolder = async (folder: FolderSchema) => {
    currentPath.value.push(folder);
    await loadRecords(folder.id);
};

const goBack = async () => {
    currentPath.value.pop();
    const parent = currentPath.value[currentPath.value.length - 1];
    await loadRecords(parent?.id || null);
};

const goToRoot = async () => {
    currentPath.value = [];
    await loadRecords(null);
};

const goToBreadcrumb = async (index: number) => {
    currentPath.value = currentPath.value.slice(0, index + 1);
    const folder = currentPath.value[index];
    await loadRecords(folder.id);
};

const isFileDisabled = (fileId: string): boolean => blockedIds.value.has(fileId);

const toggleFileSelection = (fileId: string) => {
    if (isFileDisabled(fileId)) {
        return;
    }

    if (selectedFileIds.value.has(fileId)) {
        selectedFileIds.value.delete(fileId);
    } else {
        selectedFileIds.value.clear();
        selectedFileIds.value.add(fileId);
    }

    selectedFileIds.value = new Set(selectedFileIds.value);
    disposedAcknowledged.value = false;
};

const handleFileClick = (file: SearchableFile) => {
    if (isFileDisabled(file.id)) {
        return;
    }

    toggleFileSelection(file.id);
};

const previewFile = (file: SearchableFile) => {
    if (!file.file_path) {
        error('No file attached', 'This record has no attached file to preview.');
        return;
    }

    try {
        window.open(`/file/${file.id}/view`, '_blank', 'noopener');
    } catch {
        openFile(file.id, file, error);
    }
};

const openMetadataSheet = async (file: SearchableFile) => {
    metadataItemName.value = file.file_name || 'Record';
    showMetadataSheet.value = true;
    selectedMetadataEntries.value = [];

    try {
        const { data } = await apiClient.get<SearchableFile>(`/api/files/${file.id}`);
        metadataFileId.value = data.id ?? file.id;
        metadataHasFile.value = Boolean(data.file_path ?? file.file_path);
        metadataCurrentVersionId.value = (data as any).current_version_id ?? null;

        const departmentName = (data as any).department?.name ?? getDepartmentName((data as any).department_id ?? data.department_id);

        const entries: { label: string; value: string }[] = [
            { label: 'File name', value: data.file_name ?? '—' },
            { label: 'Record ID', value: data.record_id ?? '—' },
            { label: 'Author', value: data.author ?? '—' },
            { label: 'Division', value: departmentName ?? '—' },
            { label: 'Physical location', value: data.physical_location ?? '—' },
            { label: 'Folder', value: (data as any).folder?.name ?? (data.folder_id ?? '—') },
            { label: 'File size', value: data.file_size ? formatFileSize(data.file_size) : '—' },
        ];

        // Add OCR information if available
        if (data.ocr_status) {
            entries.push({
                label: 'OCR Status',
                value: data.ocr_status === 'completed' ? 'Completed' :
                       data.ocr_status === 'processing' ? 'Processing...' :
                       data.ocr_status === 'pending' ? 'Pending' :
                       data.ocr_status === 'failed' ? 'Failed' : data.ocr_status
            });
        }

        if (data.ocr_language && data.ocr_status === 'completed') {
            entries.push({
                label: 'OCR Language',
                value: data.ocr_language
            });
        }

        selectedMetadataEntries.value = entries;
    } catch {
        error('Unable to load file details', 'Showing limited metadata.');
        selectedMetadataEntries.value = [
            { label: 'File name', value: file.file_name },
            { label: 'Record ID', value: file.record_id ?? '—' },
        ];
    }
};

const attachSelectedFiles = async () => {
    if (selectedFileIds.value.size === 0) return;

    attaching.value = true;
    try {
        const fileIds = Array.from(selectedFileIds.value);

        await apiClient.post(requestsApi.attachFile.url(props.requestId), {
            file_ids: fileIds,
            disposed_acknowledged: selectedFileIsDisposed.value ? disposedAcknowledged.value : false,
        });

        success(`${fileIds.length} file${fileIds.length > 1 ? 's' : ''} linked successfully`);
        emit('attached');
        open.value = false;
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string } } };
        error(err.response?.data?.message || 'Failed to link files');
    } finally {
        attaching.value = false;
    }
};

watch(open, async (val) => {
    if (val) {
        searchQuery.value = '';
        currentPath.value = [];
        selectedFileIds.value.clear();
        disposedAcknowledged.value = false;
        await loadRecords(null);
    }
});

watch(() => props.blockedFileIds, () => {
    selectedFileIds.value = new Set(
        Array.from(selectedFileIds.value).filter(id => !isFileDisabled(id)),
    );
});

let debounceTimer: ReturnType<typeof setTimeout>;
const onSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(performSearch, 300);
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-3xl max-h-[85vh] flex flex-col">
            <DialogHeader class="shrink-0">
                <DialogTitle>Link Files from Records</DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-4 flex-1 min-h-0">
                <!-- Search Bar -->
                <div class="relative shrink-0">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by file name or document content (OCR)..."
                        class="pl-9"
                        @input="onSearchInput"
                    />
                </div>

                <!-- Search Mode Header -->
                <div v-if="isSearchMode" class="flex items-center justify-between shrink-0 pb-2 border-b">
                    <div class="flex items-center gap-2">
                        <Search class="h-4 w-4 text-muted-foreground" />
                        <span class="text-sm font-medium text-muted-foreground">
                            Searching in: All Records
                        </span>
                    </div>
                    <Badge v-if="!searching && displayedFiles.length > 0" variant="secondary">
                        {{ displayedFiles.length }} result{{ displayedFiles.length !== 1 ? 's' : '' }}
                    </Badge>
                </div>

                <!-- Navigation (Browse Mode Only) -->
                <div v-if="!isSearchMode" class="flex items-center gap-2 shrink-0">
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 " :disabled="currentPath.length === 0"
                        @click="goBack">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>

                    <Breadcrumb class="flex-1">
                        <BreadcrumbList>
                            <BreadcrumbItem>
                                <BreadcrumbLink as="button" @click="goToRoot" class="flex items-center gap-1">
                                    <Home class="h-3.5 w-3.5" />
                                    All Records
                                </BreadcrumbLink>
                            </BreadcrumbItem>

                            <template v-for="(folder, index) in currentPath" :key="folder.id">
                                <BreadcrumbSeparator />
                                <BreadcrumbItem>
                                    <BreadcrumbLink v-if="index < currentPath.length - 1" as="button"
                                        @click="goToBreadcrumb(index)">
                                        {{ folder.name }}
                                    </BreadcrumbLink>
                                    <BreadcrumbPage v-else>
                                        {{ folder.name }}
                                    </BreadcrumbPage>
                                </BreadcrumbItem>
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                <p v-if="!isSearchMode && currentPath.length === 0" class="text-xs text-muted-foreground shrink-0">
                    Only organized records inside folders can be linked to requests.
                </p>

                <!-- Search Hint -->
                <div v-if="searchQueryTrimmed.length > 0 && searchQueryTrimmed.length < MIN_SEARCH_LENGTH"
                    class="px-4 py-3 bg-muted/50 rounded-md shrink-0">
                    <p class="text-sm text-muted-foreground">
                        Type at least {{ MIN_SEARCH_LENGTH }} characters to search
                    </p>
                </div>

                <!-- Selection Info -->
                <div v-if="selectedCount > 0"
                    class="flex items-center justify-between px-1 py-2 bg-muted/50 rounded-md shrink-0 cursor-pointer">
                    <span class="text-sm text-muted-foreground">
                        1 file selected
                    </span>
                    <Button variant="ghost" size="sm" class="h-7 text-xs " @click="selectedFileIds.clear()">
                        Clear
                    </Button>
                </div>

                <div v-if="selectedFileIsDisposed" class="space-y-3 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/40">
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Disposed record selected</p>
                        <p class="text-xs text-amber-800 dark:text-amber-300">
                            The physical file has been disposed, but an electronic copy is still available in the system. Continue only if the requester is asking for the electronic copy.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="disposed_acknowledged" v-model="disposedAcknowledged" />
                        <label for="disposed_acknowledged" class="text-xs text-amber-900 dark:text-amber-200">
                            I acknowledge this request references a disposed record.
                        </label>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-1 min-h-0 overflow-y-auto rounded-md border">
                    <!-- Loading State -->
                    <div v-if="loading || searching" class="flex flex-col items-center justify-center py-12 gap-3">
                        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">
                            {{ searching ? 'Searching files...' : 'Loading...' }}
                        </p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="displayedFiles.length === 0 && (isSearchMode || folders.length === 0)"
                        class="flex flex-col items-center justify-center py-12 c">
                        <FileText class="h-10 w-10 text-muted-foreground/40 mb-3" />
                        <p class="text-sm font-medium text-muted-foreground mb-1">
                            {{ isSearchMode ? 'No files found' : 'This folder is empty' }}
                        </p>
                        <p v-if="isSearchMode" class="text-xs text-muted-foreground">
                            Try different keywords or browse folders
                        </p>
                    </div>

                    <div v-else class="divide-y">
                        <!-- Folders (Browse Mode Only) -->
                        <button v-if="!isSearchMode" v-for="folder in folders" :key="`folder-${folder.id}`"
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-muted/50 transition-colors text-left group cursor-pointer"
                            @click="openFolder(folder)">
                            <FolderOpen class="h-5 w-5 text-muted-foreground shrink-0 group-hover:text-primary" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate">{{ folder.name }}</div>
                                <div v-if="folder.files_count && folder.files_count > 0"
                                    class="text-xs text-muted-foreground">
                                    {{ folder.files_count }} file{{ folder.files_count !== 1 ? 's' : '' }}
                                </div>
                            </div>
                        </button>

                        <!-- Files -->
                        <div v-for="file in displayedFiles" :key="`file-${file.id}`"
                            :class="[
                                'w-full flex items-start gap-3 transition-colors',
                                isSearchMode ? 'px-4 py-4' : 'px-4 py-3',
                                selectedFileIds.has(file.id) ? 'bg-primary/10 border-l-2 border-primary' : 'hover:bg-muted/50',
                                isFileDisabled(file.id) ? 'opacity-60 cursor-not-allowed' : ''
                            ]">
                            <!-- File Icon -->
                            <div class="mt-0.5 cursor-pointer" @click.stop="() => handleFileClick(file)">
                                <FileText class="h-5 w-5 text-muted-foreground shrink-0" />
                            </div>

                            <!-- File Info -->
                            <div class="flex-1 min-w-0 cursor-pointer" @click.stop="() => handleFileClick(file)">
                                <!-- File Name with Highlight -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="text-base font-medium"
                                        v-html="highlight(file.file_name, searchQueryTrimmed)"
                                    ></span>
                                    <Badge variant="outline" :class="retentionStatusBadgeClass(file.retention_status)">
                                        {{ retentionStatusLabel(file.retention_status) }}
                                    </Badge>
                                    <Badge v-if="isSearchMode && isOcrMatch(file)" variant="secondary" class="gap-1 text-[10px] px-1.5 py-0">
                                        <ScanText class="h-2.5 w-2.5" />
                                        OCR Match
                                    </Badge>
                                </div>

                                <!-- Record ID -->
                                <div v-if="file.record_id" class="text-xs text-muted-foreground mt-0.5">
                                    {{ file.record_id }}
                                </div>

                                <!-- OCR Snippet (Search Mode Only) -->
                                <div
                                    v-if="isSearchMode && file.snippet"
                                    class="mt-2 text-sm text-muted-foreground leading-relaxed line-clamp-3"
                                    v-html="highlight(file.snippet, searchQueryTrimmed)"
                                ></div>

                                <!-- Already Linked Warning -->
                                <div v-if="isFileDisabled(file.id)" class="text-[11px] text-amber-700 mt-1">
                                    Already linked to this request
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1 ml-2 mt-0.5">
                                <button
                                    type="button"
                                    class="p-1.5 rounded-md text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-colors"
                                    @click.stop="() => previewFile(file)"
                                    :disabled="isFileDisabled(file.id)"
                                    title="Preview file"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="p-1.5 rounded-md text-muted-foreground hover:bg-muted/50 hover:text-foreground transition-colors"
                                    @click.stop="() => openMetadataSheet(file)"
                                    :disabled="isFileDisabled(file.id)"
                                    title="View details"
                                >
                                    <Info class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- "More Results" Indicator -->
                <div v-if="isSearchMode && hasMoreSearchResults && displayedFiles.length > 0"
                    class="px-4 py-2 bg-muted/30 rounded-md shrink-0 text-center">
                    <p class="text-xs text-muted-foreground">
                        More results available. Refine your search for better matches.
                    </p>
                </div>
            </div>

            <DialogFooter class="shrink-0">
                <Button variant="outline" @click="open = false">Cancel</Button>
                <Button :disabled="selectedCount === 0 || attaching || (selectedFileIsDisposed && !disposedAcknowledged)" @click="attachSelectedFiles">
                    {{ attaching ? 'Linking...' : 'Link File' }}
                </Button>
            </DialogFooter>
        </DialogContent>
        <ItemMetadataSheet v-model:open="showMetadataSheet" :item-name="metadataItemName" :has-metadata="hasMetadataEntries"
            :metadata-entries="selectedMetadataEntries" :file-name="metadataItemName"
            :uploaded-at="selectedMetadata?.created_at ?? null" :modified-at="selectedMetadata?.updated_at ?? null" />
    </Dialog>
</template>
