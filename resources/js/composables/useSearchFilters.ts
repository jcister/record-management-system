import { computed, reactive, ref } from 'vue';

import type { File, FolderSchema } from '@/types/schema';
import type { FilterOptions, SearchFilters } from '@/types/filters';

type SearchResult = FileResult | FolderResult;

interface FileResult {
    id: string;
    type: 'file';
    name: string;
    created_at: string | null;
    url: string;
    mime_type: string | null;
    file_size: number;
    folder?: { id: string; name: string } | null;
    snippet?: string | null;
    // Filter-related fields
    date_created?: string | null;
    date_released?: string | null;
    author?: string | null;
    metadata_tag_ids?: number[];
    physical_location_tag_ids?: number[];
    retention_duration?: 'one_year' | 'ten_years' | 'perpetual' | null;
    is_record?: boolean;
    folder_id?: string | null;
    root_folder_id?: string | null;
}

interface FolderResult {
    id: string;
    type: 'folder';
    name: string;
    created_at: string | null;
    url: string;
    parent?: { id: string; name: string } | null;
}

const defaultFilters = (): SearchFilters => ({
    dateCreatedFrom: '',
    dateCreatedTo: '',
    dateReleasedFrom: '',
    dateReleasedTo: '',
    rootFolderIds: [],
    authors: [],
    metadataTagIds: [],
    physicalLocationTagIds: [],
    retentionDurations: [],
    isRecord: null,
});

// Shared state across all instances
const filters = reactive<SearchFilters>(defaultFilters());
const filterOptions = ref<FilterOptions | null>(null);
const isLoadingOptions = ref(false);

export function useSearchFilters() {

    const activeFilterCount = computed(() => {
        let count = 0;
        if (filters.dateCreatedFrom || filters.dateCreatedTo) count++;
        if (filters.dateReleasedFrom || filters.dateReleasedTo) count++;
        if (filters.rootFolderIds.length > 0) count++;
        if (filters.authors.length > 0) count++;
        if (filters.metadataTagIds.length > 0) count++;
        if (filters.physicalLocationTagIds.length > 0) count++;
        if (filters.retentionDurations.length > 0) count++;
        if (filters.isRecord !== null) count++;
        return count;
    });

    const hasActiveFilters = computed(() => activeFilterCount.value > 0);

    const clearAllFilters = () => {
        Object.assign(filters, defaultFilters());
    };

    const fetchFilterOptions = async () => {
        // Always fetch fresh data from database - no caching
        isLoadingOptions.value = true;
        try {
            const response = await fetch('/api/search/filter-options', {
                headers: {
                    Accept: 'application/json',
                    'Cache-Control': 'no-cache',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch filter options');
            }

            filterOptions.value = await response.json();
            return filterOptions.value;
        } catch (error) {
            console.error('Error fetching filter options:', error);
            return null;
        } finally {
            isLoadingOptions.value = false;
        }
    };

    const isDateInRange = (date: string | null | undefined, from: string, to: string): boolean => {
        if (!date) return !from && !to; // If no date, only pass if no date filter set

        const dateValue = new Date(date);

        if (from) {
            const fromDate = new Date(from);
            if (dateValue < fromDate) return false;
        }

        if (to) {
            const toDate = new Date(to);
            if (dateValue > toDate) return false;
        }

        return true;
    };

    const filterByDateCreated = (items: SearchResult[]): SearchResult[] => {
        if (!filters.dateCreatedFrom && !filters.dateCreatedTo) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            return isDateInRange(fileItem.date_created, filters.dateCreatedFrom, filters.dateCreatedTo);
        });
    };

    const filterByDateReleased = (items: SearchResult[]): SearchResult[] => {
        if (!filters.dateReleasedFrom && !filters.dateReleasedTo) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            return isDateInRange(fileItem.date_released, filters.dateReleasedFrom, filters.dateReleasedTo);
        });
    };

    const belongsToRootFolder = (fileItem: FileResult, rootFolderIds: string[]): boolean => {
        // Check if the file's root folder ID matches any selected root folder
        if (!fileItem.root_folder_id) return false;
        return rootFolderIds.includes(fileItem.root_folder_id);
    };

    const filterByRootFolder = (items: SearchResult[]): SearchResult[] => {
        if (filters.rootFolderIds.length === 0) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') {
                // For folders, check if they are root folders or children of root folders
                // This is a simplified check - you may need to enhance based on your folder structure
                return filters.rootFolderIds.includes(item.id);
            }
            const fileItem = item as FileResult;
            return belongsToRootFolder(fileItem, filters.rootFolderIds);
        });
    };

    const filterByAuthor = (items: SearchResult[]): SearchResult[] => {
        if (filters.authors.length === 0) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            return fileItem.author && filters.authors.includes(fileItem.author);
        });
    };

    const filterByMetadataTags = (items: SearchResult[]): SearchResult[] => {
        if (filters.metadataTagIds.length === 0) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            if (!fileItem.metadata_tag_ids || fileItem.metadata_tag_ids.length === 0) {
                return false;
            }
            // Check if file has ANY of the selected tags (OR logic)
            return filters.metadataTagIds.some((tagId) => fileItem.metadata_tag_ids!.includes(tagId));
        });
    };

    const filterByPhysicalLocationTags = (items: SearchResult[]): SearchResult[] => {
        if (filters.physicalLocationTagIds.length === 0) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            if (!fileItem.physical_location_tag_ids || fileItem.physical_location_tag_ids.length === 0) {
                return false;
            }
            // Check if file has ANY of the selected tags (OR logic)
            return filters.physicalLocationTagIds.some((tagId) =>
                fileItem.physical_location_tag_ids!.includes(tagId),
            );
        });
    };

    const filterByRetentionDuration = (items: SearchResult[]): SearchResult[] => {
        if (filters.retentionDurations.length === 0) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            return fileItem.retention_duration && filters.retentionDurations.includes(fileItem.retention_duration);
        });
    };

    const filterByRecordType = (items: SearchResult[]): SearchResult[] => {
        if (filters.isRecord === null) {
            return items;
        }

        return items.filter((item) => {
            if (item.type === 'folder') return true; // Skip folders for this filter
            const fileItem = item as FileResult;
            return fileItem.is_record === filters.isRecord;
        });
    };

    const applyAllFilters = (items: SearchResult[]): SearchResult[] => {
        let filteredItems = items;

        filteredItems = filterByDateCreated(filteredItems);
        filteredItems = filterByDateReleased(filteredItems);
        filteredItems = filterByRootFolder(filteredItems);
        filteredItems = filterByAuthor(filteredItems);
        filteredItems = filterByMetadataTags(filteredItems);
        filteredItems = filterByPhysicalLocationTags(filteredItems);
        filteredItems = filterByRetentionDuration(filteredItems);
        filteredItems = filterByRecordType(filteredItems);

        return filteredItems;
    };

    return {
        filters,
        filterOptions,
        isLoadingOptions,
        activeFilterCount,
        hasActiveFilters,
        clearAllFilters,
        fetchFilterOptions,
        applyAllFilters,
    };
}
