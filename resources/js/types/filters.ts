export type RetentionDuration = 'one_year' | 'ten_years' | 'perpetual';

export interface SearchFilters {
    dateCreatedFrom: string;
    dateCreatedTo: string;
    dateReleasedFrom: string;
    dateReleasedTo: string;
    rootFolderIds: string[];
    authors: string[];
    metadataTagIds: number[];
    physicalLocationTagIds: number[];
    retentionDurations: RetentionDuration[];
    isRecord: boolean | null; // null = all, true = records only, false = non-records only
}

export interface FilterOption {
    id: string | number;
    name: string;
}

export interface FilterOptions {
    authors: FilterOption[];
    rootFolders: FilterOption[];
    departments: FilterOption[];
    metadataTags: FilterOption[];
    physicalLocationTags: FilterOption[];
    retentionDurations: Array<{ value: RetentionDuration; label: string }>;
}
