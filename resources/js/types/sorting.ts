export type SortField = 'name' | 'date' | 'size' | 'type';
export type SortDirection = 'asc' | 'desc';
export type GroupBy = 'date' | 'size' | 'type' | null;
export type RecordsPlacement = 'top' | 'mixed';

export const defaultSortDirection: Record<SortField, SortDirection> = {
    name: 'asc',
    date: 'desc',
    size: 'asc',
    type: 'asc',
};
