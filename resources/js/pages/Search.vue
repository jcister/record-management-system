<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, FileText, Folder as FolderIcon, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import FilterButton from '@/components/records/FilterButton.vue';
import SearchBar from '@/components/records/SearchBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useSearchFilters } from '@/composables/useSearchFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import { formatDate, formatFileSize, formatMimeTypeLabel } from '@/utils/format';
import { retentionStatusBadgeClass, retentionStatusLabel } from '@/utils/retentionStatus';
import { highlight } from '@/utils/text';
interface FolderSummary {
    id: string;
    name: string;
}

interface FrequentlyAccessedItem {
    id: string;
    type: 'file' | 'folder';
    name: string;
    viewer_url: string;
    folder?: { id: string; name: string } | null;
}

type SearchResult = FileResult | FolderResult;

interface BaseResult {
    id: string;
    type: 'file' | 'folder';
    name: string;
    created_at: string | null;
    url: string;
}

interface FileResult extends BaseResult {
    type: 'file';
    mime_type: string | null;
    file_size: number;
    folder?: FolderSummary | null;
    snippet?: string | null;
    // Filter-related fields
    date_created?: string | null;
    date_released?: string | null;
    author?: string | null;
    metadata_tag_ids?: number[];
    physical_location_tag_ids?: number[];
    retention_duration?: 'one_year' | 'ten_years' | 'perpetual' | null;
    retention_status?: 'active' | 'ready_for_disposal' | 'disposed' | 'archived' | null;
    is_record?: boolean;
    folder_id?: string | null;
    root_folder_id?: string | null;
}

interface FolderResult extends BaseResult {
    type: 'folder';
    parent?: FolderSummary | null;
}

interface PaginatedResults {
    data: SearchResult[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

type Props = {
    search: string;
    results: PaginatedResults;
};

const props = defineProps<Props>();
const page = usePage();

const firstName = computed(() => {
    const name = (page.props.auth as any)?.user?.name ?? '';
    return name.split(' ')[0] || 'there';
});

const CARDS_PER_PAGE = 4;
const searchPanel = computed(() => (page.props.searchPanel as any) ?? { frequently_accessed: [] });
const frequentlyAccessed = computed<FrequentlyAccessedItem[]>(() => searchPanel.value.frequently_accessed ?? []);
const carouselPage = ref(0);
const totalCarouselPages = computed(() => Math.ceil(frequentlyAccessed.value.length / CARDS_PER_PAGE));
const paginatedItems = computed(() => {
    const start = carouselPage.value * CARDS_PER_PAGE;
    return frequentlyAccessed.value.slice(start, start + CARDS_PER_PAGE);
});

const carouselPrev = () => {
    if (carouselPage.value > 0) {
        carouselPage.value--;
    }
};

const carouselNext = () => {
    if (carouselPage.value < totalCarouselPages.value - 1) {
        carouselPage.value++;
    }
};

const isSearchActive = computed(() => Boolean(props.search) || hasActiveFilters.value);

const searchQuery = ref(props.search ?? '');
const isLoading = ref(false);
const canOrganizeContent = computed(() => page.props.auth?.can?.organizeContent ?? false);

const { filters, filterOptions, applyAllFilters, hasActiveFilters, activeFilterCount, fetchFilterOptions, clearAllFilters } = useSearchFilters();

// Fetch filter options on mount
onMounted(() => {
    fetchFilterOptions();
});

// Apply filters to search results
const filteredResults = computed(() => {
    if (!props.results?.data || props.results.data.length === 0) {
        return [];
    }

    return applyAllFilters(props.results.data as any);
});

const filteredTotal = computed(() => filteredResults.value.length);
const showingFilteredResults = computed(() => hasActiveFilters.value && filteredTotal.value !== props.results.total);

// Active filter chips for display
const activeFilterChips = computed(() => {
    const chips: Array<{ id: string; label: string; value: any; type: string }> = [];

    // Date Created
    if (filters.dateCreatedFrom || filters.dateCreatedTo) {
        const label = filters.dateCreatedFrom && filters.dateCreatedTo
            ? `Created: ${filters.dateCreatedFrom} - ${filters.dateCreatedTo}`
            : filters.dateCreatedFrom
            ? `Created after: ${filters.dateCreatedFrom}`
            : `Created before: ${filters.dateCreatedTo}`;
        chips.push({ id: 'dateCreated', label, value: null, type: 'dateCreated' });
    }

    // Date Released
    if (filters.dateReleasedFrom || filters.dateReleasedTo) {
        const label = filters.dateReleasedFrom && filters.dateReleasedTo
            ? `Released: ${filters.dateReleasedFrom} - ${filters.dateReleasedTo}`
            : filters.dateReleasedFrom
            ? `Released after: ${filters.dateReleasedFrom}`
            : `Released before: ${filters.dateReleasedTo}`;
        chips.push({ id: 'dateReleased', label, value: null, type: 'dateReleased' });
    }

    // Root Folders
    filters.rootFolderIds.forEach((id) => {
        const folder = filterOptions.value?.rootFolders.find((f) => f.id === id);
        if (folder) {
            chips.push({ id: `rootFolder-${id}`, label: folder.name, value: id, type: 'rootFolder' });
        }
    });

    // Authors
    filters.authors.forEach((author) => {
        chips.push({ id: `author-${author}`, label: `Author: ${author}`, value: author, type: 'author' });
    });

    // Metadata Tags
    filters.metadataTagIds.forEach((id) => {
        const tag = filterOptions.value?.metadataTags.find((t) => t.id === id);
        if (tag) {
            chips.push({ id: `metadataTag-${id}`, label: `Keyword: ${tag.name}`, value: id, type: 'metadataTag' });
        }
    });

    // Physical Location Tags
    filters.physicalLocationTagIds.forEach((id) => {
        const tag = filterOptions.value?.physicalLocationTags.find((t) => t.id === id);
        if (tag) {
            chips.push({ id: `physicalTag-${id}`, label: `Location: ${tag.name}`, value: id, type: 'physicalTag' });
        }
    });

    // Retention Durations
    filters.retentionDurations.forEach((duration) => {
        const option = filterOptions.value?.retentionDurations.find((d) => d.value === duration);
        if (option) {
            chips.push({ id: `retention-${duration}`, label: `Retention: ${option.label}`, value: duration, type: 'retention' });
        }
    });

    // Record Type
    if (filters.isRecord !== null) {
        const label = filters.isRecord ? 'Records Only' : 'Non-Records Only';
        chips.push({ id: 'isRecord', label, value: filters.isRecord, type: 'isRecord' });
    }

    return chips;
});

const removeFilter = (chip: { id: string; label: string; value: any; type: string }) => {
    switch (chip.type) {
        case 'dateCreated':
            filters.dateCreatedFrom = '';
            filters.dateCreatedTo = '';
            break;
        case 'dateReleased':
            filters.dateReleasedFrom = '';
            filters.dateReleasedTo = '';
            break;
        case 'rootFolder':
            filters.rootFolderIds = filters.rootFolderIds.filter((id) => id !== chip.value);
            break;
        case 'author':
            filters.authors = filters.authors.filter((a) => a !== chip.value);
            break;
        case 'metadataTag':
            filters.metadataTagIds = filters.metadataTagIds.filter((id) => id !== chip.value);
            break;
        case 'physicalTag':
            filters.physicalLocationTagIds = filters.physicalLocationTagIds.filter((id) => id !== chip.value);
            break;
        case 'retention':
            filters.retentionDurations = filters.retentionDurations.filter((d) => d !== chip.value);
            break;
        case 'isRecord':
            filters.isRecord = null;
            break;
    }
};

watch(
    () => props.search,
    (val) => {
        searchQuery.value = val ?? '';
    },
);

watch(
    () => props.results,
    () => {
        isLoading.value = false;
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Search',
        href: '/search',
    },
];

const handleSearch = (query: string) => {
    isLoading.value = true;
    router.visit('/search', {
        method: 'get',
        data: { search: query },
        preserveScroll: true,
        preserveState: true,
    });
};


const visitLink = (url: string) => {
    router.visit(url, { preserveScroll: true, preserveState: true });
};

const openResult = (result: SearchResult, query: string) => {
    const appendedQuery = query
        ? `${result.url}${result.url.includes('?') ? '&' : '?'}search=${encodeURIComponent(query)}`
        : result.url;

    router.visit(appendedQuery, { preserveScroll: true, preserveState: true });
};

const openFrequentItem = (item: FrequentlyAccessedItem) => {
    if (!item.viewer_url) {
        return;
    }

    router.visit(item.viewer_url, { preserveScroll: true, preserveState: true });
};


</script>

<template>
    <Head title="Search" />

    <AppLayout >
        <div class="flex flex-col h-full">
            <!-- Scrollable content area -->
            <div class="flex-1 overflow-y-auto flex flex-col">

                <!-- STATE A: Hero + Frequently Accessed Carousel -->
                <template v-if="!isSearchActive">
                    <div class="flex-1 flex flex-col items-center justify-center p-8 pb-6">
                        <div class="w-full max-w-3xl">
                            <!-- Greeting -->
                            <div class="mb-10">
                                <h1 class="text-4xl font-bold tracking-tight">
                                    Hi there, <span class="text-primary">{{ firstName }}</span>
                                </h1>
                                <h2 class="text-4xl font-bold tracking-tight mt-1">What are you looking for?</h2>
                                <p class="mt-3 text-base text-muted-foreground">
                                    Search by keywords inside documents, or by filename, foldername, etc.
                                </p>
                            </div>

                            <!-- Frequently Accessed Carousel -->
                            <div v-if="frequentlyAccessed.length > 0">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                                        Frequently Accessed
                                    </p>
                                    <div v-if="totalCarouselPages > 1" class="flex items-center gap-2">
                                        <span class="text-xs text-muted-foreground">
                                            {{ carouselPage + 1 }} / {{ totalCarouselPages }}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="h-8 w-8 rounded-full"
                                            :disabled="carouselPage === 0"
                                            @click="carouselPrev"
                                        >
                                            <ArrowLeft class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            class="h-8 w-8 rounded-full"
                                            :disabled="carouselPage === totalCarouselPages - 1"
                                            @click="carouselNext"
                                        >
                                            <ArrowRight class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <button
                                        v-for="item in paginatedItems"
                                        :key="item.id"
                                        type="button"
                                        class="flex flex-col justify-between w-full h-40 rounded-2xl border p-4 text-left hover:bg-accent/40 transition-colors cursor-pointer"
                                        @click="openFrequentItem(item)"
                                    >
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <component :is="item.type === 'folder' ? FolderIcon : FileText" class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0 w-full">
                                            <p class="text-sm font-medium truncate">{{ item.name }}</p>
                                            <p v-if="item.folder" class="text-xs text-muted-foreground truncate mt-0.5">
                                                {{ item.folder.name }}
                                            </p>
                                            <Badge variant="secondary" class="mt-2 text-xs">
                                                {{ item.type === 'folder' ? 'Folder' : 'File' }}
                                            </Badge>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- <p v-else class="text-sm text-muted-foreground">
                                No recently accessed files yet.
                            </p> -->
                        </div>
                    </div>
                </template>

                <!-- STATE B: Search Results -->
                <template v-else>
                    <div class="p-6 space-y-4">
                        <!-- Results header -->
                        <div>
                            <h2 class="text-xl font-semibold">
                                <template v-if="props.search">
                                    Results for <span class="text-primary">"{{ props.search }}"</span>
                                </template>
                                <template v-else>
                                    Filtered results
                                </template>
                            </h2>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                <template v-if="showingFilteredResults">
                                    {{ filteredTotal }} of {{ props.results.total }} result{{ props.results.total === 1 ? '' : 's' }}
                                    ({{ activeFilterCount }} filter{{ activeFilterCount > 1 ? 's' : '' }} applied)
                                </template>
                                <template v-else>
                                    {{ props.results.total || 0 }} result{{ props.results.total === 1 ? '' : 's' }}
                                </template>
                            </p>
                        </div>

                        <!-- Active filter chips -->
                        <div v-if="hasActiveFilters && activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-muted-foreground">Filters:</span>
                            <Badge
                                v-for="chip in activeFilterChips"
                                :key="chip.id"
                                variant="secondary"
                                class="gap-1.5 pl-3 pr-2 py-1.5 cursor-pointer hover:bg-secondary/80"
                            >
                                <span class="text-xs">{{ chip.label }}</span>
                                <button
                                    type="button"
                                    class="rounded-full hover:bg-background/50 p-0.5"
                                    @click="removeFilter(chip)"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </Badge>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                @click="clearAllFilters"
                            >
                                Clear all
                            </Button>
                        </div>

                        <!-- Skeleton loading state -->
                        <div v-if="isLoading" class="space-y-3">
                            <div v-for="i in 4" :key="i" class="flex flex-col gap-3 rounded-2xl border p-5">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <Skeleton class="h-9 w-9 shrink-0 rounded-xl" />
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <Skeleton class="h-4 w-3/4" />
                                            <Skeleton class="h-3 w-1/2" />
                                        </div>
                                    </div>
                                    <Skeleton class="h-8 w-16 rounded-xl" />
                                </div>
                                <Skeleton class="h-3 w-full" />
                                <Skeleton class="h-3 w-5/6" />
                                <div class="flex gap-2">
                                    <Skeleton class="h-5 w-12 rounded-full" />
                                    <Skeleton class="h-5 w-16 rounded-full" />
                                    <Skeleton class="h-5 w-24 rounded-full" />
                                </div>
                            </div>
                        </div>

                        <!-- Result items -->
                        <div v-else-if="filteredResults.length" class="space-y-3">
                            <div
                                v-for="result in filteredResults"
                                :key="`${result.type}-${result.id}`"
                                class="flex flex-col gap-2 rounded-2xl border p-5 hover:bg-accent/40 transition-colors"
                            >
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <component :is="result.type === 'folder' ? FolderIcon : FileText" class="h-4 w-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium leading-snug break-all" :title="result.name"
                                                v-html="highlight(result.name, props.search)"></p>
                                            <p class="text-xs text-muted-foreground mt-0.5">
                                                <span>{{ formatDate(result.created_at ?? '') }}</span>
                                                <template v-if="result.type === 'file'">
                                                    <span v-if="formatMimeTypeLabel(result.mime_type)"> • {{ formatMimeTypeLabel(result.mime_type) }}</span>
                                                    <span v-if="formatFileSize(result.file_size)"> • {{ formatFileSize(result.file_size) }}</span>
                                                </template>
                                                <template v-else>
                                                    <span> • Folder</span>
                                                </template>
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        class="cursor-pointer shrink-0 self-start rounded-xl"
                                        variant="outline"
                                        size="sm"
                                        @click="openResult(result, props.search)"
                                    >
                                        Open
                                    </Button>
                                </div>
                                <p v-if="result.type === 'file' && result.snippet"
                                    class="text-sm leading-relaxed text-muted-foreground"
                                    v-html="highlight(result.snippet, props.search)"></p>
                                <div v-else-if="result.type === 'file'" class="text-xs text-muted-foreground">
                                    No preview available.
                                </div>
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <Badge variant="secondary" class="max-w-full break-all">
                                        {{ result.type === 'file' ? (formatMimeTypeLabel(result.mime_type) || 'FILE') : 'Folder' }}
                                    </Badge>
                                    <Badge
                                        v-if="result.type === 'file'"
                                        variant="outline"
                                        :class="retentionStatusBadgeClass(result.retention_status)"
                                    >
                                        {{ retentionStatusLabel(result.retention_status) }}
                                    </Badge>
                                    <Badge v-if="result.type === 'file' && result.folder" variant="outline" class="max-w-full gap-1">
                                        <FolderIcon class="h-3 w-3" />
                                        <button type="button" class="max-w-56 cursor-pointer truncate text-left sm:max-w-72"
                                            @click="visitLink(`/records/${result.folder.id}`)">
                                            {{ result.folder.name }}
                                        </button>
                                    </Badge>
                                    <Badge v-else-if="result.type === 'folder' && result.parent" variant="outline" class="max-w-full gap-1">
                                        <FolderIcon class="h-3 w-3" />
                                        <button type="button" class="max-w-56 cursor-pointer truncate text-left sm:max-w-72"
                                            @click="visitLink(`/records/${result.parent.id}`)">
                                            {{ result.parent.name }}
                                        </button>
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!isLoading" class="py-16 text-center text-muted-foreground">
                            <template v-if="props.search">
                                No files or folders match "{{ props.search }}".
                            </template>
                            <template v-else>
                                No files match the active filters.
                            </template>
                        </div>

                        <!-- Pagination -->
                        <div v-if="props.results.links?.length" class="flex flex-wrap items-center gap-2 pt-2">
                            <Button
                                v-for="link in props.results.links"
                                :key="link.label"
                                :disabled="!link.url"
                                variant="outline"
                                size="sm"
                                :class="{
                                    'bg-black text-white hover:bg-black hover:text-white': link.active,
                                    'dark:bg-white dark:text-black dark:hover:bg-white dark:hover:text-black': link.active
                                }"
                                @click="link.url && visitLink(link.url)"
                            >
                                <span v-html="link.label"></span>
                            </Button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Sticky bottom bar: Filter + Search -->
            <div class="shrink-0 border-border/60 bg-background/80 backdrop-blur-sm px-6 py-4">
                <div class="mx-auto max-w-3xl flex items-center gap-3">
                    <SearchBar :initial-query="searchQuery" class="flex-1" @search="handleSearch" />
                    <FilterButton />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
