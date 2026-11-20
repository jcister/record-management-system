import type { FolderSchema, File } from '@/types/schema';
import type { GroupBy, RecordsPlacement, SortDirection, SortField } from '@/types/sorting';
import type { SelectionIdentity } from './selection';
import { buildOrderedItems } from './itemOrdering';

export type GroupedFiles = {
    key: string;
    label: string;
    items: File[];
};

type Primitive = string | number | Date;

export function sortFolders(
    folders: FolderSchema[],
    sortField: SortField,
    sortDirection: SortDirection,
): FolderSchema[] {
    return [...folders].sort((a, b) =>
        compareValues(resolveFolderValue(a, sortField), resolveFolderValue(b, sortField), sortDirection),
    );
}

export function sortFiles(
    files: File[],
    sortField: SortField,
    sortDirection: SortDirection,
    recordsPlacement: RecordsPlacement = 'top',
): File[] {
    return [...files].sort((a, b) => {
        if (recordsPlacement === 'top') {
            const aPriority = recordPriority(a);
            const bPriority = recordPriority(b);

            if (aPriority !== bPriority) {
                return aPriority - bPriority;
            }
        }

        return compareValues(resolveFileValue(a, sortField), resolveFileValue(b, sortField), sortDirection);
    });
}

export function buildOrderedFromSorted(
    folders: FolderSchema[],
    files: File[],
): SelectionIdentity[] {
    return buildOrderedItems(folders, files);
}

export function groupFiles(files: File[], groupBy: GroupBy): GroupedFiles[] {
    if (!groupBy) {
        return [];
    }

    const buckets = new Map<string, GroupedFiles>();
    const orderedKeys: string[] = [];

    files.forEach(file => {
        const { key, label } = buildGroupKey(file, groupBy);

        if (!buckets.has(key)) {
            buckets.set(key, { key, label, items: [] });
            orderedKeys.push(key);
        }

        buckets.get(key)?.items.push(file);
    });

    return orderedKeys.map(key => buckets.get(key)!).filter(Boolean);
}

function compareValues(a: Primitive, b: Primitive, direction: SortDirection): number {
    const factor = direction === 'asc' ? 1 : -1;

    if (a instanceof Date && b instanceof Date) {
        return (a.getTime() - b.getTime()) * factor;
    }

    if (typeof a === 'number' && typeof b === 'number') {
        return (a - b) * factor;
    }

    return String(a).localeCompare(String(b)) * factor;
}

function resolveFolderValue(folder: FolderSchema, field: SortField): Primitive {
    switch (field) {
        case 'date':
            return new Date(folder.updated_at ?? folder.created_at ?? 0);
        case 'size':
            return (folder.files_count ?? 0) + (folder.children_count ?? 0);
        case 'type':
            return 'Folder';
        default:
            return folder.name?.toLowerCase() ?? '';
    }
}

function resolveFileValue(file: File, field: SortField): Primitive {
    switch (field) {
        case 'date':
            return new Date(file.updated_at ?? file.created_at ?? 0);
        case 'size':
            return file.file_size ?? 0;
        case 'type':
            return fileTypeLabel(file);
        default:
            return file.file_name?.toLowerCase() ?? '';
    }
}

function recordPriority(file: File): number {
    return file.is_record === false ? 1 : 0;
}

function buildGroupKey(file: File, groupBy: GroupBy): { key: string; label: string } {
    switch (groupBy) {
        case 'date': {
            const date = file.updated_at ? new Date(file.updated_at) : (file.created_at ? new Date(file.created_at) : null);
            const key = date ? date.toISOString().slice(0, 10) : 'unknown-date';
            const label = date
                ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : 'Unknown date';
            return { key, label };
        }
        case 'size': {
            const size = file.file_size ?? 0;
            const hasFile = Boolean(file.file_path);

            if (!hasFile || size === 0) {
                return { key: 'no-file', label: 'No file attached' };
            }

            const mb = size / (1024 * 1024);

            if (mb < 1) return { key: 'lt-1', label: '< 1 MB' };
            if (mb < 10) return { key: '1-10', label: '1 – 10 MB' };
            if (mb < 100) return { key: '10-100', label: '10 – 100 MB' };
            return { key: '100-plus', label: '100+ MB' };
        }
        case 'type': {
            const type = fileTypeLabel(file) || 'Unknown';
            return { key: type.toLowerCase(), label: type };
        }
        default:
            return { key: 'none', label: 'Ungrouped' };
    }
}

function fileTypeLabel(file: File): string {
    if (file.is_record === false) {
        return 'Stored Files';
    }

    const mime = file.mime_type?.toLowerCase() ?? '';

    if (mime.includes('pdf') || mime.startsWith('image/')) {
        return 'Records';
    }

    if (mime) {
        const [type, subtype] = mime.split('/') as [string, string?];
        if (subtype) return subtype.toUpperCase();
        if (type) return type.toUpperCase();
    }

    return 'Unknown';
}
