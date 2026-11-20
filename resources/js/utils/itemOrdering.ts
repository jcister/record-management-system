import type { SelectionIdentity } from './selection';
import type { FolderSchema, File } from '@/types/schema';

/**
 * Build an ordered list of selection identities from folders and files
 */
export function buildOrderedItems(
    folders: FolderSchema[],
    files: File[],
): SelectionIdentity[] {
    return [
        ...folders.map(folder => ({ id: folder.id, type: 'folder' as const })),
        ...files.map(file => ({ id: file.id, type: 'file' as const })),
    ];
}
