<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, FileText, Folder as FolderIcon, Loader2, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMimeTypeLabel, formatDate, formatDateTime } from '@/utils/format';
import { highlight } from '@/utils/text';

interface FolderSummary {
    id: string;
    name: string;
    viewer_url: string;
}

interface Suggestion {
    id: string;
    type: 'file' | 'folder';
    file_name: string;
    mime_type: string | null;
    created_at: string | null;
    viewer_url: string;
    snippet?: string | null;
    folder?: FolderSummary | null;
}

interface FrequentlyAccessedItem {
    id: string;
    type: 'file' | 'folder';
    name: string;
    viewer_url: string;
    folder?: FolderSummary | null;
    views?: number;
}

const MIN_QUERY_LENGTH = 2;
const SUGGESTION_LIMIT = 5;

interface Props {
    initialQuery?: string;
}

const props = withDefaults(defineProps<Props>(), {
    initialQuery: '',
});

const searchQuery = ref(props.initialQuery);
const page = usePage();

const inputRef = ref<any>(null);
const wrapperRef = ref<HTMLElement | null>(null);
const showDropdown = ref(false);
const isInputFocused = ref(false);
const isLoadingSuggestions = ref(false);
const suggestions = ref<Suggestion[]>([]);
const hasMoreSuggestions = ref(false);
const suggestionError = ref('');
const trimmedQuery = computed(() => searchQuery.value.trim());
const isQueryTooShort = computed(() => trimmedQuery.value.length > 0 && trimmedQuery.value.length < MIN_QUERY_LENGTH);
const isInPanelMode = computed(() => trimmedQuery.value.length === 0);
const fetchAbortController = ref<AbortController | null>(null);
let debounceHandle: ReturnType<typeof setTimeout> | null = null;
let suppressNextFocus = false;

const searchPanel = computed(() => (page.props.searchPanel as { frequently_accessed?: FrequentlyAccessedItem[]; last_recorded_at?: string | null }) ?? { frequently_accessed: [] });
const frequentlyAccessed = computed<FrequentlyAccessedItem[]>(() => searchPanel.value.frequently_accessed ?? []);
const hasPanelContent = computed(() => frequentlyAccessed.value.length > 0);
const panelLastRecordedAt = computed(() => searchPanel.value.last_recorded_at ?? null);
const panelLastActivityLabel = computed(() => {
    if (!panelLastRecordedAt.value) {
        return '';
    }

    return formatDateTime(panelLastRecordedAt.value);
});

const emit = defineEmits<{
    search: [query: string];
}>();

const resetSuggestions = () => {
    suggestions.value = [];
    hasMoreSuggestions.value = false;
    suggestionError.value = '';
};

const closeDropdown = () => {
    showDropdown.value = false;
    isInputFocused.value = false;
};


const fetchSuggestions = async (term: string) => {
    fetchAbortController.value?.abort();
    const controller = new AbortController();
    fetchAbortController.value = controller;

    isLoadingSuggestions.value = true;
    suggestionError.value = '';

    const params = new URLSearchParams({
        search: term,
        limit: String(SUGGESTION_LIMIT),
    });

    try {
        const response = await fetch(`/search/suggestions?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'Cache-Control': 'no-cache',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error('Unable to fetch suggestions.');
        }

        const payload = (await response.json()) as { data: Suggestion[]; has_more: boolean };
        suggestions.value = payload.data ?? [];
        hasMoreSuggestions.value = Boolean(payload.has_more);
    } catch (error) {
        if ((error as DOMException)?.name === 'AbortError') {
            return;
        }

        console.error('Failed to fetch suggestions:', error);
        suggestionError.value = 'Unable to load suggestions right now.';
        suggestions.value = [];
        hasMoreSuggestions.value = false;
    } finally {
        isLoadingSuggestions.value = false;
    }
};

watch(trimmedQuery, (value) => {
    if (debounceHandle) {
        clearTimeout(debounceHandle);
    }

    const hasValue = value.length > 0;
    showDropdown.value = isInputFocused.value;

    if (!hasValue) {
        fetchAbortController.value?.abort();
        resetSuggestions();
        return;
    }

    if (isQueryTooShort.value) {
        fetchAbortController.value?.abort();
        resetSuggestions();
        return;
    }

    debounceHandle = setTimeout(() => {
        fetchSuggestions(value);
    }, 250);
});

const focusInput = async () => {
    suppressNextFocus = true;
    await nextTick();
    const el = inputRef.value as any;
    if (!el) return;

    const root = el.$el ?? el;
    try {
        const input = root.querySelector ? root.querySelector('input') : null;
        if (input && typeof input.focus === 'function') {
            input.focus();
            return;
        }
    } catch (e) {
        // ignore
    }

    if (typeof (root as any).focus === 'function') {
        (root as any).focus();
    }
};

const submitSearch = () => {
    emit('search', trimmedQuery.value);
    closeDropdown();
};

const handleSeeAllResults = submitSearch;
const handleSearch = submitSearch;

const handleSearchFocus = () => {
    if (suppressNextFocus) {
        suppressNextFocus = false;
        return;
    }
    isInputFocused.value = true;
    showDropdown.value = true;
    focusInput();
};

const openSuggestion = (item: Suggestion) => {
    closeDropdown();
    router.visit(item.viewer_url, { preserveScroll: true, preserveState: true });
};

const openParentFolder = (folder?: FolderSummary | null) => {
    if (!folder?.viewer_url) {
        return;
    }

    closeDropdown();
    router.visit(folder.viewer_url, { preserveScroll: true, preserveState: true });
};

const openFrequentItem = (item: FrequentlyAccessedItem) => {
    if (!item.viewer_url) {
        return;
    }

    closeDropdown();
    router.visit(item.viewer_url, { preserveScroll: true, preserveState: true });
};



const handleDocumentClick = (event: MouseEvent) => {
    if (!wrapperRef.value) {
        return;
    }

    const target = event.target as HTMLElement;

    // Close dropdown if clicking on/within filter button or filter popover
    const isFilterClick = target.closest('button')?.textContent?.includes('Filters') ||
                          target.closest('[role="dialog"]') ||
                          target.closest('[data-radix-popper-content-wrapper]');

    if (isFilterClick) {
        closeDropdown();
        return;
    }

    // Close dropdown if clicking outside the search wrapper
    if (!wrapperRef.value.contains(target)) {
        closeDropdown();
    }
};

watch(() => page.url, (url) => {
    closeDropdown();

    if (url === '/search') {
        focusInput();
    }
});

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);

    if (page.url === '/search') {
        focusInput();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    fetchAbortController.value?.abort();

    if (debounceHandle) {
        clearTimeout(debounceHandle);
    }
});
</script>

<template>
    <div ref="wrapperRef" class="relative w-full">
        <div class="flex items-center gap-3 rounded-2xl border  dark:border-white bg-background px-4 h-14">
            <Search :size="16" class="shrink-0 text-muted-foreground" />

            <Input
                ref="inputRef"
                v-model="searchQuery"
                type="text"
                placeholder="Search files and folders…"
                class="flex-1 border-none bg-transparent shadow-none focus-visible:ring-0 focus-visible:ring-offset-0 px-0 h-full"
                @keydown.enter="submitSearch"
                @keydown.esc="closeDropdown"
                @focus="handleSearchFocus"
            />

            <button
                v-if="trimmedQuery.length > 0"
                type="button"
                title="Clear"
                aria-label="Clear search"
                class="shrink-0 flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                @mousedown.prevent
                @click="searchQuery = ''"
            >
                <X class="h-3.5 w-3.5" />
            </button>

            <button
                type="button"
                title="Search"
                aria-label="Search"
                class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full transition-colors"
                :class="trimmedQuery.length > 0
                    ? 'bg-black dark:bg-white text-white dark:text-black hover:opacity-80 cursor-pointer'
                    : 'bg-muted text-muted-foreground cursor-default'"
                @click="trimmedQuery.length > 0 && submitSearch()"
            >
                <ArrowUpRight class="h-4 w-4" />
            </button>
        </div>

        <div
            v-if="showDropdown"
            class="absolute left-0 right-0 z-50 bottom-full mb-2 rounded-2xl border border-border/60 bg-background shadow-xl"
        >
            <div class="max-h-96 overflow-auto py-2">
                <template v-if="isInPanelMode">
                    <div v-if="!hasPanelContent" class="px-4 py-6 text-sm text-muted-foreground space-y-2">
                        <p>Search files and folders… (type a name, keyword, or tag)</p>

                        <p v-if="panelLastActivityLabel" class="text-xs text-muted-foreground/80">
                            Last activity recorded {{ panelLastActivityLabel }}
                        </p>
                    </div>
                    <div v-else>
                        <div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            Frequently Accessed
                        </div>
                        <ul class="flex flex-col divide-y divide-border/70">
                            <li v-for="item in frequentlyAccessed" :key="item.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent transition-colors"
                                    @mousedown.prevent
                                    @click="openFrequentItem(item)"
                                >
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <component :is="item.type === 'folder' ? FolderIcon : FileText" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            <span v-if="item.folder">In {{ item.folder.name }}</span>
                                            <!-- <span v-if="item.views">
                                                <template v-if="item.folder"> • </template>
                                                {{ item.views }} views (last hour)
                                            </span> -->
                                        </p>
                                    </div>
                                    <ArrowUpRight class="h-4 w-4 text-muted-foreground" />
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>
                <template v-else>
                    <div v-if="isQueryTooShort" class="px-4 py-6 text-sm text-muted-foreground">
                        Type at least {{ MIN_QUERY_LENGTH }} characters to search.
                    </div>
                    <div v-else-if="isLoadingSuggestions" class="flex items-center gap-2 px-4 py-6 text-sm text-muted-foreground">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        <span>Searching files...</span>
                    </div>
                    <div v-else-if="suggestionError" class="px-4 py-6 text-sm text-destructive">
                        {{ suggestionError }}
                    </div>
                    <ul v-else-if="suggestions.length" class="flex flex-col divide-y divide-border/70">
                        <li v-for="suggestion in suggestions" :key="suggestion.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent"
                                @mousedown.prevent
                                @click="openSuggestion(suggestion)"
                            >
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <component :is="suggestion.type === 'folder' ? FolderIcon : FileText" class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium" v-html="highlight(suggestion.file_name, trimmedQuery)"></p>
                                    <p class="text-xs text-muted-foreground">
                                        <template v-if="suggestion.type === 'file'">
                                            <span>{{ formatDate(suggestion.created_at) }}</span>
                                            <span v-if="formatMimeTypeLabel(suggestion.mime_type)">
                                                • {{ formatMimeTypeLabel(suggestion.mime_type) }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span>Folder</span>
                                            <span v-if="formatDate(suggestion.created_at)">
                                                • {{ formatDate(suggestion.created_at) }}
                                            </span>
                                        </template>
                                    </p>
                                    <p
                                        v-if="suggestion.type === 'file' && suggestion.snippet"
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground line-clamp-3"
                                        v-html="highlight(suggestion.snippet, trimmedQuery)"
                                    ></p>
                                    <Button
                                    v-if="suggestion.folder"
                                    variant="secondary"
                                    size="sm"
                                    class="mt-2 h-7 gap-1 rounded-full px-2 text-xs"
                                    @mousedown.stop.prevent
                                    @click.stop="openParentFolder(suggestion.folder)"
                                >
                                    <FolderIcon class="h-3 w-3" />
                                    <span class="truncate">{{ suggestion.folder.name }}</span>
                                </Button>
                                </div>
                            </button>
                        </li>
                    </ul>
                    <div v-else class="px-4 py-6 text-sm text-muted-foreground">
                        No files match "{{ searchQuery }}".
                    </div>
                </template>
            </div>
            <div v-if="!isInPanelMode && hasMoreSuggestions && !isQueryTooShort" class="border-t border-border/60 px-3 py-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full justify-between"
                    @mousedown.prevent
                    @click="submitSearch"
                >
                    <span>See all results</span>
                    <Search class="h-4 w-4" />
                </Button>
            </div>
        </div>

    </div>
</template>
