import type { SelectionIdentity } from './selection';
import type { SelectedItemType } from '@/types';
import type { FolderSchema, File } from '@/types/schema';

interface BuildContextOptions {
    selections: SelectionIdentity[];
    folders: FolderSchema[];
    files: File[];
    currentFolderId?: string | null;
}

/**
 * Build selected contexts from selection identities
 */
export function buildSelectedContexts({
    selections,
    folders,
    files,
    currentFolderId = null,
}: BuildContextOptions): SelectedItemType[] {
    return selections
        .map(selection => {
            if (selection.type === 'folder') {
                const folder = folders.find(f => f.id === selection.id);
                if (!folder) return null;

                return {
                    id: folder.id,
                    type: 'folder' as const,
                    name: folder.name,
                    isBookmarked: folder.is_bookmarked,
                    bookmarkId: folder.bookmark_id,
                    currentFolderId,
                } satisfies SelectedItemType;
            }

            const file = files.find(f => f.id === selection.id);
            if (!file) return null;

            return {
                id: file.id,
                type: 'file' as const,
                name: file.file_name,
                isBookmarked: file.is_bookmarked,
                bookmarkId: file.bookmark_id,
                currentFolderId,
                hasFile: Boolean(file.file_path),
                status: file.status,
                pendingScanMetadata: file.pending_scan_metadata,
            } satisfies SelectedItemType;
        })
        .filter(Boolean) as SelectedItemType[];
}
